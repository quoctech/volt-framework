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
        $job = $this->fetchNextJob();

        if ($job === null) {
            return false;
        }

        $this->markRunning($job['id']);

        try {
            $handler = $this->resolveHandler($job['job_type']);
            $payload = is_string($job['payload']) ? json_decode($job['payload'], true) : $job['payload'];
            $handler($payload, $job);
            $this->markCompleted($job['id']);
        } catch (Throwable $e) {
            $this->markFailed($job['id'], $e->getMessage());
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

    private function fetchNextJob(): ?array
    {
        $job = $this->model
            ->where('status', 'queued')
            ->orderBy('created_at', 'ASC')
            ->first();

        return $job;
    }

    private function markRunning(int $id): void
    {
        $this->model->update($id, [
            'status'    => 'running',
            'attempts'  => $this->model->find($id)['attempts'] + 1,
        ]);
    }

    private function markCompleted(int $id): void
    {
        $this->model->update($id, [
            'status' => 'completed',
        ]);
    }

    private function markFailed(int $id, string $error): void
    {
        $job = $this->model->find($id);
        $attempts = $job['attempts'] ?? 0;

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
