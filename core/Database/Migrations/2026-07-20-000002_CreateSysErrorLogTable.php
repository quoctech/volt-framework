<?php

declare(strict_types=1);

namespace Volt\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateSysErrorLogTable extends Migration
{
    private const TABLE = 'sys_error_log';

    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGSERIAL'],
            'level' => ['type' => 'VARCHAR', 'constraint' => 20],
            'channel' => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'system'],
            'code' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'message' => ['type' => 'TEXT'],
            'context' => ['type' => 'JSONB', 'default' => '{}'],
            'file' => ['type' => 'TEXT', 'null' => true],
            'line' => ['type' => 'INTEGER', 'null' => true],
            'trace' => ['type' => 'TEXT', 'null' => true],
            'request_uri' => ['type' => 'TEXT', 'null' => true],
            'request_method' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'user_agent' => ['type' => 'TEXT', 'null' => true],
            'actor' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'created_at' => ['type' => 'TIMESTAMP', 'null' => false],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->createTable(self::TABLE, true);

        $this->db->query('ALTER TABLE ' . self::TABLE . ' ALTER COLUMN created_at SET DEFAULT CURRENT_TIMESTAMP');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_sys_error_log_level_created ON ' . self::TABLE . ' (level, created_at DESC)');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_sys_error_log_channel_created ON ' . self::TABLE . ' (channel, created_at DESC)');
    }

    public function down()
    {
        $this->forge->dropTable(self::TABLE, true);
    }
}
