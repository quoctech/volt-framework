<?php

declare(strict_types=1);

namespace Volt\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSysAwesomeBarTable extends Migration
{
    const T_AWESOME_BAR = 'sys_awesome_bar';

    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'SERIAL'],
            'item_type'   => ['type' => 'VARCHAR', 'constraint' => 50],
            'item_name'   => ['type' => 'VARCHAR', 'constraint' => 100],
            'label'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'description' => ['type' => 'TEXT', 'null' => true],
            'route'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'module'      => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'is_core'     => ['type' => 'SMALLINT', 'default' => 0],
            'owner'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'created_at'  => ['type' => 'TIMESTAMP', 'null' => true],
            'updated_at'  => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable(self::T_AWESOME_BAR, true);

        $this->db->query('CREATE INDEX IF NOT EXISTS idx_awesome_item_type ON ' . self::T_AWESOME_BAR . ' (item_type)');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_awesome_item_name ON ' . self::T_AWESOME_BAR . ' (item_name)');
    }

    public function down()
    {
        $this->forge->dropTable(self::T_AWESOME_BAR, true);
    }
}
