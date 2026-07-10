<?php

declare(strict_types=1);

namespace Volt\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSysModuleTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'label'       => ['type' => 'VARCHAR', 'constraint' => 150],
            'namespace'   => ['type' => 'VARCHAR', 'constraint' => 255],
            'module_path' => ['type' => 'VARCHAR', 'constraint' => 255],
            'is_active'   => ['type' => 'SMALLINT', 'default' => 1],
            'created_at'  => ['type' => 'TIMESTAMP', 'null' => true],
            'updated_at'  => ['type' => 'TIMESTAMP', 'null' => true],
        ]);

        $this->forge->addKey('name', true);
        $this->forge->createTable('sys_module', true);

        $this->db->query("ALTER TABLE sys_module ALTER COLUMN created_at SET DEFAULT CURRENT_TIMESTAMP");
        $this->db->query("ALTER TABLE sys_module ALTER COLUMN updated_at SET DEFAULT CURRENT_TIMESTAMP");
    }

    public function down()
    {
        $this->forge->dropTable('sys_module', true);
    }
}
