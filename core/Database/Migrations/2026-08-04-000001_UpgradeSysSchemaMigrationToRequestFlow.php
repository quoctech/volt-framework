<?php

declare(strict_types=1);

namespace Volt\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Nâng cấp hạ tầng migration:
 * - Bảng sys_migration_request: tiêu đề cho mỗi "migration plan" (plan → approve → apply → rollback).
 * - Bảng sys_schema_migration: thêm cột trạng thái, migration_id, inverse_sql, severity, downtime.
 */
class UpgradeSysSchemaMigrationToRequestFlow extends Migration
{
    private const TABLE_REQUEST = 'sys_migration_request';
    private const TABLE_LOG = 'sys_schema_migration';

    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'BIGSERIAL'],
            'entity'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'status'       => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'pending_approval'],
            'summary'      => ['type' => 'JSONB', 'null' => false, 'default' => '{}'],
            'requested_by' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'approved_by'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'applied_by'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'approved_at'  => ['type' => 'TIMESTAMP', 'null' => true],
            'applied_at'   => ['type' => 'TIMESTAMP', 'null' => true],
            'created_at'   => ['type' => 'TIMESTAMP'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable(self::TABLE_REQUEST, true);

        $this->db->query('ALTER TABLE ' . self::TABLE_REQUEST . ' ALTER COLUMN created_at SET DEFAULT CURRENT_TIMESTAMP');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_sys_migration_request_entity ON ' . self::TABLE_REQUEST . ' (entity)');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_sys_migration_request_status ON ' . self::TABLE_REQUEST . ' (status)');

        $this->db->query('ALTER TABLE ' . self::TABLE_LOG . ' ADD COLUMN IF NOT EXISTS migration_id BIGINT NULL');
        $this->db->query('ALTER TABLE ' . self::TABLE_LOG . ' ADD COLUMN IF NOT EXISTS status VARCHAR(30) NOT NULL DEFAULT \'applied\'');
        $this->db->query('ALTER TABLE ' . self::TABLE_LOG . ' ADD COLUMN IF NOT EXISTS severity VARCHAR(20) NOT NULL DEFAULT \'safe\'');
        $this->db->query('ALTER TABLE ' . self::TABLE_LOG . ' ADD COLUMN IF NOT EXISTS downtime VARCHAR(20) NOT NULL DEFAULT \'none\'');
        $this->db->query('ALTER TABLE ' . self::TABLE_LOG . ' ADD COLUMN IF NOT EXISTS inverse_sql TEXT NULL');
        $this->db->query('ALTER TABLE ' . self::TABLE_LOG . ' ADD COLUMN IF NOT EXISTS applied_at TIMESTAMP NULL');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_sys_schema_migration_status ON ' . self::TABLE_LOG . ' (status)');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_sys_schema_migration_migration_id ON ' . self::TABLE_LOG . ' (migration_id)');
    }

    public function down()
    {
        $this->forge->dropTable(self::TABLE_REQUEST, true);

        $this->db->query('ALTER TABLE ' . self::TABLE_LOG . ' DROP COLUMN IF EXISTS applied_at');
        $this->db->query('ALTER TABLE ' . self::TABLE_LOG . ' DROP COLUMN IF EXISTS inverse_sql');
        $this->db->query('ALTER TABLE ' . self::TABLE_LOG . ' DROP COLUMN IF EXISTS downtime');
        $this->db->query('ALTER TABLE ' . self::TABLE_LOG . ' DROP COLUMN IF EXISTS severity');
        $this->db->query('ALTER TABLE ' . self::TABLE_LOG . ' DROP COLUMN IF EXISTS status');
        $this->db->query('ALTER TABLE ' . self::TABLE_LOG . ' DROP COLUMN IF EXISTS migration_id');
    }
}
