<?php

declare(strict_types=1);

namespace Volt\Core\Queue;

interface JobHandlerInterface
{
    /** Tên job type mà handler này đảm nhận. */
    public const JOB_TYPE = '';

    /**
     * Xử lý job.
     *
     * @param array<string, mixed> $payload payload đã decode từ JSONB
     * @param array<string, mixed> $job     bản ghi job thô từ sys_queue_job
     */
    public function handle(array $payload, array $job): void;
}
