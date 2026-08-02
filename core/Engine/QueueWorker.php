<?php

declare(strict_types=1);

namespace Volt\Core\Engine;

use Config\Queue;
use RuntimeException;
use Throwable;
use Volt\Core\Models\QueueJobModel;

final class QueueWorker
{
    private readonly QueueJobModel $model;
    private readonly Queue $config;

    /** @var array<string, callable> */
    private array $handlers = [];

    public function __construct(?QueueJobModel $model = null, ?Queue $config = null)
    {
        $this->model = $model ?? new QueueJobModel();
        $this->config = $config ?? config('Queue');
    }

    public function registerHandler(string $jobType, callable $handler): void
    {
        $this->handlers[$jobType] = $handler;
    }

    public function hasHandler(string $jobType): bool
    {
        return isset($this->handlers[$jobType]);
    }

    public function processNext(?string $queue = null, ?int $timeoutOverride = null): bool
    {
        $job = $this->model->claimNextJob($queue);

        if ($job === null) {
            return false;
        }

        $id = (int) $job['id'];

        try {
            $handler = $this->resolveHandler((string) $job['job_type']);
            $payload = is_string($job['payload']) ? json_decode($job['payload'], true) : $job['payload'];
            $payload = is_array($payload) ? $payload : [];

            $this->enforceTimeout($timeoutOverride ?? (int) ($job['timeout'] ?? $this->config->timeout));

            $handler($payload, $job);
            $this->model->markCompleted($id);
        } catch (Throwable $e) {
            $this->handleFailure($job, $e);
        }

        return true;
    }

    public function processAll(?string $queue = null, ?int $maxJobs = null, ?int $timeoutOverride = null): int
    {
        $count = 0;

        while ($this->processNext($queue, $timeoutOverride)) {
            $count++;

            if ($maxJobs !== null && $count >= $maxJobs) {
                break;
            }
        }

        return $count;
    }

    /** Đưa các job 'running' bị treo trở lại hàng đợi. */
    public function requeueStaleJobs(): int
    {
        return $this->model->requeueStaleJobs($this->config->staleAfterSeconds);
    }

    /**
     * @param array<string, mixed> $job
     */
    private function handleFailure(array $job, Throwable $e): void
    {
        $id = (int) $job['id'];
        $attempts = (int) ($job['attempts'] ?? 0);
        $message = $e->getMessage();

        service('voltErrorLog')->logException($e, [
            'job_id'   => $id,
            'job_type' => $job['job_type'] ?? null,
            'attempts' => $attempts,
        ], 'queue', 'queue_job_failed');

        if ($attempts >= $this->config->maxAttempts) {
            $this->model->markDead($id, $message);

            return;
        }

        $delay = $this->config->backoffBaseSeconds * (2 ** ($attempts - 1));
        $this->model->scheduleRetry($id, $message, date('Y-m-d H:i:s', time() + $delay));
    }

    private function resolveHandler(string $jobType): callable
    {
        if (! isset($this->handlers[$jobType])) {
            throw new RuntimeException("No handler registered for job type: {$jobType}");
        }

        return $this->handlers[$jobType];
    }

    private function enforceTimeout(int $seconds): void
    {
        if ($seconds > 0) {
            set_time_limit($seconds);
        }
    }
}
