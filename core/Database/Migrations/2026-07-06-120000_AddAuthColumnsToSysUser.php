<?php

declare(strict_types=1);

namespace Volt\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAuthColumnsToSysUser extends Migration
{
    private const T_USER = 'sys_user';

    public function up()
    {
        $columns = [];

        if (! $this->db->fieldExists('is_active', self::T_USER)) {
            $columns['is_active'] = ['type' => 'SMALLINT', 'default' => 1];
        }

        if (! $this->db->fieldExists('api_token_hash', self::T_USER)) {
            $columns['api_token_hash'] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true];
        }

        if (! $this->db->fieldExists('api_token_expires_at', self::T_USER)) {
            $columns['api_token_expires_at'] = ['type' => 'TIMESTAMP', 'null' => true];
        }

        if (! $this->db->fieldExists('last_login_at', self::T_USER)) {
            $columns['last_login_at'] = ['type' => 'TIMESTAMP', 'null' => true];
        }

        if (! $this->db->fieldExists('failed_login_attempts', self::T_USER)) {
            $columns['failed_login_attempts'] = ['type' => 'INTEGER', 'default' => 0];
        }

        if (! $this->db->fieldExists('locked_until', self::T_USER)) {
            $columns['locked_until'] = ['type' => 'TIMESTAMP', 'null' => true];
        }

        if (! $this->db->fieldExists('created_at', self::T_USER)) {
            $columns['created_at'] = ['type' => 'TIMESTAMP', 'null' => true];
        }

        if (! $this->db->fieldExists('updated_at', self::T_USER)) {
            $columns['updated_at'] = ['type' => 'TIMESTAMP', 'null' => true];
        }

        if ($columns !== []) {
            $this->forge->addColumn(self::T_USER, $columns);
            $this->db->query('ALTER TABLE ' . self::T_USER . ' ALTER COLUMN created_at SET DEFAULT CURRENT_TIMESTAMP');
            $this->db->query('ALTER TABLE ' . self::T_USER . ' ALTER COLUMN updated_at SET DEFAULT CURRENT_TIMESTAMP');
        }
    }

    public function down()
    {
        $this->forge->dropColumn(self::T_USER, [
            'is_active',
            'api_token_hash',
            'api_token_expires_at',
            'last_login_at',
            'failed_login_attempts',
            'locked_until',
            'created_at',
            'updated_at',
        ]);
    }
}
