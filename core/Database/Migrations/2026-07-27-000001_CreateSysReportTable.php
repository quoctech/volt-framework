<?php

declare(strict_types=1);

namespace Volt\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSysReportTable extends Migration
{
    private const TABLE = 'sys_report';

    public function up()
    {
        if ($this->db->tableExists(self::TABLE)) {
            return;
        }

        $sql = 'CREATE TABLE ' . self::TABLE . ' ('
            . 'name VARCHAR(140) PRIMARY KEY,'
            . 'module VARCHAR(50) NOT NULL,'
            . 'label VARCHAR(255) NOT NULL,'
            . 'description TEXT DEFAULT \'\','
            . 'report_type VARCHAR(20) NOT NULL DEFAULT \'query\','
            . 'is_active SMALLINT DEFAULT 1,'
            . 'query JSONB DEFAULT \'{}\'::jsonb,'
            . 'columns JSONB DEFAULT \'[]\'::jsonb,'
            . 'roles JSONB DEFAULT \'[]\'::jsonb,'
            . 'charts JSONB DEFAULT \'[]\'::jsonb,'
            . 'owner VARCHAR(100) DEFAULT \'\','
            . 'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,'
            . 'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
            . ')';

        $this->db->query($sql);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS ' . self::TABLE);
    }
}
