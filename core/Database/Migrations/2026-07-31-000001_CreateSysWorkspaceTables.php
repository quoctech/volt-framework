<?php

declare(strict_types=1);

namespace Volt\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateSysWorkspaceTables extends Migration
{
    private const T_WORKSPACE = 'sys_workspace';
    private const T_BLOCK     = 'sys_workspace_block';

    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'SERIAL'],
            'user_name'   => ['type' => 'VARCHAR', 'constraint' => 100],
            'title'       => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'My Workspace'],
            'columns'     => ['type' => 'SMALLINT', 'default' => 3],
            'is_active'   => ['type' => 'SMALLINT', 'default' => 1],
            'created_at'  => ['type' => 'TIMESTAMP', 'null' => false],
            'updated_at'  => ['type' => 'TIMESTAMP', 'null' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable(self::T_WORKSPACE, true);

        $this->db->query('ALTER TABLE ' . self::T_WORKSPACE . ' ALTER COLUMN created_at SET DEFAULT CURRENT_TIMESTAMP');
        $this->db->query('ALTER TABLE ' . self::T_WORKSPACE . ' ALTER COLUMN updated_at SET DEFAULT CURRENT_TIMESTAMP');
        $this->db->query('CREATE UNIQUE INDEX IF NOT EXISTS uq_sys_workspace_user ON ' . self::T_WORKSPACE . ' (user_name)');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_sys_workspace_active ON ' . self::T_WORKSPACE . ' (is_active)');

        $this->forge->addField([
            'id'            => ['type' => 'SERIAL'],
            'workspace_id'  => ['type' => 'INTEGER'],
            'block_type'    => ['type' => 'VARCHAR', 'constraint' => 20],
            'title'         => ['type' => 'VARCHAR', 'constraint' => 255],
            'data'          => ['type' => 'JSONB', 'default' => '{}'],
            'size'          => ['type' => 'SMALLINT', 'default' => 1],
            'sort'          => ['type' => 'INTEGER', 'default' => 0],
            'is_visible'    => ['type' => 'SMALLINT', 'default' => 1],
            'created_at'    => ['type' => 'TIMESTAMP', 'null' => false],
            'updated_at'    => ['type' => 'TIMESTAMP', 'null' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable(self::T_BLOCK, true);

        $this->db->query('ALTER TABLE ' . self::T_BLOCK . ' ALTER COLUMN created_at SET DEFAULT CURRENT_TIMESTAMP');
        $this->db->query('ALTER TABLE ' . self::T_BLOCK . ' ALTER COLUMN updated_at SET DEFAULT CURRENT_TIMESTAMP');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_sys_workspace_block_ws ON ' . self::T_BLOCK . ' (workspace_id, sort)');
    }

    public function down()
    {
        $this->forge->dropTable(self::T_BLOCK, true);
        $this->forge->dropTable(self::T_WORKSPACE, true);
    }
}
