<?php

declare(strict_types=1);

namespace Volt\Core\Models;

use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Model;
use Volt\Core\Database\VoltDatabase;

class QueueJobModel extends Model
{
    private const TABLE = 'sys_queue_job';

    protected $table            = self::TABLE;
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields    = ['job_type', 'payload', 'status', 'attempts', 'error_log', 'queue', 'priority', 'available_at', 'timeout', 'started_at', 'completed_at'];
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';
    protected $dateFormat       = 'datetime';
    protected $returnType       = 'array';

    public function __construct(?ConnectionInterface $db = null)
    {
        parent::__construct($db ?? VoltDatabase::connection());
    }

    /**
     * Đẩy một job vào hàng đợi.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $opts   queue|priority|available_at|timeout
     */
    public function dispatch(string $jobType, array $payload, array $opts = []): int
    {
        $job = [
            'job_type'     => $jobType,
            'payload'      => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status'       => 'queued',
            'attempts'     => 0,
            'queue'        => (string) ($opts['queue'] ?? 'default'),
            'priority'     => (int) ($opts['priority'] ?? 0),
            'available_at' => (string) ($opts['available_at'] ?? date('Y-m-d H:i:s')),
            'timeout'      => (int) ($opts['timeout'] ?? 60),
        ];

        return (int) $this->insert($job);
    }

    /**
     * Claim một job một cách atomic (UPDATE...RETURNING, chống trùng claim).
     *
     * @param string|null $queue null = bất kỳ queue nào
     */
    public function claimNextJob(?string $queue = null): ?array
    {
        $queueCondition = $queue === null ? '' : ' AND queue = ?';
        $binds = $queue === null ? [] : [$queue];

        $sql = "UPDATE {$this->table} "
             . "SET status = 'running', attempts = attempts + 1, started_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP "
             . "WHERE id = ("
             . "SELECT id FROM {$this->table} "
             . "WHERE status = 'queued' AND (available_at IS NULL OR available_at <= CURRENT_TIMESTAMP)"
             . $queueCondition
             . " ORDER BY priority DESC, created_at ASC LIMIT 1"
             . ") RETURNING *";

        $result = $this->db->query($sql, $binds);

        if ($result === null || $result->getNumRows() === 0) {
            return null;
        }

        return $result->getRowArray();
    }

    public function markCompleted(int $id): void
    {
        $this->update($id, [
            'status'       => 'completed',
            'started_at'   => null,
            'completed_at' => date('Y-m-d H:i:s'),
            'error_log'    => null,
        ]);
    }

    public function markDead(int $id, string $error): void
    {
        $this->update($id, [
            'status'       => 'dead',
            'error_log'    => $error,
            'completed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function scheduleRetry(int $id, string $error, string $availableAt): void
    {
        $this->update($id, [
            'status'       => 'queued',
            'error_log'    => $error,
            'available_at' => $availableAt,
            'started_at'   => null,
        ]);
    }

    public function resetFailed(int $id): bool
    {
        return $this->update($id, [
            'status'       => 'queued',
            'attempts'     => 0,
            'error_log'    => null,
            'available_at' => date('Y-m-d H:i:s'),
            'started_at'   => null,
        ]);
    }

    /** Đưa các job 'running' bị treo (quá hạn started_at) trở lại hàng đợi. */
    public function requeueStaleJobs(int $staleAfterSeconds = 300): int
    {
        $sql = "UPDATE {$this->table} "
             . "SET status = 'queued', started_at = NULL, available_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP "
             . "WHERE status = 'running' AND started_at < (CURRENT_TIMESTAMP - (? * INTERVAL '1 second'))";

        $this->db->query($sql, [$staleAfterSeconds]);

        return $this->db->affectedRows();
    }

    /** Xóa các job dead đã quá số ngày giữ lại. */
    public function purgeDead(int $days = 30): int
    {
        $sql = "DELETE FROM {$this->table} "
             . "WHERE status = 'dead' AND updated_at < (CURRENT_TIMESTAMP - (? * INTERVAL '1 day'))";

        $this->db->query($sql, [$days]);

        return $this->db->affectedRows();
    }

    /** @return array<string, int> Số job theo trạng thái */
    public function counts(): array
    {
        $result = $this->db->table($this->table)
            ->select('status, COUNT(*) as total')
            ->groupBy('status')
            ->get()
            ->getResultArray();

        $counts = [];
        foreach ($result as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }
}
