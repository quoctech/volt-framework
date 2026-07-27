<?php

declare(strict_types=1);

namespace Volt\Core\Engine;

use RuntimeException;
use Volt\Core\Models\QueueJobModel;

final class QueueWorker
{
    private const MAX_ATTEMPTS = 3;

    private QueueJobModel $model;

    /** @var array<string, callable> */
    private array $handlers = [];

    public function __construct()
    {
        $this->model = new QueueJobModel();
    }

    public function registerHandler(string $jobType, callable $handler): void
    {
        $this->handlers[$jobType] = $handler;
    }

    public function processNext(): bool
    {
        $job = $this->model->claimNextJob();

        if ($job === null) {
            return false;
        }

        try {
            $handler = $this->resolveHandler($job['job_type']);
            $payload = is_string($job['payload']) ? json_decode($job['payload'], true) : $job['payload'];
            $handler($payload, $job);
            $this->markCompleted($job['id']);
        } catch (Throwable $e) {
            $this->markFailed($job['id'], (int) ($job['attempts'] ?? 0), $e->getMessage());
        }

        return true;
    }

    public function processAll(): int
    {
        $count = 0;

        while ($this->processNext()) {
            $count++;
        }

        return $count;
    }

    private function markCompleted(int $id): void
    {
        $this->model->update($id, [
            'status' => 'completed',
        ]);
    }

    private function markFailed(int $id, int $attempts, string $error): void
    {
        $status = $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'queued';

        $this->model->update($id, [
            'status'    => $status,
            'error_log' => $error,
        ]);
    }

    private function resolveHandler(string $jobType): callable
    {
        if (! isset($this->handlers[$jobType])) {
            throw new RuntimeException("No handler registered for job type: {$jobType}");
        }

        return $this->handlers[$jobType];
    }
}
