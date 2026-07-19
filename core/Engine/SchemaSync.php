<?php

declare(strict_types=1);

namespace Volt\Core\Engine;

use CodeIgniter\Database\BaseConnection;
use Volt\Core\Database\TableNameResolver;
use Volt\Core\Database\VoltDatabase;
use Volt\Core\Validation\MetadataValidator;

class SchemaSync
{
    private const CORE_COLUMNS = [
        "name VARCHAR(100) PRIMARY KEY",
        "docstatus SMALLINT DEFAULT 0",
        "owner VARCHAR(100) NOT NULL",
        "creation TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP",
        "modified TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP",
    ];

    private const CHILD_COLUMNS = [
        "name VARCHAR(100) PRIMARY KEY",
        "parent VARCHAR(100) NOT NULL",
        "parentfield VARCHAR(100) NOT NULL",
        "parenttype VARCHAR(100) NOT NULL",
        "idx INTEGER DEFAULT 0",
        "owner VARCHAR(100) NOT NULL",
        "creation TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP",
        "modified TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP",
    ];

    protected BaseConnection $db;
    protected MetadataValidator $validator;

    public function __construct()
    {
        $this->db = VoltDatabase::connection();
        $this->validator = new MetadataValidator();
    }

    /**
     * Bốc cấu trúc vật lý thực tế từ Postgres information_schema lên RAM
     */
    private function getPostgresSchema(string $tableName): array
    {
        $sql = "SELECT column_name, data_type, character_maximum_length, is_nullable 
                FROM information_schema.columns 
                WHERE table_name = ?";
        
        $query = $this->db->query($sql, [strtolower($tableName)]);
        $result = $query->getResultArray();

        $schema = [];
        foreach ($result as $row) {
            $schema[$row['column_name']] = [
                'type'     => $row['data_type'],
                'length'   => $row['character_maximum_length'],
                'nullable' => $row['is_nullable'] === 'YES'
            ];
        }
        return $schema;
    }

    /**
     * Map kiểu dữ liệu logic của Volt sang kiểu vật lý chuẩn Postgres (Sạch, không rác)
     */
    private function mapToPostgresType(string $fieldType, ?int $length = null): string
    {
        return match ($fieldType) {
            'Input'      => 'VARCHAR(' . ($length ?? 255) . ')',
            'Int'        => 'INTEGER',
            'Float'      => 'NUMERIC(18, 4)',
            'Currency'   => 'NUMERIC(18, 4)',
            'Data'       => 'VARCHAR(' . ($length ?? 255) . ')',
            'Text'       => 'TEXT',
            'Check'      => 'SMALLINT',
            'Date'       => 'DATE',
            'Datetime'   => 'TIMESTAMP WITHOUT TIME ZONE',
            'Time'       => 'TIME WITHOUT TIME ZONE',
            'Email'      => 'VARCHAR(255)',
            'Phone'      => 'VARCHAR(32)',
            'URL'        => 'VARCHAR(2048)',
            'Password'   => 'VARCHAR(255)',
            'Select'     => 'VARCHAR(255)',
            'MultiSelect' => 'JSONB',
            'JSON'       => 'JSONB',
            'Link'       => 'VARCHAR(100)',
            'Table'              => 'JSONB',
            'Child Table (JSONB)' => 'JSONB',
            default              => 'TEXT'
        };
    }

    /**
     * Thuật toán tối thượng: Tính toán độ lệch Delta và tự động rèn bảng vật lý
     */
    public function syncEntity(string $entityName): array
    {
        $entityName = $this->validator->assertEntityName($entityName);

        // Kiểm tra xem entity có phải child table không
        $isChild = $this->isChildEntity($entityName);

        return $this->doSyncEntity($entityName, $isChild);
    }

    private function isChildEntity(string $entityName): bool
    {
        $row = $this->db->table('sys_entity')
            ->select('istable')
            ->where('name', $this->normalizeEntityName($entityName))
            ->get()
            ->getRowArray();

        return is_array($row) && ((int) ($row['istable'] ?? 0)) === 1;
    }

