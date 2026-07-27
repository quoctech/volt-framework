<?php

declare(strict_types=1);

namespace Volt\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSysTenantTable extends Migration
{
    const T_TENANT = 'sys_tenant';

    public function up()
    {
        if ($this->db->tableExists(self::T_TENANT)) {
            return;
        }

        $this->forge->addField([
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'label'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'domain'      => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'db_host'     => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => 'localhost'],
            'db_port'     => ['type' => 'INTEGER', 'default' => 5432],
            'db_name'     => ['type' => 'VARCHAR', 'constraint' => 255],
            'db_username' => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'volt_admin'],
            'db_password' => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'is_active'   => ['type' => 'SMALLINT', 'default' => 1],
            'created_at'  => ['type' => 'TIMESTAMP', 'null' => true],
            'updated_at'  => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addKey('name', true);
        $this->forge->createTable(self::T_TENANT, true);
    }

    public function down()
    {
        $this->forge->dropTable(self::T_TENANT, true);
    }
}
