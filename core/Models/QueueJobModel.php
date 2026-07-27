<?php

declare(strict_types=1);

namespace Volt\Core\Models;

use CodeIgniter\Model;
use Volt\Core\Database\VoltDatabase;

final class QueueJobModel extends Model
{
    protected $table            = 'sys_queue_job';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields    = ['job_type', 'payload', 'status', 'attempts', 'error_log'];
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';
    protected $dateFormat       = 'datetime';
    protected $returnType       = 'array';

    public function __construct()
    {
        parent::__construct(VoltDatabase::connection());
    }

    public function claimNextJob(): ?array
    {
        $sql = "UPDATE {$this->table} "
             . "SET status = 'running', attempts = attempts + 1, updated_at = CURRENT_TIMESTAMP "
             . "WHERE id = ("
             . "SELECT id FROM {$this->table} WHERE status = 'queued' ORDER BY created_at ASC LIMIT 1"
             . ") RETURNING *";

        $result = $this->db->query($sql);

        if ($result->getNumRows() === 0) {
            return null;
        }

        return $result->getRowArray();
    }
}