    private function doSyncEntity(string $entityName, bool $isChild): array
    {
        $normalizedName = $this->normalizeEntityName($entityName);
        $tableName = TableNameResolver::entity($entityName);
        $legacyTableName = TableNameResolver::legacyEntity($entityName);
        
        // 1. Đọc Metadata gốc của trường từ sys_entity_field
        $metaFields = $this->db->table('sys_entity_field')
                               ->where('parent', $normalizedName)
                               ->orderBy('idx', 'ASC')
                               ->get()
                               ->getResultArray();

        $metaFields = array_map(fn (array $field): array => $this->validator->normalizeFieldRow($field), $metaFields);

        if (empty($metaFields)) {
            return ['status' => 'error', 'message' => "Metadata trống cho Entity: {$entityName}"];
        }

        // 2. Lấy cấu trúc thực tế đang có dưới Postgres
        $logs = [];
        $currentSchema = $this->getPostgresSchema($tableName);

        if ($currentSchema === [] && $legacyTableName !== '' && $legacyTableName !== $tableName) {
            $legacySchema = $this->getPostgresSchema($legacyTableName);
            if ($legacySchema !== []) {
                $this->db->query("ALTER TABLE {$legacyTableName} RENAME TO {$tableName}");
                $logs[] = "🔁 Đã đổi tên bảng legacy {$legacyTableName} -> {$tableName}";
                $currentSchema = $this->getPostgresSchema($tableName);
            }
        }

        $baseColumns = $isChild ? self::CHILD_COLUMNS : self::CORE_COLUMNS;

        // 3. KỊCH BẢN A: Bảng chưa tồn tại -> CREATE TABLE mới tinh
        if (empty($currentSchema)) {
            $columnsSql = $baseColumns;

            foreach ($metaFields as $field) {
                if ($field['fieldtype'] === 'Table' && str_contains($field['options'] ?? '', 'separate')) {
                    continue; 
                }

                $pgType = $this->mapToPostgresType($field['fieldtype'], $field['length']);
                $nullable = $field['reqd'] == 1 ? 'NOT NULL' : 'NULL';
                $logs[] = "Rèn mới cột: {$field['fieldname']} ({$pgType})";
                $columnsSql[] = "{$field['fieldname']} {$pgType} {$nullable}";
            }

            $createSql = "CREATE TABLE {$tableName} (" . implode(", ", $columnsSql) . ")";
            $this->db->query($createSql);
            $logs[] = "➔ ĐÃ RÈN THÀNH CÔNG BẢNG VẬT LÝ: {$tableName}";

        } else {
            // 4. KỊCH BẢN B: Bảng đã tồn tại -> Kiểm tra tính toán DELTA để ALTER TABLE vá cột thiếu

            // Với child table, đảm bảo các cột parent/parentfield/parenttype/idx tồn tại
            if ($isChild) {
                $childRefColumns = [
                    'parent'     => 'VARCHAR(100) NOT NULL',
                    'parentfield' => 'VARCHAR(100) NOT NULL',
                    'parenttype' => 'VARCHAR(100) NOT NULL',
                    'idx'        => 'INTEGER DEFAULT 0',
                ];

                foreach ($childRefColumns as $colName => $colDef) {
                    if (isset($currentSchema[$colName])) {
                        continue;
                    }

                    $alterSql = "ALTER TABLE {$tableName} ADD COLUMN {$colName} {$colDef}";
                    $this->db->query($alterSql);
                    $logs[] = "🛠️ Đã thêm cột child table: {$colName} vào {$tableName}";
                }

                // Refresh schema sau khi thêm cột child
                $currentSchema = $this->getPostgresSchema($tableName);
            }

            foreach ($metaFields as $field) {
                if ($field['fieldtype'] === 'Table' && str_contains($field['options'] ?? '', 'separate')) {
                    continue;
                }

                if (isset($currentSchema[strtolower($field['fieldname'])])) {
                    continue;
                }

                $pgType = $this->mapToPostgresType($field['fieldtype'], $field['length']);
                $nullable = $field['reqd'] == 1 ? 'NOT NULL' : 'NULL';

                $alterSql = "ALTER TABLE {$tableName} ADD COLUMN {$field['fieldname']} {$pgType} {$nullable}";
                $this->db->query($alterSql);
                $logs[] = "🛠️ Phát hiện thiếu trường! Đã tự động vá thêm cột: {$field['fieldname']} vào bảng {$tableName}";
            }
        }

        // 5. Nếu không phải child table, quét Table:separate fields và sync child entities
        if (! $isChild) {
            $childEntityNames = [];
            foreach ($metaFields as $field) {
                if ($field['fieldtype'] !== 'Table' || ! str_contains($field['options'] ?? '', 'separate')) {
                    continue;
                }

                $childName = $this->parseChildEntityName($field['options'] ?? '');
                if ($childName !== '' && $this->entityExists($childName)) {
                    $childEntityNames[] = $childName;
                }
            }

            $childEntityNames = array_unique($childEntityNames);
            foreach ($childEntityNames as $childName) {
                $childResult = $this->doSyncEntity($childName, true);
                $logs = array_merge($logs, $childResult['logs'] ?? []);
                $logs[] = "🔗 Đã đồng bộ child table entity: {$childName}";
            }
        }

        return ['status' => 'success', 'logs' => $logs];
    }

    private function parseChildEntityName(string $options): string
    {
        $parts = explode(':', $options);
        $name = trim($parts[0]);

        $name = preg_replace('/[^a-zA-Z0-9_]/', '', $name) ?? '';

        return $name !== '' ? $name : '';
    }

    private function entityExists(string $entityName): bool
    {
        return $this->db->table('sys_entity')
            ->where('name', $this->normalizeEntityName($entityName))
            ->countAllResults() > 0;
    }

    /**
     * Convert entity name to snake_case for consistent DB queries.
     */
    private function normalizeEntityName(string $name): string
    {
        $name = preg_replace('/(?<!^)[A-Z]/', '_$0', $name) ?? $name;
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9_]+/', '_', $name) ?? '';
        $name = preg_replace('/_+/', '_', $name) ?? '';
        return trim($name, '_');
    }
}
