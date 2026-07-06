<?php

declare(strict_types=1);

namespace Volt\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSysNoteTable extends Migration
{
    private const T_NOTE = 'sys_note';

    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'BIGSERIAL'],
            'title'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'body'       => ['type' => 'TEXT', 'null' => true],
            'status'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'draft'],
            'owner'      => ['type' => 'VARCHAR', 'constraint' => 100],
            'created_at' => ['type' => 'TIMESTAMP', 'null' => true],
            'updated_at' => ['type' => 'TIMESTAMP', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('status');
        $this->forge->addKey('owner');
        $this->forge->createTable(self::T_NOTE, true);

        $this->db->query('ALTER TABLE ' . self::T_NOTE . ' ALTER COLUMN created_at SET DEFAULT CURRENT_TIMESTAMP');
        $this->db->query('ALTER TABLE ' . self::T_NOTE . ' ALTER COLUMN updated_at SET DEFAULT CURRENT_TIMESTAMP');
    }

    public function down()
    {
        $this->forge->dropTable(self::T_NOTE, true);
    }
}
