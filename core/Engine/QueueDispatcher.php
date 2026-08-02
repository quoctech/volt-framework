<?php

declare(strict_types=1);

namespace Volt\Core\Engine;

use CodeIgniter\Events\Events;
use Config\Queue;
use InvalidArgumentException;
use Volt\Core\Models\QueueJobModel;

final class QueueDispatcher
{
    private const JOB_TYPE_PATTERN = '/^[a-zA-Z][a-zA-Z0-9_.-]*$/';

    private readonly QueueJobModel $model;
    private readonly Queue $config;

    public function __construct(?QueueJobModel $model = null, ?Queue $config = null)
    {
        $this->model = $model ?? new QueueJobModel();
        $this->config = $config ?? config('Queue');
    }

    /**
     * Đẩy job vào hàng đợi.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $opts queue|priority|available_at|timeout
     */
    public function dispatch(string $jobType, array $payload = [], array $opts = []): int
    {
        $jobType = mb_trim($jobType);

        if (! preg_match(self::JOB_TYPE_PATTERN, $jobType)) {
            throw new InvalidArgumentException("Invalid job type: {$jobType}");
        }

        $opts['queue'] ??= $this->config->defaultQueue;
        $opts['timeout'] ??= $this->config->timeout;

        $id = $this->model->dispatch($jobType, $payload, $opts);

        Events::trigger('volt.queue.dispatched', $jobType, $payload, $id);

        return $id;
    }
}
