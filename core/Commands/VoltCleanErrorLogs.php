<?php

declare(strict_types=1);

namespace Volt\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;

final class VoltCleanErrorLogs extends BaseCommand
{
    protected $group       = 'Volt Core';
    protected $name        = 'volt:clean-error-logs';
    protected $description = 'Purge old sys_error_log entries';
    protected $usage       = 'volt:clean-error-logs [options]';
    protected $options     = [
        '--days' => 'Retention period in days (default: 90)',
        '--dry-run' => 'Show count of records that would be deleted without deleting',
    ];

    public function run(array $params): void
    {
        $retainDays = max(1, (int) ($params['days'] ?? 90));
        $dryRun = array_key_exists('dry-run', $params);

        $logService = Services::voltErrorLog();
        $db = \Volt\Core\Database\VoltDatabase::connection();

        $cutoff = (new \DateTimeImmutable("-{$retainDays} days"))->format('Y-m-d H:i:s');

        $count = $db->table('sys_error_log')
            ->where('created_at <', $cutoff)
            ->countAllResults();

        if ($count === 0) {
            CLI::write('No error log entries older than ' . $retainDays . ' days found.', 'green');
            return;
        }

        if ($dryRun) {
            CLI::write(sprintf('[DRY-RUN] Would delete %d record(s) from sys_error_log (retention: %d days).', $count, $retainDays), 'yellow');
            return;
        }

        $deleted = $logService->purge($retainDays);

        CLI::write(sprintf('Purged %d record(s) from sys_error_log (retention: %d days).', $deleted, $retainDays), 'green');
    }
}
