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
    public function getPostgresSchema(string $tableName): array
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
    public function mapToPostgresType(string $fieldType, ?int $length = null): string
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
            'Table'      => 'JSONB',
            default      => 'TEXT'
        };
    }

    /**
     * Thuật toán tối thượng: Tính toán độ lệch Delta và tự động rèn bảng vật lý
     */
    public function syncEntity(string $entityName): array
    {
        $entityName = $this->validator->assertEntityName($entityName);
        $tableName = TableNameResolver::entity($entityName);
        $legacyTableName = TableNameResolver::legacyEntity($entityName);
        
        // 1. Đọc Metadata gốc của trường từ sys_entity_field
        $metaFields = $this->db->table('sys_entity_field')
                               ->where('parent', $entityName)
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
                // Tự nâng cấp bảng cũ sang chuẩn tab_ để giữ nguyên dữ liệu khi đổi quy ước đặt tên.
                $this->db->query("ALTER TABLE {$legacyTableName} RENAME TO {$tableName}");
                $logs[] = "🔁 Đã đổi tên bảng legacy {$legacyTableName} -> {$tableName}";
                $currentSchema = $this->getPostgresSchema($tableName);
            }
        }

        // 3. KỊCH BẢN A: Bảng chưa tồn tại -> CREATE TABLE mới tinh
        if (empty($currentSchema)) {
            $columnsSql = self::CORE_COLUMNS;

            foreach ($metaFields as $field) {
                // Chặn cứng: Nếu trường thuộc kiểu Table tách riêng (separate) thì bảng cha không tạo cột vật lý
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
            foreach ($metaFields as $field) {
                if ($field['fieldtype'] === 'Table' && str_contains($field['options'] ?? '', 'separate')) {
                    continue; 
                }

                // Nếu trong Metadata cấu hình có định nghĩa trường mà dưới Postgres chưa có cột vật lý tương ứng
                if (!isset($currentSchema[strtolower($field['fieldname'])])) {
                    $pgType = $this->mapToPostgresType($field['fieldtype'], $field['length']);
                    $nullable = $field['reqd'] == 1 ? 'NOT NULL' : 'NULL';
                    
                    $alterSql = "ALTER TABLE {$tableName} ADD COLUMN {$field['fieldname']} {$pgType} {$nullable}";
                    $this->db->query($alterSql);
                    $logs[] = "🛠️ Phát hiện thiếu trường! Đã tự động vá thêm cột: {$field['fieldname']} vào bảng {$tableName}";
                }
            }
        }

        return ['status' => 'success', 'logs' => $logs];
    }
}
