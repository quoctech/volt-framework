<?php

declare(strict_types=1);

namespace Volt\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddApiKeyToSysUser extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('api_key', 'sys_user')) {
            $this->db->query('ALTER TABLE sys_user ADD COLUMN api_key VARCHAR(64) DEFAULT NULL');
            $this->db->query('ALTER TABLE sys_user ADD COLUMN api_secret_hash VARCHAR(255) DEFAULT NULL');
            $this->db->query('CREATE UNIQUE INDEX IF NOT EXISTS idx_sys_user_api_key ON sys_user (api_key)');
        }
    }

    public function down()
    {
        $this->db->query('DROP INDEX IF EXISTS idx_sys_user_api_key');
        $this->db->query('ALTER TABLE sys_user DROP COLUMN IF EXISTS api_secret_hash');
        $this->db->query('ALTER TABLE sys_user DROP COLUMN IF EXISTS api_key');
    }
}
