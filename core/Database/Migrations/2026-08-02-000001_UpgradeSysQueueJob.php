<?php

declare(strict_types=1);

namespace Volt\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpgradeSysQueueJob extends Migration
{
    private const TABLE_QUEUE = 'sys_queue_job';

    public function up()
    {
        if (! $this->db->fieldExists('queue', self::TABLE_QUEUE)) {
            $this->forge->addColumn(self::TABLE_QUEUE, [
                'queue'        => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'default', 'null' => true],
            ]);
        }

        if (! $this->db->fieldExists('priority', self::TABLE_QUEUE)) {
            $this->forge->addColumn(self::TABLE_QUEUE, [
                'priority' => ['type' => 'SMALLINT', 'default' => 0, 'null' => true],
            ]);
        }

        if (! $this->db->fieldExists('available_at', self::TABLE_QUEUE)) {
            $this->forge->addColumn(self::TABLE_QUEUE, [
                'available_at' => ['type' => 'TIMESTAMP', 'null' => true],
            ]);
            $this->db->query('ALTER TABLE ' . self::TABLE_QUEUE . ' ALTER COLUMN available_at SET DEFAULT CURRENT_TIMESTAMP');
        }

        if (! $this->db->fieldExists('timeout', self::TABLE_QUEUE)) {
            $this->forge->addColumn(self::TABLE_QUEUE, [
                'timeout' => ['type' => 'INTEGER', 'default' => 60, 'null' => true],
            ]);
        }

        if (! $this->db->fieldExists('started_at', self::TABLE_QUEUE)) {
            $this->forge->addColumn(self::TABLE_QUEUE, [
                'started_at' => ['type' => 'TIMESTAMP', 'null' => true],
            ]);
        }

        if (! $this->db->fieldExists('completed_at', self::TABLE_QUEUE)) {
            $this->forge->addColumn(self::TABLE_QUEUE, [
                'completed_at' => ['type' => 'TIMESTAMP', 'null' => true],
            ]);
        }

        $this->db->query('CREATE INDEX IF NOT EXISTS idx_sys_queue_status_queue ON ' . self::TABLE_QUEUE . ' (status, queue, available_at)');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_sys_queue_queue ON ' . self::TABLE_QUEUE . ' (queue)');
    }

    public function down()
    {
        $this->db->query('DROP INDEX IF EXISTS idx_sys_queue_status_queue');
        $this->db->query('DROP INDEX IF EXISTS idx_sys_queue_queue');
        $this->forge->dropColumn(self::TABLE_QUEUE, ['queue', 'priority', 'available_at', 'timeout', 'started_at', 'completed_at']);
    }
}
