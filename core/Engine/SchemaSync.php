<?php

declare(strict_types=1);

namespace Volt\Core\Engine;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Forge;
use CodeIgniter\Database\RawSql;
use Throwable;
use Volt\Core\Database\TableNameResolver;
use Volt\Core\Database\VoltDatabase;
use Volt\Core\Validation\MetadataValidator;

/**
 * Đồng bộ schema vật lý từ metadata. *
 * - Không phá hủy: thay đổi "phá vỡ" (đổi kiểu, xóa cột) chỉ nằm trong plan khi được bật flag.
 * - Dùng CI4 Forge cho mọi DDL; raw SQL chỉ dùng khi Forge không hỗ trợ
 *   (CREATE INDEX trên bảng đã tồn tại, RENAME COLUMN).
 * - Ghi mỗi thao tác đã apply vào sys_schema_migration.
 */
class SchemaSync
{
    private const TABLE_MIGRATION = 'sys_schema_migration';

    private readonly BaseConnection $db;
    private readonly MetadataValidator $validator;
    private ?QueueDispatcher $queue;

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
                $ops[] = $this->makeOp('add_column', $entityName, $tableName, column: $colName, severity: 'safe', sql: "ADD COLUMN {$colName}", extra: ['def' => $colDef]);
                $logs[] = "🛠️ Kế hoạch thêm base column: {$colName} vào {$tableName}";
            }

            $this->planRenames($tableName, $entityName, $currentSchema, $metaFields, $ops, $logs, $opts);

            foreach ($metaFields as $field) {
                if ($field['is_child_table']) {
                    continue;
                }

                $colName = $field['fieldname'];
                $def = $this->columnDefFromField($field);

                if (! isset($currentSchema[$colName])) {
                    $ops[] = $this->makeOp('add_column', $entityName, $tableName, column: $colName, severity: 'safe', sql: "ADD COLUMN {$colName}", extra: ['def' => $def]);
                    $logs[] = "🛠️ Phát hiện thiếu trường! Kế hoạch vá thêm cột: {$colName} vào {$tableName}";
                    continue;
                }

                $this->planTypeChange($tableName, $entityName, $field, $currentSchema[$colName], $ops, $logs, $opts);
            }

            $this->planOrphanDrops($tableName, $entityName, $currentSchema, $metaFields, $baseColumns, $ops, $logs, $opts);
        }

        $this->planIndexes($tableName, $entityName, $metaFields, $this->getEntityMeta($entityName)['custom_attributes'], $ops, $logs);

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
        foreach ($plan['plan'] as $op) {
            if (! $this->opAllowed($op, $opts)) {
                continue;
            }

            try {
                $this->applyOp($op);
                $this->logMigration($op);
            } catch (Throwable $e) {
                service('voltErrorLog')->logException($e, [
                    'entity'    => $op['entity'] ?? null,
                    'operation' => $op['operation'] ?? null,
                    'table'     => $op['table'] ?? null,
                ], 'schema_sync', 'schema_sync_apply_failed');
                throw $e;
            }
        }
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

        // Thao tác phá vỡ chỉ apply khi flag tương ứng được bật ở lúc plan.
        return match ($op['operation'] ?? '') {
            'drop_column'  => (bool) ($opts['allow_drop'] ?? $opts['prune'] ?? false),
            'alter_column' => (bool) ($opts['allow_type_change'] ?? false),
            default        => false,
        };
    }

    /**
     * @param array<string, mixed> $op
     */
    private function applyOp(array $op): void
    {
        $table = (string) $op['table'];

        match ($op['operation']) {
            'rename_table'   => (new Forge($this->db))->renameTable((string) $op['old_table'], $table),
            'create_table'   => $this->applyCreateTable($op),
            'add_column'     => (new Forge($this->db))->addColumn($table, [$op['column'] => $this->fieldDef($op['def'])]),
            'alter_column'   => $this->applyAlterColumn($op),
            'rename_column'  => $this->applyRenameColumn($op),
            'drop_column'    => $this->applyDropColumn($op),
            'create_index'   => $this->applyCreateIndex($op),
            default          => null,
        };
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
        $apply = function () use ($op): void {
            (new Forge($this->db))->modifyColumn((string) $op['table'], [$op['column'] => $this->fieldDef($op['def'])]);
        };

        if (($op['severity'] ?? '') === 'breaking') {
            $this->withLockTimeout($apply);
        } else {
            $apply();
        }
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
        $sql = 'CREATE INDEX IF NOT EXISTS ' . $this->db->escapeIdentifiers($this->sanitizeIdentifier((string) $op['index_name']))
             . ' ON ' . $this->db->escapeIdentifiers((string) $op['table'])
             . ' (' . $this->db->escapeIdentifiers((string) $op['column']) . ')';

        $this->db->query($sql);
    }

    /**
     * @param array<string, mixed> $op
     */
    private function logMigration(array $op): void
    {
        $this->db->table(self::TABLE_MIGRATION)->insert([
            'entity'     => (string) ($op['entity'] ?? ''),
            'table_name' => (string) ($op['table'] ?? ''),
            'operation'  => (string) ($op['operation'] ?? ''),
            'sql'        => (string) ($op['sql'] ?? ''),
            'dry_run'    => 0,
            'created_by' => 'system',
        ]);
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
            $ops[] = $this->makeOp('alter_column', $entityName, $tableName, column: $field['fieldname'], severity: 'breaking', sql: "ALTER COLUMN {$field['fieldname']}", extra: ['def' => $desired]);
            $logs[] = "⚠ Kế hoạch đổi kiểu cột: {$field['fieldname']} trên {$tableName} (phá vỡ)";
        } else {
            $logs[] = "⏭ Bỏ qua đổi kiểu cột: {$field['fieldname']} trên {$tableName} (cần --allow-type-change)";
        }
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
     */
    private function planIndexes(string $tableName, string $entityName, array $metaFields, mixed $customAttributes, array &$ops, array &$logs): void
    {
        $indexes = $this->normalizeIndexHints($customAttributes);
        $metaFieldnames = array_column($metaFields, 'fieldname');
        $existingIndexes = $this->existingIndexes($tableName);

        foreach ($indexes as $fieldname) {
            if (! in_array($fieldname, $metaFieldnames, true)) {
                continue;
            }

            $indexName = 'ix_' . TableNameResolver::normalizeIdentifier($entityName) . '_' . $fieldname;

            if (in_array($indexName, $existingIndexes, true)) {
                continue;
            }

            $ops[] = $this->makeOp('create_index', $entityName, $tableName, column: $fieldname, severity: 'safe', sql: "CREATE INDEX {$indexName}", extra: ['index_name' => $indexName]);
            $logs[] = "📇 Kế hoạch tạo index: {$indexName} trên {$tableName} ({$fieldname})";
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

    private function queue(): ?QueueDispatcher
    {
        return $this->queue ??= service('voltQueue');
    }

    private function withLockTimeout(callable $fn): void
    {
        $this->db->transStart();

        try {
            $this->db->query("SET LOCAL lock_timeout = '2000'");
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
        return array_merge([
            'operation' => $operation,
            'entity'    => $entity,
            'table'     => $table,
            'column'    => $column,
            'severity'  => $severity,
            'sql'       => $sql,
        ], $extra);
    }
}
