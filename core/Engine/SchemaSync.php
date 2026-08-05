<?php

declare(strict_types=1);

namespace Volt\Core\Engine;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Forge;
use CodeIgniter\Database\RawSql;
use Config\Volt as VoltConfig;
use Throwable;
use Volt\Core\Audit\AuditTrailWriter;
use Volt\Core\Database\TableNameResolver;
use Volt\Core\Database\VoltDatabase;
use Volt\Core\Validation\MetadataValidator;

/**
 * Đồng bộ schema vật lý từ metadata. *
 * - Không phá hủy: thay đổi "phá vỡ" (đổi kiểu, xóa cột, drop index/constraint)
 *   chỉ nằm trong plan khi được bật flag; việc apply thực tế phải qua
 *   MigrationCoordinator để đảm bảo preview + approval (không tự ý thay đổi production).
 * - Dùng CI4 Forge cho mọi DDL; raw SQL chỉ dùng khi Forge không hỗ trợ
 *   (CREATE INDEX trên bảng đã tồn tại, RENAME COLUMN, USING, backfill).
 * - Ghi mỗi thao tác đã apply vào sys_schema_migration.
 */
class SchemaSync
{
    private const TABLE_MIGRATION = 'sys_schema_migration';

    private readonly BaseConnection $db;
    private readonly MetadataValidator $validator;
    private ?QueueDispatcher $queue;
    private ?VoltConfig $voltConfig = null;

    public function __construct(
        ?BaseConnection $db = null,
        ?MetadataValidator $validator = null,
        ?QueueDispatcher $queue = null,
    ) {
        $this->db = $db ?? VoltDatabase::connection();
        $this->validator = $validator ?? new MetadataValidator();
        $this->queue = $queue;
    }

    /**
     * Đồng bộ schema cho một entity.
     *
     * @param array<string, mixed> $opts dry_run|allow_drop|allow_rename|allow_type_change|renames|prune|indexes
     *
     * @return array{status: string, message: ?string, logs: list<string>, plan: list<array<string, mixed>>, dry_run: bool}
     */
    public function syncEntity(string $entityName, array $opts = []): array
    {
        $plan = $this->planEntity($entityName, $opts);

        if (($plan['status'] ?? '') !== 'success') {
            return $plan;
        }

        if (! ($opts['dry_run'] ?? false)) {
            $this->applyPlan($plan, $opts);

            // Schema thay đổi thực sự -> warm lại metadata cache qua queue.
            if (($plan['plan'] ?? []) !== []) {
                $this->queue()?->dispatch('rebuild_metadata_cache');
            }
        }

        return $plan;
    }

