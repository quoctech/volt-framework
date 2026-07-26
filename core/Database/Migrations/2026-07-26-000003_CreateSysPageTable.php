<?php

declare(strict_types=1);

namespace Volt\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSysPageTable extends Migration
{
    private const TABLE = 'sys_page';

    public function up()
    {
        if ($this->db->tableExists(self::TABLE)) {
            return;
        }

        $sql = 'CREATE TABLE ' . self::TABLE . ' ('
            . 'name VARCHAR(100) PRIMARY KEY,'
            . 'module VARCHAR(50) NOT NULL,'
            . 'label VARCHAR(255) NOT NULL,'
            . 'icon VARCHAR(100) DEFAULT \'\','
            . 'route VARCHAR(100) UNIQUE NOT NULL,'
            . 'html_content TEXT DEFAULT \'\','
            . 'css_content TEXT DEFAULT \'\','
            . 'js_content TEXT DEFAULT \'\','
            . 'roles JSONB DEFAULT \'[]\'::jsonb,'
            . 'is_active SMALLINT DEFAULT 1,'
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
