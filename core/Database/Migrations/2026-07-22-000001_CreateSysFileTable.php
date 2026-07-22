<?php

declare(strict_types=1);

namespace Volt\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateSysFileTable extends Migration
{
    private const TABLE = 'sys_file';

    public function up()
    {
        $this->forge->addField([
            'name'               => ['type' => 'VARCHAR', 'constraint' => 100],
            'file_name'          => ['type' => 'VARCHAR', 'constraint' => 500],
            'file_path'          => ['type' => 'TEXT'],
            'file_size'          => ['type' => 'BIGINT', 'default' => 0],
            'file_type'          => ['type' => 'VARCHAR', 'constraint' => 255],
            'attached_to_entity' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'attached_to_name'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'attached_to_field'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'is_private'         => ['type' => 'SMALLINT', 'default' => 1],
            'owner'              => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'system'],
            'creation'           => ['type' => 'TIMESTAMP', 'null' => false],
            'modified'           => ['type' => 'TIMESTAMP', 'null' => false],
            'docstatus'          => ['type' => 'SMALLINT', 'default' => 0],
        ]);

        $this->forge->addKey('name', true);
        $this->forge->addKey('attached_to_entity');
        $this->forge->createTable(self::TABLE, true);

        $this->db->query('ALTER TABLE ' . self::TABLE . ' ALTER COLUMN creation SET DEFAULT CURRENT_TIMESTAMP');
        $this->db->query('ALTER TABLE ' . self::TABLE . ' ALTER COLUMN modified SET DEFAULT CURRENT_TIMESTAMP');
    }

    public function down()
    {
        $this->forge->dropTable(self::TABLE, true);
    }
}