    /**
     * Kiểm tra dữ liệu thực tế của entity mà không sửa schema.
     *
     * Báo cáo: tổng số dòng, các `name` trùng lặp, và child rows mồ côi
     * (child table có `parent` trỏ đến parent không còn tồn tại).
     *
     * @param array<string, mixed> $opts
     *
     * @return array{status: string, message: ?string, rows: int, duplicates: list<array{name: string, count: int}>, orphan_children: list<array{entity: string, table: string, count: int}>}
     */
    public function checkData(string $entityName, array $opts = []): array
    {
        $entityName = $this->validator->assertEntityName($entityName);
        $normalizedName = TableNameResolver::normalizeIdentifier($entityName);
        $tableName = TableNameResolver::entity($entityName);
        $meta = $this->getEntityMeta($entityName);

        $result = [
            'status'          => 'success',
            'message'         => null,
            'rows'            => 0,
            'duplicates'      => [],
            'orphan_children' => [],
        ];

        $metaFields = $this->db->table('sys_entity_field')
            ->where('parent', $normalizedName)
            ->orderBy('idx', 'ASC')
            ->get()
            ->getResultArray();

        $metaFields = array_map(fn (array $field): array => $this->validator->normalizeFieldRow($field), $metaFields);

        if ($metaFields === []) {
            $result['status']  = 'error';
            $result['message'] = "Metadata trống cho Entity: {$entityName}";

            return $result;
        }

        $row = $this->db->query("SELECT COUNT(*) AS cnt FROM {$tableName}")->getRow();
        $result['rows'] = (int) ($row->cnt ?? 0);

        $dup = $this->db->query(
            "SELECT name, COUNT(*) AS cnt FROM {$tableName} WHERE name <> '' GROUP BY name HAVING COUNT(*) > 1",
        );
        foreach ($dup->getResultArray() as $d) {
            $result['duplicates'][] = [
                'name'  => (string) ($d['name'] ?? ''),
                'count' => (int) ($d['cnt'] ?? 0),
            ];
        }

        if ((int) ($meta['istable'] ?? 0) !== 1) {
            foreach ($this->childEntityNames($metaFields) as $childName) {
                $childTable = TableNameResolver::entity($childName);
                $orphan = $this->db->query(
                    "SELECT COUNT(*) AS cnt FROM {$childTable} c "
                    . "WHERE c.parent <> '' AND c.parent NOT IN (SELECT name FROM {$tableName})",
                )->getRow();

                $count = (int) ($orphan->cnt ?? 0);
                if ($count > 0) {
                    $result['orphan_children'][] = [
                        'entity' => $childName,
                        'table'  => $childTable,
                        'count'  => $count,
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Tính toán plan thay đổi schema mà không áp dụng.
     *
     * @param array<string, mixed> $opts
     *
     * @return array{status: string, message: ?string, logs: list<string>, plan: list<array<string, mixed>>, dry_run: bool}
     */
    public function planEntity(string $entityName, array $opts = []): array
    {
        $entityName = $this->validator->assertEntityName($entityName);

        $ops = [];
        $logs = [];
        $message = null;

        $ok = $this->doPlanEntity($entityName, $this->getEntityMeta($entityName)['istable'] === 1, $ops, $logs, $opts, $message);

        return [
            'status'  => $ok ? 'success' : 'error',
            'message' => $message,
            'logs'    => $logs,
            'plan'    => $ok ? $ops : [],
            'dry_run' => (bool) ($opts['dry_run'] ?? false),
        ];
    }

    /**
     * @param list<array<string, mixed>> $ops
     * @param list<string> $logs
     * @param array<string, mixed> $opts
     */
    private function doPlanEntity(string $entityName, bool $isChild, array &$ops, array &$logs, array $opts, ?string &$message): bool
    {
        $normalizedName = TableNameResolver::normalizeIdentifier($entityName);
        $tableName = TableNameResolver::entity($entityName);
        $legacyTableName = TableNameResolver::legacyEntity($entityName);

        $metaFields = $this->db->table('sys_entity_field')
            ->where('parent', $normalizedName)
            ->orderBy('idx', 'ASC')
            ->get()
            ->getResultArray();

        $metaFields = array_map(fn (array $field): array => $this->validator->normalizeFieldRow($field), $metaFields);

        if ($metaFields === []) {
            $message = "Metadata trống cho Entity: {$entityName}";

            return false;
        }

        $customAttributes = $this->getEntityMeta($entityName)['custom_attributes'];

        $currentSchema = $this->getPostgresSchema($tableName);

        // Đổi tên bảng legacy (giữ dữ liệu) trước khi xử lý phần còn lại.
        if ($currentSchema === [] && $legacyTableName !== '' && $legacyTableName !== $tableName) {
            $legacySchema = $this->getPostgresSchema($legacyTableName);
            if ($legacySchema !== []) {
                $ops[] = $this->makeOp('rename_table', $entityName, $tableName, severity: 'safe', sql: "RENAME TABLE {$legacyTableName} TO {$tableName}", extra: ['old_table' => $legacyTableName]);
                $logs[] = "🔁 Kế hoạch đổi tên bảng legacy {$legacyTableName} -> {$tableName}";
                $currentSchema = $legacySchema;
            }
        }

        $baseColumns = $this->baseColumns($isChild);

        if ($currentSchema === []) {
            // Kịch bản A: bảng chưa tồn tại -> CREATE TABLE.
            $columns = $baseColumns;
            foreach ($metaFields as $field) {
                if ($field['is_child_table']) {
                    continue;
                }
                $columns[$field['fieldname']] = $this->columnDefFromField($field);
                $logs[] = "Rèn mới cột: {$field['fieldname']}";
            }

            $ops[] = $this->makeOp('create_table', $entityName, $tableName, severity: 'safe', sql: "CREATE TABLE {$tableName}", extra: ['columns' => $columns]);
            $logs[] = "➔ Kế hoạch tạo bảng vật lý: {$tableName}";
        } else {
            // Kịch bản B: bảng đã tồn tại -> vá cột thiếu + phát hiện thay đổi kiểu.
            foreach ($baseColumns as $colName => $colDef) {
                if (isset($currentSchema[$colName])) {
                    continue;
                }
                $this->planAddColumn($tableName, $entityName, $colName, $colDef, $ops, $logs, $opts);
            }

            $this->planRenames($tableName, $entityName, $currentSchema, $metaFields, $ops, $logs, $opts);

            foreach ($metaFields as $field) {
                if ($field['is_child_table']) {
                    continue;
                }

                $colName = $field['fieldname'];
                $def = $this->columnDefFromField($field);

                if (! isset($currentSchema[$colName])) {
                    $this->planAddColumn($tableName, $entityName, $colName, $def, $ops, $logs, $opts);
                    continue;
                }

                $this->planTypeChange($tableName, $entityName, $field, $currentSchema[$colName], $ops, $logs, $opts);
            }

            $this->planOrphanDrops($tableName, $entityName, $currentSchema, $metaFields, $baseColumns, $ops, $logs, $opts);
        }

        $this->planIndexes($tableName, $entityName, $metaFields, $customAttributes, $ops, $logs, $opts);
        $this->planConstraints($tableName, $entityName, $customAttributes, $ops, $logs, $opts);

        // Đồng bộ child table (mode separate).
        if (! $isChild) {
            foreach ($this->childEntityNames($metaFields) as $childName) {
                if (! $this->entityExists($childName)) {
                    continue;
                }
                if (! $this->doPlanEntity($childName, true, $ops, $logs, $opts, $message)) {
                    return false;
                }
                $logs[] = "🔗 Kế hoạch đồng bộ child table entity: {$childName}";
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $plan
     * @param array<string, mixed> $opts
     */
    private function applyPlan(array &$plan, array $opts): void
    {
        $this->acquireAdvisoryLock();

        try {
            foreach ($plan['plan'] as $op) {
                if (! $this->opAllowed($op, $opts)) {
                    continue;
                }

                $this->applyOperation($op);
                $this->logMigration($op);
            }
        } finally {
            $this->releaseAdvisoryLock();
        }
    }

    /**
     * Chặn 2 luồng schema sync chạy song song trên cùng DB để tránh
     * deadlock/race khi cùng ALTER một bảng. Dùng session-level advisory lock
     * (không mở transaction) để không ảnh hưởng tới CREATE INDEX CONCURRENTLY.
     */
    public function acquireAdvisoryLock(): void
    {
        $key = max(1, (int) $this->voltConfig()->schemaSyncAdvisoryLockKey);
        $this->db->query('SELECT pg_advisory_lock(' . $key . ')');
    }

    public function releaseAdvisoryLock(): void
    {
        $key = max(1, (int) $this->voltConfig()->schemaSyncAdvisoryLockKey);
        $this->db->query('SELECT pg_advisory_unlock(' . $key . ')');
    }

    /** Yêu cầu approval cho op breaking hay không (theo cấu hình). */
    public function requiresApprovalForBreaking(): bool
    {
        return (bool) $this->voltConfig()->schemaSyncRequireApprovalForBreaking;
    }

    /** Apply một operation đơn lẻ (dùng bởi cả applyPlan lẫn MigrationCoordinator). */
    public function applyOperation(array $op): void
    {
        $this->applyOp($op);

        $operation = (string) ($op['operation'] ?? '');
        $after = [
            'operation' => $operation,
            'table'     => (string) ($op['table'] ?? ''),
            'severity'  => (string) ($op['severity'] ?? 'safe'),
        ];

        if ($operation === 'rename_table') {
            $after['old_table'] = (string) ($op['old_table'] ?? '');
        }

        if (($op['column'] ?? null) !== null) {
            $after['column'] = (string) $op['column'];
        }

        try {
            service('voltAuditTrailWriter')->write(
                AuditTrailWriter::CAT_SCHEMA,
                'schema:apply',
                'entity',
                (string) ($op['entity'] ?? $this->entityForTable((string) ($op['table'] ?? ''))),
                [],
                $after,
            );
        } catch (Throwable $throwable) {
            service('voltErrorLog')->logException($throwable, [
                'table' => $op['table'] ?? null,
                'operation' => $operation,
                'operation_tag' => 'schemaApplyAudit',
            ], 'schema', 'schema_apply_audit_failed');
        }
    }

    /** Kiểm tra operation có được phép apply với opts cho trước hay không. */
    public function isOpAllowed(array $op, array $opts): bool
    {
        return $this->opAllowed($op, $opts);
    }

    /**
     * @param array<string, mixed> $op
     * @param array<string, mixed> $opts
     */
    private function opAllowed(array $op, array $opts): bool
    {
        if (($op['severity'] ?? '') !== 'breaking') {
            return true;
        }

        // Production guard: mọi thao tác drop bị chặn trực tiếp ở production
        // trừ khi admin bật schemaSyncAllowDirectDropInProduction.
        $isDrop = in_array($op['operation'] ?? '', ['drop_column', 'drop_index', 'drop_constraint'], true);
        if ($isDrop && $this->isProductionEnv() && ! (bool) $this->voltConfig()->schemaSyncAllowDirectDropInProduction) {
            return false;
        }

        // Thao tác phá vỡ chỉ apply khi flag tương ứng được bật ở lúc plan.
        return match ($op['operation'] ?? '') {
            'drop_column'     => (bool) ($opts['allow_drop'] ?? $opts['prune'] ?? false),
            'drop_index'      => (bool) ($opts['allow_drop'] ?? $opts['prune'] ?? false),
            'drop_constraint' => (bool) ($opts['allow_drop'] ?? $opts['prune'] ?? false),
            'alter_column'    => (bool) ($opts['allow_type_change'] ?? false),
            default           => false,
        };
    }

    /**
     * @param array<string, mixed> $op
     */
private function applyOp(array $op): void
    {
        $table = (string) $op['table'];

        match ($op['operation']) {
            'rename_table'    => (new Forge($this->db))->renameTable((string) $op['old_table'], $table),
            'create_table'    => $this->applyCreateTable($op),
            'add_column'      => (new Forge($this->db))->addColumn($table, [$op['column'] => $this->fieldDef($op['def'])]),
            'alter_column'    => $this->applyAlterColumn($op),
            'rename_column'   => $this->applyRenameColumn($op),
            'drop_column'     => $this->applyDropColumn($op),
            'create_index'    => $this->applyCreateIndex($op),
            'drop_index'      => $this->applyDropIndex($op),
            'backfill_data'   => $this->applyBackfillData($op),
            'set_not_null'    => $this->applySetNotNull($op),
            'add_constraint'  => $this->applyAddConstraint($op),
            'drop_constraint' => $this->applyDropConstraint($op),
            default           => null,
        };
    }

    /**
     * Suy đoán tên entity từ tên bảng vật lý (bỏ tiền tố module).
     */
    private function entityForTable(string $table): string
    {
        $prefixes = ['tab', 'sys_', 'style_', 'doc_'];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($table, $prefix)) {
                return substr($table, strlen($prefix));
            }
        }

        return $table;
    }

    /**
     * @param array<string, mixed> $op
     */
    private function applyCreateTable(array $op): void
    {
        $forge = new Forge($this->db);

        foreach ($op['columns'] as $name => $def) {
            $forge->addField([$name => $this->fieldDef($def)]);
        }
        $forge->addKey('name', true);
        $forge->createTable((string) $op['table']);
    }

    /**
     * @param array<string, mixed> $op
     */
    private function applyAlterColumn(array $op): void
    {
        $table = (string) $op['table'];
        $column = (string) $op['column'];
        $def = $op['def'] ?? [];

        $apply = function () use ($op, $table, $column, $def): void {
            $using = $op['using'] ?? null;

            if (is_string($using) && $using !== '') {
                $sql = 'ALTER TABLE ' . $this->db->escapeIdentifiers($table)
                     . ' ALTER COLUMN ' . $this->db->escapeIdentifiers($column)
                     . ' TYPE ' . $this->pgType($def);
                if ($using !== '') {
                    $sql .= ' USING ' . $using;
                }
                $this->db->query($sql);
            } else {
                (new Forge($this->db))->modifyColumn($table, [$column => $this->fieldDef($def)]);
            }

            $this->applyColumnNullability($table, $column, $def);
            $this->applyColumnDefault($table, $column, $def);
        };

        if (($op['severity'] ?? '') === 'breaking') {
            $this->withLockTimeout($apply);
        } else {
            $apply();
        }
    }

    /**
     * @param array<string, mixed> $def
     */
    private function applyColumnNullability(string $table, string $column, array $def): void
    {
        $escTable = $this->db->escapeIdentifiers($table);
        $escCol = $this->db->escapeIdentifiers($column);

        if (($def['null'] ?? true) === true) {
            $this->db->query("ALTER TABLE {$escTable} ALTER COLUMN {$escCol} DROP NOT NULL");
        } else {
            $this->db->query("ALTER TABLE {$escTable} ALTER COLUMN {$escCol} SET NOT NULL");
        }
    }

    /**
     * @param array<string, mixed> $def
     */
    private function applyColumnDefault(string $table, string $column, array $def): void
    {
        if (! array_key_exists('default', $def)) {
            return;
        }

        $escTable = $this->db->escapeIdentifiers($table);
        $escCol = $this->db->escapeIdentifiers($column);
        $value = $def['default'];
        $expr = $value instanceof RawSql ? (string) $value : "'" . $this->db->escape((string) $value) . "'";

        $this->db->query("ALTER TABLE {$escTable} ALTER COLUMN {$escCol} SET DEFAULT {$expr}");
    }

    /**
     * @param array<string, mixed> $op
     */
    private function applyDropColumn(array $op): void
    {
        $this->withLockTimeout(fn (): bool => (new Forge($this->db))->dropColumn((string) $op['table'], (string) $op['column']));
    }

    /**
     * @param array<string, mixed> $op
     */
    private function applyRenameColumn(array $op): void
    {
        $sql = 'ALTER TABLE ' . $this->db->escapeIdentifiers((string) $op['table'])
             . ' RENAME COLUMN ' . $this->db->escapeIdentifiers((string) $op['from'])
             . ' TO ' . $this->db->escapeIdentifiers((string) $op['to']);

        $this->db->query($sql);
    }

    /**
     * @param array<string, mixed> $op
     */
    private function applyCreateIndex(array $op): void
    {
        $concurrent = (bool) ($op['concurrent'] ?? false);
        $sql = 'CREATE INDEX ' . ($concurrent ? 'CONCURRENTLY IF NOT EXISTS ' : 'IF NOT EXISTS ')
             . $this->db->escapeIdentifiers($this->sanitizeIdentifier((string) $op['index_name']))
             . ' ON ' . $this->db->escapeIdentifiers((string) $op['table'])
             . ' (' . $this->db->escapeIdentifiers((string) $op['column']) . ')';

        $this->db->query($sql);
    }

    /**
     * @param array<string, mixed> $op
     */
    private function applyDropIndex(array $op): void
    {
        $this->db->query(
            'DROP INDEX IF EXISTS ' . $this->db->escapeIdentifiers($this->sanitizeIdentifier((string) $op['index_name'])),
        );
    }

    /**
     * @param array<string, mixed> $op
     */
    private function applyBackfillData(array $op): void
    {
        $table = (string) $op['table'];
        $column = (string) $op['column'];
        $expr = (string) ($op['expr'] ?? 'NULL');
        $where = (string) ($op['where'] ?? '');

        $sql = 'UPDATE ' . $this->db->escapeIdentifiers($table)
             . ' SET ' . $this->db->escapeIdentifiers($column) . ' = ' . $expr;

        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }

        $this->db->query($sql);
    }

    /**
     * @param array<string, mixed> $op
     */
    private function applySetNotNull(array $op): void
    {
        $this->db->query(
            'ALTER TABLE ' . $this->db->escapeIdentifiers((string) $op['table'])
            . ' ALTER COLUMN ' . $this->db->escapeIdentifiers((string) $op['column'])
            . ' SET NOT NULL',
        );
    }

    /**
     * @param array<string, mixed> $op
     */
    private function applyAddConstraint(array $op): void
    {
        $this->db->query((string) $op['sql']);
    }

    /**
     * @param array<string, mixed> $op
     */
    private function applyDropConstraint(array $op): void
    {
        $this->db->query(
            'ALTER TABLE ' . $this->db->escapeIdentifiers((string) $op['table'])
            . ' DROP CONSTRAINT IF EXISTS ' . $this->db->escapeIdentifiers((string) $op['constraint_name']),
        );
    }

    /**
     * Ghi một operation đã apply vào sys_schema_migration.
     *
     * @param array<string, mixed> $op
     * @param array<string, mixed> $extra migration_id|status|applied_at|created_by
     */
    public function logMigration(array $op, array $extra = []): void
    {
        $inverse = $this->inverseSqlFor($op);

        $this->db->table(self::TABLE_MIGRATION)->insert(array_filter([
            'entity'      => (string) ($op['entity'] ?? ''),
            'table_name'  => (string) ($op['table'] ?? ''),
            'operation'   => (string) ($op['operation'] ?? ''),
            'sql'         => (string) ($op['sql'] ?? ''),
            'dry_run'     => 0,
            'created_by'  => (string) ($extra['created_by'] ?? 'system'),
            'migration_id' => $extra['migration_id'] ?? null,
            'status'      => (string) ($extra['status'] ?? 'applied'),
            'severity'    => (string) ($op['severity'] ?? 'safe'),
            'downtime'    => (string) ($op['downtime'] ?? $this->downtimeFor($op)),
            'inverse_sql' => $inverse,
            'applied_at'  => $extra['applied_at'] ?? new RawSql('CURRENT_TIMESTAMP'),
        ], static fn (mixed $value): bool => $value !== null && $value !== ''));
    }

    /**
     * Sinh SQL nghịch đảo cho một operation (dùng cho rollback).
     * Trả về null khi thao tác làm mất dữ liệu và không thể tự động đảo ngược.
     *
     * @param array<string, mixed> $op
     */
    public function inverseSqlFor(array $op): ?string
    {
        $table = (string) $op['table'];
        $escTable = $this->db->escapeIdentifiers($table);

        return match ($op['operation']) {
            'create_table'   => 'DROP TABLE IF EXISTS ' . $escTable,
            'add_column'     => 'ALTER TABLE ' . $escTable . ' DROP COLUMN IF EXISTS ' . $this->db->escapeIdentifiers((string) $op['column']),
            'rename_column'  => 'ALTER TABLE ' . $escTable . ' RENAME COLUMN ' . $this->db->escapeIdentifiers((string) $op['to']) . ' TO ' . $this->db->escapeIdentifiers((string) $op['from']),
            'rename_table'   => 'ALTER TABLE ' . $escTable . ' RENAME TO ' . $this->db->escapeIdentifiers((string) $op['old_table']),
            'create_index'   => 'DROP INDEX IF EXISTS ' . $this->db->escapeIdentifiers($this->sanitizeIdentifier((string) $op['index_name'])),
            'drop_index'     => (string) ($op['sql'] ?? '') !== '' ? (string) $op['sql'] : null,
            'set_not_null'   => 'ALTER TABLE ' . $escTable . ' ALTER COLUMN ' . $this->db->escapeIdentifiers((string) $op['column']) . ' DROP NOT NULL',
            'add_constraint' => 'ALTER TABLE ' . $escTable . ' DROP CONSTRAINT IF EXISTS ' . $this->db->escapeIdentifiers((string) $op['constraint_name']),
            'drop_constraint'=> (string) ($op['sql'] ?? '') !== '' ? (string) $op['sql'] : null,
            // alter_column (nhất là breaking), drop_column, backfill_data: mất dữ liệu / không tự đảo ngược được.
            default          => null,
        };
    }

    /**
     * @param array<string, mixed> $tableName
     * @param list<array<string, mixed>> $metaFields
     * @param list<array<string, mixed>> $ops
     * @param list<string> $logs
     * @param array<string, mixed> $opts
     */
    private function planTypeChange(string $tableName, string $entityName, array $field, array $actual, array &$ops, array &$logs, array $opts): void
    {
        $desired = $this->columnDefFromField($field);

        if ($this->canonicalType($desired) === $this->canonicalActual($actual)) {
            return;
        }

        if ($this->isSafeWiden($desired, $actual)) {
            $ops[] = $this->makeOp('alter_column', $entityName, $tableName, column: $field['fieldname'], severity: 'safe', sql: "ALTER COLUMN {$field['fieldname']}", extra: ['def' => $desired]);
            $logs[] = "↔ Kế hoạch mở rộng cột: {$field['fieldname']} trên {$tableName} (an toàn)";

            return;
        }

        if ($opts['allow_type_change'] ?? false) {
            $extra = ['def' => $desired];
            $using = $this->typeUsingExpr($desired, $actual, $field['fieldname']);
            if ($using !== null) {
                $extra['using'] = $using;
            }
            $ops[] = $this->makeOp('alter_column', $entityName, $tableName, column: $field['fieldname'], severity: 'breaking', sql: "ALTER COLUMN {$field['fieldname']}", extra: $extra);
            $logs[] = "⚠ Kế hoạch đổi kiểu cột: {$field['fieldname']} trên {$tableName} (phá vỡ)";
        } else {
            $logs[] = "⏭ Bỏ qua đổi kiểu cột: {$field['fieldname']} trên {$tableName} (cần --allow-type-change)";
        }
    }

    /**
     * Sinh biểu thức USING hợp lệ để convert dữ liệu khi đổi kiểu cột.
     * Chỉ sinh cho cặp kiểu chuyển đổi an toàn; trả về null → dùng convert mặc định của PG.
     *
     * @param array<string, mixed> $desired
     * @param array<string, mixed> $actual
     */
    private function typeUsingExpr(array $desired, array $actual, string $column): ?string
    {
        $target = strtoupper((string) ($desired['type'] ?? ''));
        $source = strtolower((string) ($actual['type'] ?? ''));
        $escCol = $this->db->escapeIdentifiers($column);

        // Các cast không mất dữ liệu (hoặc kiểm soát được), cho phép tự convert.
        if ($target === 'INTEGER' && in_array($source, ['character varying', 'text', 'numeric'], true)) {
            return "{$escCol}::integer";
        }

        if ($target === 'NUMERIC' && in_array($source, ['character varying', 'text'], true)) {
            return "{$escCol}::numeric";
        }

        if (in_array($target, ['DATE', 'TIMESTAMP', 'TIME'], true) && in_array($source, ['character varying', 'text'], true)) {
            return "{$escCol}::" . strtolower($target);
        }

        if ($target === 'JSONB' && in_array($source, ['character varying', 'text'], true)) {
            return "NULLIF({$escCol}, '')::jsonb";
        }

        return null;
    }

    /**
     * Lập kế hoạch thêm một cột, áp dụng expand/contract (thêm nullable -> backfill
     * -> SET NOT NULL) khi cột là bắt buộc trên bảng đã có dữ liệu.
     *
     * @param array<string, mixed> $def
     * @param list<array<string, mixed>> $ops
     * @param list<string> $logs
     * @param array<string, mixed> $opts
     */
    private function planAddColumn(string $tableName, string $entityName, string $colName, array $def, array &$ops, array &$logs, array $opts): void
    {
        $isRequired = ($def['null'] ?? true) === false;
        $hasRows = $this->tableHasRows($tableName);

        if ($isRequired && $hasRows) {
            $nullableDef = $def;
            $nullableDef['null'] = true;

            $ops[] = $this->makeOp('add_column', $entityName, $tableName, column: $colName, severity: 'safe', sql: "ADD COLUMN {$colName}", extra: ['def' => $nullableDef]);
            $logs[] = "➕ Kế hoạch thêm cột (nullable trước): {$colName} vào {$tableName}";

            $default = $opts['defaults'][$colName] ?? $def['default'] ?? null;
            if ($default !== null) {
                $expr = $default instanceof RawSql ? (string) $default : "'" . $this->db->escape((string) $default) . "'";
                $ops[] = $this->makeOp('backfill_data', $entityName, $tableName, column: $colName, severity: 'safe', sql: "UPDATE {$tableName} SET {$colName} = {$expr}", extra: ['expr' => $expr, 'where' => $colName . ' IS NULL']);
                $logs[] = "🪣 Kế hoạch backfill giá trị mặc định cho {$colName} trên {$tableName}";
                $ops[] = $this->makeOp('set_not_null', $entityName, $tableName, column: $colName, severity: 'safe', sql: "ALTER COLUMN {$colName} SET NOT NULL");
                $logs[] = "🚫 Kế hoạch đặt NOT NULL cho {$colName} trên {$tableName}";
            } else {
                $logs[] = "⏳ Cột {$colName} là bắt buộc không có default — cần backfill thủ công trước khi đặt NOT NULL.";
            }

            return;
        }

        $ops[] = $this->makeOp('add_column', $entityName, $tableName, column: $colName, severity: 'safe', sql: "ADD COLUMN {$colName}", extra: ['def' => $def]);
        $logs[] = "🛠️ Kế hoạch thêm cột: {$colName} vào {$tableName}";
    }

    /**
     * Lập kế hoạch constraint (UNIQUE / CHECK / FK) từ custom_attributes.
     * Chỉ chạy khi metadata khai báo constraint; không tự suy đoán để tránh phá schema.
     *
     * @param mixed $customAttributes
     * @param list<array<string, mixed>> $ops
     * @param list<string> $logs
     * @param array<string, mixed> $opts
     */
    private function planConstraints(string $tableName, string $entityName, mixed $customAttributes, array &$ops, array &$logs, array $opts): void
    {
        $attrs = $this->normalizeCustomAttributes($customAttributes);
        $uniques = $attrs['uniques'] ?? [];
        $checks = $attrs['checks'] ?? [];
        $foreignKeys = $attrs['foreign_keys'] ?? [];

        if ($uniques === [] && $checks === [] && $foreignKeys === []) {
            return;
        }

        $existing = $this->existingConstraints($tableName);
        $prefix = 'ix_' . TableNameResolver::normalizeIdentifier($entityName) . '_';
        $desiredNames = [];

        foreach ($uniques as $fieldname) {
            $fieldname = $this->sanitizeIdentifier((string) $fieldname);
            if ($fieldname === 'idx') {
                continue;
            }
            $name = $prefix . $fieldname . '_uq';
            $desiredNames[$name] = true;
            if (in_array($name, $existing, true)) {
                continue;
            }
            $ops[] = $this->makeOp(
                'add_constraint',
                $entityName,
                $tableName,
                column: $fieldname,
                severity: 'breaking',
                sql: 'ALTER TABLE ' . $this->db->escapeIdentifiers($tableName)
                    . ' ADD CONSTRAINT ' . $this->db->escapeIdentifiers($name)
                    . ' UNIQUE (' . $this->db->escapeIdentifiers($fieldname) . ')',
                extra: ['constraint_name' => $name, 'kind' => 'unique'],
            );
            $logs[] = "🔒 Kế hoạch thêm UNIQUE constraint: {$name} trên {$tableName} ({$fieldname})";
        }

        foreach ($checks as $idx => $check) {
            if (! is_array($check)) {
                continue;
            }
            $fieldname = $this->sanitizeIdentifier((string) ($check['field'] ?? $check['fieldname'] ?? 'check_' . $idx));
            $expr = (string) ($check['expr'] ?? '');
            if ($expr === '') {
                continue;
            }
            $name = $prefix . $fieldname . '_ck' . $idx;
            $desiredNames[$name] = true;
            if (in_array($name, $existing, true)) {
                continue;
            }
            $ops[] = $this->makeOp(
                'add_constraint',
                $entityName,
                $tableName,
                column: $fieldname,
                severity: 'breaking',
                sql: 'ALTER TABLE ' . $this->db->escapeIdentifiers($tableName)
                    . ' ADD CONSTRAINT ' . $this->db->escapeIdentifiers($name)
                    . ' CHECK (' . $expr . ')',
                extra: ['constraint_name' => $name, 'kind' => 'check'],
            );
            $logs[] = "🔒 Kế hoạch thêm CHECK constraint: {$name} trên {$tableName}";
        }

        foreach ($foreignKeys as $fk) {
            if (! is_array($fk)) {
                continue;
            }
            $fieldname = $this->sanitizeIdentifier((string) ($fk['field'] ?? $fk['fieldname'] ?? ''));
            $references = (string) ($fk['references'] ?? '');
            $onDelete = (string) ($fk['on_delete'] ?? 'CASCADE');
            if ($fieldname === 'idx' || $references === '') {
                continue;
            }
            $name = $prefix . $fieldname . '_fk';
            $desiredNames[$name] = true;
            if (in_array($name, $existing, true)) {
                continue;
            }
            $onDelete = $this->sanitizeOnDelete($onDelete);
            $ops[] = $this->makeOp(
                'add_constraint',
                $entityName,
                $tableName,
                column: $fieldname,
                severity: 'breaking',
                sql: 'ALTER TABLE ' . $this->db->escapeIdentifiers($tableName)
                    . ' ADD CONSTRAINT ' . $this->db->escapeIdentifiers($name)
                    . ' FOREIGN KEY (' . $this->db->escapeIdentifiers($fieldname) . ')'
                    . ' REFERENCES ' . $this->db->escapeIdentifiers($references)
                    . ' ON DELETE ' . $onDelete,
                extra: ['constraint_name' => $name, 'kind' => 'foreign_key'],
            );
            $logs[] = "🔗 Kế hoạch thêm FK constraint: {$name} trên {$tableName} ({$fieldname} -> {$references})";
        }

        if (! ($opts['allow_drop'] ?? $opts['prune'] ?? false)) {
            return;
        }

        foreach ($existing as $name) {
            if (! str_starts_with($name, $prefix)) {
                continue;
            }
            if (isset($desiredNames[$name])) {
                continue;
            }
            $ops[] = $this->makeOp(
                'drop_constraint',
                $entityName,
                $tableName,
                severity: 'breaking',
                sql: 'ALTER TABLE ' . $this->db->escapeIdentifiers($tableName)
                    . ' ADD CONSTRAINT ' . $this->db->escapeIdentifiers($name),
                extra: ['constraint_name' => $name],
            );
            $logs[] = "🗑 Kế hoạch xóa constraint dư: {$name} trên {$tableName} (phá vỡ)";
        }
    }

    private function sanitizeOnDelete(string $value): string
    {
        $value = strtoupper(mb_trim($value));
        $allowed = ['CASCADE', 'RESTRICT', 'SET NULL', 'NO ACTION'];

        return in_array($value, $allowed, true) ? $value : 'CASCADE';
    }

    /** @return array<string, mixed> */
    private function normalizeCustomAttributes(mixed $customAttributes): array
    {
        if (is_string($customAttributes)) {
            $customAttributes = json_decode($customAttributes, true);
        }

        return is_array($customAttributes) ? $customAttributes : [];
    }

    /**
     * @param list<array<string, mixed>> $metaFields
     * @param list<array<string, mixed>> $ops
     * @param list<string> $logs
     * @param array<string, mixed> $opts
     */
    private function planRenames(string $tableName, string $entityName, array $currentSchema, array $metaFields, array &$ops, array &$logs, array $opts): void
    {
        $renames = $opts['renames'] ?? [];
        if (! is_array($renames) || $renames === []) {
            return;
        }

        $metaFieldnames = array_column($metaFields, 'fieldname');

        foreach ($renames as $old => $new) {
            $old = mb_trim((string) $old);
            $new = mb_trim((string) $new);

            if ($old === '' || $new === '' || ! isset($currentSchema[$old]) || ! in_array($new, $metaFieldnames, true)) {
                continue;
            }

            if (! ($opts['allow_rename'] ?? false)) {
                $logs[] = "⏭ Bỏ qua đổi tên cột: {$old} -> {$new} (cần --allow-rename)";
                continue;
            }

            $ops[] = $this->makeOp('rename_column', $entityName, $tableName, column: $old, severity: 'safe', sql: "RENAME COLUMN {$old} TO {$new}", extra: ['from' => $old, 'to' => $new]);
            $logs[] = "🔁 Kế hoạch đổi tên cột: {$old} -> {$new}";
        }
    }

    /**
     * @param list<array<string, mixed>> $metaFields
     * @param array<string, array<string, mixed>> $baseColumns
     * @param list<array<string, mixed>> $ops
     * @param list<string> $logs
     * @param array<string, mixed> $opts
     */
    private function planOrphanDrops(string $tableName, string $entityName, array $currentSchema, array $metaFields, array $baseColumns, array &$ops, array &$logs, array $opts): void
    {
        $allowDrop = (bool) ($opts['allow_drop'] ?? $opts['prune'] ?? false);
        $known = array_merge(array_keys($baseColumns), array_column($metaFields, 'fieldname'));
        $known = array_fill_keys(array_map('strtolower', $known), true);

        foreach ($currentSchema as $colName => $actual) {
            if (isset($known[$colName])) {
                continue;
            }

            if (! $allowDrop) {
                $logs[] = "🗑 Phát hiện cột dư: {$colName} trên {$tableName} (cần --prune để xóa)";
                continue;
            }

            if ($this->isColumnReferenced($tableName, $colName)) {
                $logs[] = "🗑 Bỏ qua xóa cột {$colName}: còn bị index/FK tham chiếu trên {$tableName}";

                continue;
            }

            $ops[] = $this->makeOp('drop_column', $entityName, $tableName, column: $colName, severity: 'breaking', sql: "DROP COLUMN {$colName}");
            $logs[] = "🗑 Kế hoạch xóa cột dư: {$colName} trên {$tableName} (phá vỡ)";
        }
    }

    /**
     * @param list<array<string, mixed>> $metaFields
     * @param mixed $customAttributes
     * @param list<array<string, mixed>> $ops
     * @param list<string> $logs
     * @param array<string, mixed> $opts
     */
    private function planIndexes(string $tableName, string $entityName, array $metaFields, mixed $customAttributes, array &$ops, array &$logs, array $opts): void
    {
        $indexes = $this->normalizeIndexHints($customAttributes);
        $metaFieldnames = array_column($metaFields, 'fieldname');
        $existingIndexes = $this->existingIndexes($tableName);
        $normalizedEntity = TableNameResolver::normalizeIdentifier($entityName);
        $prefix = 'ix_' . $normalizedEntity . '_';
        $concurrent = (bool) $this->voltConfig()->schemaSyncConcurrentIndexCreate;

        $desiredNames = [];
        foreach ($indexes as $fieldname) {
            if (! in_array($fieldname, $metaFieldnames, true)) {
                continue;
            }

            $indexName = $prefix . $fieldname;
            $desiredNames[$indexName] = true;

            if (in_array($indexName, $existingIndexes, true)) {
                continue;
            }

            $ops[] = $this->makeOp('create_index', $entityName, $tableName, column: $fieldname, severity: 'safe', sql: "CREATE INDEX {$indexName}", extra: ['index_name' => $indexName, 'concurrent' => $concurrent]);
            $logs[] = "📇 Kế hoạch tạo index: {$indexName} trên {$tableName} ({$fieldname})";
        }

        // Xóa index dư (theo quy ước đặt tên) không còn khai báo — cần flag phá vỡ.
        if (! ($opts['allow_drop'] ?? $opts['prune'] ?? false)) {
            return;
        }

        foreach ($existingIndexes as $name) {
            if (! str_starts_with($name, $prefix)) {
                continue;
            }
            if (isset($desiredNames[$name])) {
                continue;
            }
            $ops[] = $this->makeOp('drop_index', $entityName, $tableName, severity: 'breaking', sql: "CREATE INDEX {$name}", extra: ['index_name' => $name, 'concurrent' => $concurrent]);
            $logs[] = "🗑 Kế hoạch xóa index dư: {$name} trên {$tableName} (phá vỡ)";
        }
    }

    /** @return list<string> */
    private function normalizeIndexHints(mixed $customAttributes): array
    {
        if (is_string($customAttributes)) {
            $customAttributes = json_decode($customAttributes, true) ?: [];
        }

        if (! is_array($customAttributes)) {
            return [];
        }

        $hints = $customAttributes['indexes'] ?? [];

        $result = [];
        foreach ((array) $hints as $hint) {
            if (is_string($hint)) {
                $result[] = $hint;
            } elseif (is_array($hint)) {
                $fieldname = mb_trim((string) ($hint['field'] ?? $hint['fieldname'] ?? ''));
                if ($fieldname !== '') {
                    $result[] = $fieldname;
                }
            }
        }

        return array_values(array_unique($result));
    }

    /** @return list<string> */
    private function childEntityNames(array $metaFields): array
    {
        $names = [];
        foreach ($metaFields as $field) {
            if (! $field['is_child_table']) {
                continue;
            }
            $name = $this->parseChildEntityName((string) ($field['options'] ?? ''));
            if ($name !== '' && $this->entityExists($name)) {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /** @return array{istable: int, custom_attributes: mixed} */
    private function getEntityMeta(string $entityName): array
    {
        $row = $this->db->table('sys_entity')
            ->select('istable, custom_attributes')
            ->where('name', TableNameResolver::normalizeIdentifier($entityName))
            ->get()
            ->getRowArray();

        return [
            'istable'           => is_array($row) ? (int) ($row['istable'] ?? 0) : 0,
            'custom_attributes' => is_array($row) ? ($row['custom_attributes'] ?? []) : [],
        ];
    }

    private function entityExists(string $entityName): bool
    {
        return $this->db->table('sys_entity')
            ->where('name', TableNameResolver::normalizeIdentifier($entityName))
            ->countAllResults() > 0;
    }

    /**
     * @param array<string, mixed> $desired
     * @param array<string, mixed> $actual
     */
    private function isSafeWiden(array $desired, array $actual): bool
    {
        $desiredType = (string) ($desired['type'] ?? '');
        $actualType  = strtolower((string) ($actual['type'] ?? ''));

        if ($desiredType === 'VARCHAR' && $actualType === 'character varying') {
            $desiredLen = (int) ($desired['constraint'] ?? 255);

            return $desiredLen > (int) ($actual['length'] ?? 0);
        }

        if ($desiredType === 'NUMERIC' && $actualType === 'numeric') {
            $desiredPrecision = (int) $this->numericPart($desired['constraint'] ?? '0', 0);
            $desiredScale     = (int) $this->numericPart($desired['constraint'] ?? '0', 1);
            $actualPrecision  = (int) ($actual['precision'] ?? 0);
            $actualScale      = (int) ($actual['scale'] ?? 0);

            return $desiredPrecision >= $actualPrecision && $desiredScale >= $actualScale;
        }

        return false;
    }

    /** Lấy phần precision (pos=0) hoặc scale (pos=1) từ constraint NUMERIC "18, 4". */
    private function numericPart(mixed $constraint, int $pos): int
    {
        $normalized = preg_replace('/[^0-9]/', ':', (string) $constraint);
        $parts = array_values(array_filter(explode(':', $normalized), static fn (string $p): bool => $p !== ''));

        return (int) ($parts[$pos] ?? 0);
    }

    /** @param array<string, mixed> $actual */
    private function canonicalActual(array $actual): string
    {
        return match (strtolower((string) ($actual['type'] ?? ''))) {
            'character varying' => 'varchar:' . (int) ($actual['length'] ?? 0),
            'character'         => 'char:' . (int) ($actual['length'] ?? 0),
            'numeric'           => 'numeric:' . (int) ($actual['precision'] ?? 0) . ':' . (int) ($actual['scale'] ?? 0),
            'timestamp without time zone' => 'timestamp',
            'time without time zone'      => 'time',
            default             => strtolower((string) ($actual['type'] ?? '')),
        };
    }

    /** @param array<string, mixed> $forgeDef */
    private function canonicalType(array $forgeDef): string
    {
        return match (strtoupper((string) ($forgeDef['type'] ?? ''))) {
            'VARCHAR'  => 'varchar:' . (int) ($forgeDef['constraint'] ?? 0),
            'CHAR'     => 'char:' . (int) ($forgeDef['constraint'] ?? 0),
            'NUMERIC'  => $this->canonicalNumericConstraint($forgeDef['constraint'] ?? '0:0'),
            'TIMESTAMP' => 'timestamp',
            'TIME'     => 'time',
            default    => strtolower((string) ($forgeDef['type'] ?? '')),
        };
    }

    /** Chuẩn hóa constraint NUMERIC (VD "18, 4") về dạng "precision:scale" giống canonicalActual. */
    private function canonicalNumericConstraint(mixed $constraint): string
    {
        $constraint = preg_replace('/[^0-9]/', ':', (string) $constraint);
        $parts = array_values(array_filter(explode(':', $constraint), static fn (string $p): bool => $p !== ''));

        $precision = (int) ($parts[0] ?? 0);
        $scale     = (int) ($parts[1] ?? 0);

        return 'numeric:' . $precision . ':' . $scale;
    }

    /** @param array<string, mixed> $field */
    private function columnDefFromField(array $field): array
    {
        $def = $this->forgeType((string) $field['fieldtype'], $field['length'] !== null ? (int) $field['length'] : null);
        $def['null'] = (int) ($field['reqd'] ?? 0) !== 1;

        return $def;
    }

    /** @return array{type: string, constraint?: int|string} */
    private function forgeType(string $fieldType, ?int $length = null): array
    {
        return match ($fieldType) {
            'Input'               => ['type' => 'VARCHAR', 'constraint' => $length ?? 255],
            'Int'                 => ['type' => 'INTEGER'],
            'Float', 'Currency'   => ['type' => 'NUMERIC', 'constraint' => '18, 4'],
            'Data'                => ['type' => 'VARCHAR', 'constraint' => $length ?? 255],
            'Text', 'Code'        => ['type' => 'TEXT'],
            'Check'               => ['type' => 'SMALLINT'],
            'Date'                => ['type' => 'DATE'],
            'Datetime'            => ['type' => 'TIMESTAMP'],
            'Time'                => ['type' => 'TIME'],
            'Email'               => ['type' => 'VARCHAR', 'constraint' => 255],
            'Phone'               => ['type' => 'VARCHAR', 'constraint' => 32],
            'URL'                 => ['type' => 'VARCHAR', 'constraint' => 2048],
            'Password'            => ['type' => 'VARCHAR', 'constraint' => 255],
            'Select'              => ['type' => 'VARCHAR', 'constraint' => 255],
            'MultiSelect', 'JSON' => ['type' => 'JSONB'],
            'Link'                => ['type' => 'VARCHAR', 'constraint' => 100],
            'Table', 'Child Table (JSONB)' => ['type' => 'JSONB'],
            'Attach', 'Attach Image'       => ['type' => 'VARCHAR', 'constraint' => 100],
            default               => ['type' => 'TEXT'],
        };
    }

    /** @param array<string, mixed> $def */
    private function fieldDef(array $def): array
    {
        if (($def['default'] ?? null) === 'CURRENT_TIMESTAMP') {
            $def['default'] = new RawSql('CURRENT_TIMESTAMP');
        }

        return $def;
    }

    /** @return array<string, array<string, mixed>> */
    private function baseColumns(bool $isChild): array
    {
        $columns = [
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'docstatus'  => ['type' => 'SMALLINT', 'default' => 0],
            'owner'      => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'creation'   => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP'],
            'modified'   => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP'],
            'deleted_at' => ['type' => 'TIMESTAMP', 'null' => true],
        ];

        if ($isChild) {
            $columns['parent']     = ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false];
            $columns['parentfield'] = ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false];
            $columns['parenttype'] = ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false];
            $columns['idx']        = ['type' => 'INTEGER', 'default' => 0];
        } else {
            $columns['workflow_state'] = ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'Draft'];
            $columns['amended_from']   = ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true];
        }

        return $columns;
    }

    /**
     * Bốc cấu trúc vật lý từ information_schema.
     *
     * Raw query có lý do: CI4 getFieldData() không cung cấp is_nullable + constraint length.
     *
     * @return array<string, array<string, mixed>>
     */
    private function getPostgresSchema(string $tableName): array
    {
        $sql = 'SELECT column_name, data_type, character_maximum_length, is_nullable, numeric_precision, numeric_scale '
             . 'FROM information_schema.columns '
             . 'WHERE table_name = ?';

        $query = $this->db->query($sql, [strtolower($tableName)]);
        $result = $query->getResultArray();

        $schema = [];
        foreach ($result as $row) {
            $schema[$row['column_name']] = [
                'type'      => $row['data_type'],
                'length'    => $row['character_maximum_length'],
                'nullable'  => $row['is_nullable'] === 'YES',
                'precision' => $row['numeric_precision'],
                'scale'     => $row['numeric_scale'],
            ];
        }

        return $schema;
    }

    private function isColumnReferenced(string $tableName, string $columnName): bool
    {
        foreach ($this->db->getIndexData($tableName) ?: [] as $index) {
            if (in_array($columnName, (array) ($index->fields ?? []), true)) {
                return true;
            }
        }

        foreach ($this->db->getForeignKeyData($tableName) ?: [] as $fk) {
            if (in_array($columnName, (array) ($fk->fields ?? []), true)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function existingIndexes(string $tableName): array
    {
        $names = [];
        foreach ($this->db->getIndexData($tableName) ?: [] as $index) {
            $names[] = (string) ($index->name ?? '');
        }

        return $names;
    }

    /** @return list<string> Tên các constraint hiện có trên bảng. */
    private function existingConstraints(string $tableName): array
    {
        $sql = 'SELECT constraint_name FROM information_schema.table_constraints WHERE table_name = ?';
        $result = $this->db->query($sql, [strtolower($tableName)])->getResultArray();

        $names = [];
        foreach ($result as $row) {
            $name = (string) ($row['constraint_name'] ?? '');
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function tableHasRows(string $tableName): bool
    {
        try {
            $result = $this->db->query("SELECT 1 FROM {$tableName} LIMIT 1");

            return $result !== null && $result->getRow() !== null;
        } catch (Throwable) {
            return false;
        }
    }

    /** Map forge def type về kiểu PostgreSQL thực tế cho câu ALTER COLUMN TYPE. */
    private function pgType(array $def): string
    {
        $type = strtoupper((string) ($def['type'] ?? 'TEXT'));

        return match ($type) {
            'VARCHAR'  => 'VARCHAR(' . (int) ($def['constraint'] ?? 255) . ')',
            'CHAR'     => 'CHAR(' . (int) ($def['constraint'] ?? 255) . ')',
            'NUMERIC'  => 'NUMERIC(' . $this->pgNumericPrecision($def['constraint'] ?? '18, 4') . ')',
            'INTEGER'  => 'INTEGER',
            'SMALLINT' => 'SMALLINT',
            'BIGINT'   => 'BIGINT',
            'JSONB'    => 'JSONB',
            'TEXT'     => 'TEXT',
            'DATE'     => 'DATE',
            'TIMESTAMP'=> 'TIMESTAMP',
            'TIME'     => 'TIME',
            default    => 'TEXT',
        };
    }

    /** Trả về dạng "precision, scale" hợp lệ cho NUMERIC SQL. */
    private function pgNumericPrecision(mixed $constraint): string
    {
        $normalized = preg_replace('/[^0-9]/', ':', (string) $constraint);
        $parts = array_values(array_filter(explode(':', $normalized), static fn (string $p): bool => $p !== ''));

        $precision = (int) ($parts[0] ?? 18);
        $scale = (int) ($parts[1] ?? 4);

        return $precision . ', ' . $scale;
    }

    private function queue(): ?QueueDispatcher
    {
        return $this->queue ??= service('voltQueue');
    }

    private function voltConfig(): VoltConfig
    {
        if ($this->voltConfig === null) {
            try {
                $this->voltConfig = config(VoltConfig::class);
            } catch (Throwable) {
                $this->voltConfig = new VoltConfig();
            }
        }

        return $this->voltConfig;
    }

    /** @return bool TRUE khi môi trường hiện tại được coi là production. */
    public function isProductionEnv(): bool
    {
        $env = (string) (ENVIRONMENT ?? '');
        $production = array_filter(array_map('trim', explode(',', $this->voltConfig()->schemaSyncProductionEnvs)));

        return $production === [] || $env !== '' && in_array($env, $production, true);
    }

    private function withLockTimeout(callable $fn): void
    {
        $lockTimeoutMs = $this->voltConfig()->schemaSyncLockTimeoutMs;
        $statementTimeoutMs = $this->voltConfig()->schemaSyncStatementTimeoutMs;

        $this->db->transStart();

        try {
            if ($lockTimeoutMs > 0) {
                $this->db->query("SET LOCAL lock_timeout = '" . (int) $lockTimeoutMs . "'");
            }
            if ($statementTimeoutMs > 0) {
                $this->db->query("SET LOCAL statement_timeout = '" . (int) $statementTimeoutMs . "'");
            }
            $fn();
            $this->db->transComplete();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    private function parseChildEntityName(string $options): string
    {
        $parts = explode(':', $options);
        $name = mb_trim($parts[0]);
        $name = preg_replace('/[^a-zA-Z0-9_]/', '', $name) ?? '';
        $name = mb_strtolower($name);

        return $name !== '' ? $name : '';
    }

    private function sanitizeIdentifier(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_]+/', '_', mb_strtolower($name)) ?? 'idx';

        return mb_trim($name, '_') ?: 'idx';
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function makeOp(string $operation, string $entity, string $table, ?string $column = null, string $severity = 'safe', string $sql = '', array $extra = []): array
    {
        $op = array_merge([
            'operation' => $operation,
            'entity'    => $entity,
            'table'     => $table,
            'column'    => $column,
            'severity'  => $severity,
            'sql'       => $sql,
        ], $extra);

        $op['downtime'] ??= $this->downtimeFor($op);

        return $op;
    }

    /** Phân loại mức downtime của một operation (none|brief|full). */
    private function downtimeFor(array $op): string
    {
        $operation = (string) ($op['operation'] ?? '');
        $severity = (string) ($op['severity'] ?? 'safe');

        return match ($operation) {
            'create_table', 'add_column', 'backfill_data', 'create_index' => 'none',
            'rename_table', 'rename_column', 'set_not_null', 'add_constraint' => 'brief',
            'alter_column' => $severity === 'breaking' ? 'full' : 'brief',
            default        => 'full',
        };
    }
}
