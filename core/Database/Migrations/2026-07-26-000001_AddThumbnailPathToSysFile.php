<?php

declare(strict_types=1);

namespace Volt\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddThumbnailPathToSysFile extends Migration
{
    private const TABLE = 'sys_file';

    public function up()
    {
        if (! $this->db->fieldExists('thumbnail_path', self::TABLE)) {
            $this->db->query('ALTER TABLE ' . self::TABLE . ' ADD COLUMN thumbnail_path TEXT DEFAULT NULL');
        }
    }

    public function down()
    {
        $this->db->query('ALTER TABLE ' . self::TABLE . ' DROP COLUMN IF EXISTS thumbnail_path');
    }
}
