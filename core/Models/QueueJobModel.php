<?php

declare(strict_types=1);

namespace Volt\Core\Models;

use CodeIgniter\Model;

final class QueueJobModel extends Model
{
    protected $table            = 'sys_queue_job';
    protected $primaryKey       = 'id';
    protected $allowedFields    = ['job_type', 'payload', 'status', 'attempts', 'error_log'];
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';
    protected $dateFormat       = 'datetime';
    protected $returnType       = 'array';
}
