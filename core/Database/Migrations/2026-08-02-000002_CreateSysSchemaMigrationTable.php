<?php

declare(strict_types=1);

namespace Volt\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSysSchemaMigrationTable extends Migration
{
    private const TABLE_LOG = 'sys_schema_migration';

    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'BIGSERIAL'],
            'entity'     => ['type' => 'VARCHAR', 'constraint' => 100],
            'table_name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'operation'  => ['type' => 'VARCHAR', 'constraint' => 50],
            'sql'        => ['type' => 'TEXT', 'null' => true],
            'dry_run'    => ['type' => 'SMALLINT', 'default' => 0],
            'created_by' => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'system'],
            'created_at' => ['type' => 'TIMESTAMP'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable(self::TABLE_LOG, true);

        $this->db->query('ALTER TABLE ' . self::TABLE_LOG . ' ALTER COLUMN created_at SET DEFAULT CURRENT_TIMESTAMP');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_sys_schema_migration_entity ON ' . self::TABLE_LOG . ' (entity)');
    }

    public function down()
    {
        $this->forge->dropTable(self::TABLE_LOG, true);
    }
}
