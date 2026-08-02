<?php

declare(strict_types=1);

namespace App\QueueHandlers;

use Volt\Core\Engine\VoltMetadataCompiler;
use Volt\Core\Queue\JobHandlerInterface;

final class RebuildMetadataCacheHandler implements JobHandlerInterface
{
    public const JOB_TYPE = 'rebuild_metadata_cache';

    public function handle(array $payload, array $job): void
    {
        $compiler = new VoltMetadataCompiler();
        $role = is_string($payload['role'] ?? null) && $payload['role'] !== '' ? $payload['role'] : null;
        $force = (bool) ($payload['force'] ?? false);

        $compiler->warmAll($role, $force);
    }
}
