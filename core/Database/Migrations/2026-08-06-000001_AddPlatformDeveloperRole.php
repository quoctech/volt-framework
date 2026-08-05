<?php

declare(strict_types=1);

namespace Volt\Core\Database\Migrations;

use CodeIgniter\Database\Migration;
use Volt\Core\Database\VoltDatabase;

/**
 * Seed role hệ thống platform_developer (quyền sửa cấu hình nền tảng
 * như Custom Pages JS). Role này được tạo ở DB hub để dùng chung cho mọi tenant.
 */
class AddPlatformDeveloperRole extends Migration
{
    const T_ROLE = 'sys_role';

    public function up()
    {
        $db = VoltDatabase::connection();
        $exists = $db->table(self::T_ROLE)->where('name', 'platform_developer')->countAllResults();

        if ($exists > 0) {
            return;
        }

        $db->table(self::T_ROLE)->insert([
            'name'        => 'platform_developer',
            'label'       => 'Platform Developer',
            'description' => 'Quyền chỉnh sửa cấu hình nền tảng (Custom Pages JS, schema).',
            'is_system'   => 1,
            'owner'       => 'system',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    public function down()
    {
        $db = VoltDatabase::connection();
        $db->table(self::T_ROLE)->where('name', 'platform_developer')->delete();
    }
}