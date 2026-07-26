<?php

declare(strict_types=1);

namespace Volt\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Volt\Core\Engine\QueueWorker;

final class VoltQueueWork extends BaseCommand
{
    protected $group       = 'Volt Core';
    protected $name        = 'volt:queue-work';
    protected $description = 'Process pending queue jobs';
    protected $usage       = 'volt:queue-work [options]';
    protected $options     = [
        '--once' => 'Process only one job and exit',
        '--sleep' => 'Seconds to wait when no jobs are available (default: 3)',
    ];

    public function run(array $params): void
    {
        $once  = array_key_exists('once', $params);
        $sleep = (int) ($params['sleep'] ?? 3);
        $loop  = true;

        $worker = new QueueWorker();
        $this->discoverHandlers($worker);

        while ($loop) {
            $processed = $worker->processNext();

            if ($processed) {
                CLI::write('Job processed.', 'green');
            } else {
                CLI::write('No jobs pending.', 'yellow');
            }

            if ($once) {
                $loop = false;
                break;
            }

            sleep($sleep);
        }
    }

    private function discoverHandlers(QueueWorker $worker): void
    {
        $handlerDir = ROOTPATH . 'app/QueueHandlers';

        if (! is_dir($handlerDir)) {
            return;
        }

        $files = glob($handlerDir . '/*.php');

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $contents = file_get_contents($file);

            if ($contents === false) {
                continue;
            }

            if (preg_match('/^<\?php\s+declare\(strict_types=1\);\s+namespace\s+(\S+);/m', $contents, $nsMatch) !== 1) {
                continue;
            }

            if (preg_match('/class\s+(\w+)/', $contents, $classMatch) !== 1) {
                continue;
            }

            require_once $file;

            $fqcn = $nsMatch[1] . '\\' . $classMatch[1];

            if (! class_exists($fqcn)) {
                continue;
            }

            $instance = new $fqcn();

            $jobType = $this->inferJobType($classMatch[1], $instance);

            if ($jobType !== null) {
                $worker->registerHandler($jobType, [$instance, 'handle']);
            }
        }
    }

    /**
     * @param object $instance
     */
    private function inferJobType(string $className, object $instance): ?string
    {
        if (defined(get_class($instance) . '::JOB_TYPE')) {
            return $instance::JOB_TYPE;
        }

        if (str_ends_with($className, 'Handler')) {
            return substr($className, 0, -7);
        }

        return $className;
    }
}
