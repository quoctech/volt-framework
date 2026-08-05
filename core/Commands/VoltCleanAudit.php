<?php

declare(strict_types=1);

namespace Volt\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\I18n\Time;
use Volt\Core\Database\VoltDatabase;

final class VoltCleanAudit extends BaseCommand
{
    protected $group       = 'Volt Core';
    protected $name        = 'volt:clean-audit';
    protected $description = 'Purge old sys_audit_trail entries (append-only; uses SECURITY DEFINER function)';
    protected $usage       = 'volt:clean-audit [options]';
    protected $options     = [
        '--days' => 'Retention period in days (default: 730)',
        '--dry-run' => 'Show count of records that would be deleted without deleting',
    ];

    public function run(array $params): void
    {
        $retainDays = max(1, (int) ($params['days'] ?? 730));
        $dryRun = array_key_exists('dry-run', $params);

        $db = VoltDatabase::connection();
        $cutoff = Time::now()->subDays($retainDays)->toDateTimeString();

        $count = $db->table('sys_audit_trail')
            ->where('changed_at <', $cutoff)
            ->countAllResults();

        if ($count === 0) {
            CLI::write('No audit trail entries older than ' . $retainDays . ' days found.', 'green');
            return;
        }

        if ($dryRun) {
            CLI::write(sprintf('[DRY-RUN] Would purge %d record(s) from sys_audit_trail (retention: %d days).', $count, $retainDays), 'yellow');
            return;
        }

        $result = $db->query('SELECT volt_audit_purge(' . (int) $retainDays . ') AS purged')->getResultArray();
        $purged = (int) ($result[0]['purged'] ?? 0);

        CLI::write(sprintf('Purged %d record(s) from sys_audit_trail (retention: %d days).', $purged, $retainDays), 'green');
    }
}
