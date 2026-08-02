<?php

declare(strict_types=1);

namespace Volt\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Volt\Core\Engine\QueueWorker;
use Volt\Core\Queue\JobHandlerInterface;

final class VoltQueueWork extends BaseCommand
{
    protected $group       = 'Volt Core';
    protected $name        = 'volt:queue-work';
    protected $description = 'Process pending queue jobs';
    protected $usage       = 'volt:queue-work [options]';
    protected $options     = [
        '--once'          => 'Process only one job and exit',
        '--sleep'         => 'Seconds to wait when no jobs are available (default: 3)',
        '--queue'         => 'Only process jobs from this queue (default: all queues)',
        '--max-jobs'      => 'Process up to N jobs then exit',
        '--max-time'      => 'Run for at most N seconds then exit (default: 0 = unlimited)',
        '--timeout'       => 'Override job timeout in seconds',
        '--stale-requeue' => 'Requeue stale running jobs before processing',
        '--status'        => 'Print queue status counts and exit',
        '--retry'         => 'Reset a job id (failed/dead) back to queued and exit',
        '--purge-dead'    => 'Purge dead jobs older than --days and exit',
        '--days'          => 'Age threshold in days for purge-dead (default: 30)',
    ];

    public function run(array $params): void
    {
        $worker = new QueueWorker();
        $this->discoverHandlers($worker);

        if (CLI::getOption('status')) {
            $this->printStatus();

            return;
        }

        $retryId = CLI::getOption('retry');
        if ($retryId !== null && $retryId !== true) {
            $this->retryJob((int) $retryId);

            return;
        }

        if (CLI::getOption('purge-dead')) {
            $days = (int) (CLI::getOption('days') ?? 30);
            $this->purgeDead($days);

            return;
        }

        if (CLI::getOption('stale-requeue')) {
            $requeued = $worker->requeueStaleJobs();
            CLI::write("Requeued {$requeued} stale job(s).", 'yellow');
        }

        $once      = CLI::getOption('once') === true;
        $queue     = CLI::getOption('queue');
        $queue     = is_string($queue) ? $queue : null;
        $sleep     = max(0, (int) (CLI::getOption('sleep') ?? 3));
        $maxJobs   = CLI::getOption('max-jobs') !== null && CLI::getOption('max-jobs') !== true ? (int) CLI::getOption('max-jobs') : null;
        $maxTime   = (int) (CLI::getOption('max-time') ?? 0);
        $timeout   = CLI::getOption('timeout') !== null && CLI::getOption('timeout') !== true ? (int) CLI::getOption('timeout') : null;
        $startedAt = time();

        while (true) {
            if ($maxTime > 0 && (time() - $startedAt) >= $maxTime) {
                CLI::write('Reached --max-time, exiting.', 'yellow');
                break;
            }

            $processed = $once
                ? $worker->processNext($queue, $timeout)
                : $worker->processAll($queue, $maxJobs, $timeout) > 0;

            if ($processed) {
                CLI::write('Job processed.', 'green');
            } else {
                CLI::write('No jobs pending.', 'yellow');
            }

            if ($once || ($maxJobs !== null && $maxJobs > 0)) {
                break;
            }

            sleep($sleep);
        }
    }

    private function printStatus(): void
    {
        $counts = (new \Volt\Core\Models\QueueJobModel())->counts();

        if ($counts === []) {
            CLI::write('Queue is empty.', 'green');

            return;
        }

        foreach ($counts as $status => $total) {
            $color = match ($status) {
                'queued'    => 'yellow',
                'running'   => 'blue',
                'completed' => 'green',
                'dead'      => 'red',
                default     => 'white',
            };
            CLI::write(sprintf('%-10s %d', $status, $total), $color);
        }
    }

    private function retryJob(int $id): void
    {
        $model = new \Volt\Core\Models\QueueJobModel();

        if ($model->resetFailed($id)) {
            CLI::write("Job {$id} requeued.", 'green');
        } else {
            CLI::error("Job {$id} not found or not reset.");
        }
    }

    private function purgeDead(int $days): void
    {
        $model = new \Volt\Core\Models\QueueJobModel();
        $purged = $model->purgeDead($days);
        CLI::write("Purged {$purged} dead job(s).", 'green');
    }

    private function discoverHandlers(QueueWorker $worker): void
    {
        $handlerDir = ROOTPATH . 'app/QueueHandlers';

        if (! is_dir($handlerDir)) {
            return;
        }

        foreach (glob($handlerDir . '/*.php') ?: [] as $file) {
            require_once $file;

            $class = $this->resolveHandlerClass($file);
            if ($class === null) {
                continue;
            }

            $instance = new $class();
            $worker->registerHandler($instance::JOB_TYPE, [$instance, 'handle']);
        }
    }

    private function resolveHandlerClass(string $file): ?string
    {
        $declared = get_declared_classes();

        foreach ($declared as $class) {
            if (! is_subclass_of($class, JobHandlerInterface::class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);
            if ($reflection->getFileName() === realpath($file)) {
                return $class;
            }
        }

        return null;
    }
}
