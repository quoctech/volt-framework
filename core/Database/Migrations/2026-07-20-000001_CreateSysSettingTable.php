<?php

declare(strict_types=1);

namespace Volt\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSysSettingTable extends Migration
{
    const T_SETTING = 'sys_setting';

    public function up()
    {
        $this->forge->addField([
            'key'         => ['type' => 'VARCHAR', 'constraint' => 100],
            'value'       => ['type' => 'TEXT', 'null' => true],
            'type'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'string'],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'updated_at'  => ['type' => 'TIMESTAMP'],
        ]);
        $this->forge->addKey('key', true);
        $this->forge->createTable(self::T_SETTING, true);

        $this->db->query('ALTER TABLE ' . self::T_SETTING . ' ALTER COLUMN updated_at SET DEFAULT CURRENT_TIMESTAMP');

        // Seed defaults
        $now = date('Y-m-d H:i:s');
        $this->db->table(self::T_SETTING)->insertBatch([
            [
                'key'         => 'language',
                'value'       => 'en',
                'type'        => 'string',
                'description' => 'System interface language (en, vi, ...)',
                'updated_at'  => $now,
            ],
            [
                'key'         => 'timezone',
                'value'       => 'UTC',
                'type'        => 'string',
                'description' => 'System timezone (e.g. UTC, Asia/Ho_Chi_Minh)',
                'updated_at'  => $now,
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropTable(self::T_SETTING, true);
    }
}
