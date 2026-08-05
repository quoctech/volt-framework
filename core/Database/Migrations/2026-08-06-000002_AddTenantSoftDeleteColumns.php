<?php

declare(strict_types=1);

namespace Volt\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTenantSoftDeleteColumns extends Migration
{
    public function up()
    {
        $fields = [
            'deleted_at' => ['type' => 'TIMESTAMP', 'null' => true],
            'deleted_by' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'purge_at'   => ['type' => 'TIMESTAMP', 'null' => true],
        ];

        foreach ($fields as $name => $def) {
            if (! $this->db->fieldExists($name, 'sys_tenant')) {
                $this->forge->addColumn('sys_tenant', [$name => $def]);
            }
        }
    }

    public function down()
    {
        foreach (['deleted_at', 'deleted_by', 'purge_at'] as $name) {
            if ($this->db->fieldExists($name, 'sys_tenant')) {
                $this->forge->dropColumn('sys_tenant', $name);
            }
        }
    }
}
