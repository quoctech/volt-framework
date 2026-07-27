<?php

declare(strict_types=1);

namespace Volt\Core\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateSysReportTable extends Migration
{
    private const TABLE = 'sys_report';

    public function up()
    {
        if ($this->db->tableExists(self::TABLE)) {
            return;
        }

        $this->forge->addField([
            'name'        => ['type' => 'VARCHAR', 'constraint' => 140],
            'module'      => ['type' => 'VARCHAR', 'constraint' => 50],
            'label'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'description' => ['type' => 'TEXT', 'default' => ''],
            'report_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'query'],
            'is_active'   => ['type' => 'SMALLINT', 'default' => 1],
            'query'       => ['type' => 'JSONB', 'default' => '{}', 'null' => true],
            'columns'     => ['type' => 'JSONB', 'default' => '[]', 'null' => true],
            'roles'       => ['type' => 'JSONB', 'default' => '[]', 'null' => true],
            'charts'      => ['type' => 'JSONB', 'default' => '[]', 'null' => true],
            'owner'       => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => ''],
            'created_at'  => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP'), 'null' => true],
            'updated_at'  => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP'), 'null' => true],
        ]);

        $this->forge->addKey('name', true);
        $this->forge->createTable(self::TABLE);
    }

    public function down()
    {
        $this->forge->dropTable(self::TABLE, true);
    }
}
