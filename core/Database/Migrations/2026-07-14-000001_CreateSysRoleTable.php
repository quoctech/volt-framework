<?php

declare(strict_types=1);

namespace Volt\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSysRoleTable extends Migration
{
    const T_ROLE = 'sys_role';

    public function up()
    {
        $this->forge->addField([
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'label'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'description' => ['type' => 'TEXT', 'null' => true],
            'is_system'   => ['type' => 'SMALLINT', 'default' => 0],
            'owner'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'created_at'  => ['type' => 'TIMESTAMP', 'null' => true],
            'updated_at'  => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addKey('name', true);
        $this->forge->createTable(self::T_ROLE, true);
    }

    public function down()
    {
        $this->forge->dropTable(self::T_ROLE, true);
    }
}
