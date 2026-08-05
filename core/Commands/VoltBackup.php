<?php

declare(strict_types=1);

namespace Volt\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Volt;
use Throwable;
use Volt\Core\System\Services\BackupService;
use Volt\Core\Tenant\Models\TenantModel;

class VoltBackup extends BaseCommand
{
    protected $group = 'Volt Core';
    protected $name = 'volt:backup';
    protected $description = 'Backup tenant databases (pg_dump). Optional restore test.';

    public function run(array $params): void
    {
        $service = new BackupService();
        $model = new TenantModel();

        $tenantName = is_string($params[0] ?? null) ? $params[0] : null;
        $verify = array_key_exists('verify', $params) || in_array('-v', $params, true);
        $prune = array_key_exists('prune', $params) || in_array('-p', $params, true);

        if ($tenantName !== null) {
            $tenants = [$tenantName];
        } else {
            $tenants = array_column($model->getActive(), 'name');
        }

        if ($tenants === []) {
            CLI::error('No active tenants found.');
            return;
        }

        $ok = 0;
        $fail = 0;

        foreach ($tenants as $name) {
            $tenant = $model->find($name);
            if ($tenant === null) {
                CLI::error("Tenant '{$name}' not found or inactive.");
                $fail++;
                continue;
            }

            try {
                $file = $service->backup(
                    (string) $tenant['db_name'],
                    (string) $tenant['db_host'],
                    (int) $tenant['db_port'],
                    (string) $tenant['db_username'],
                    (string) $tenant['db_password'],
                );
                CLI::write("Backed up '{$name}' → {$file}", 'green');
                $ok++;

                if ($verify) {
                    $result = $service->verifyRestore(
                        (string) $tenant['db_name'],
                        $file,
                        (string) $tenant['db_host'],
                        (int) $tenant['db_port'],
                        (string) $tenant['db_username'],
                        (string) $tenant['db_password'],
                    );
                    CLI::write(($result['ok'] ? '  [OK] ' : '  [FAIL] ') . ($result['message'] ?? ''), $result['ok'] ? 'green' : 'red');
                }
            } catch (Throwable $e) {
                CLI::error("Backup failed for '{$name}': " . $e->getMessage());
                $fail++;
            }
        }

        CLI::newLine();
        CLI::write("Backup done: {$ok} ok, {$fail} failed.", $fail === 0 ? 'green' : 'yellow');

        if ($prune) {
            $removed = $service->prune((int) config(Volt::class)->backupRetentionDays);
            CLI::write("Pruned {$removed} old backup file(s).", 'green');
        }
    }
}
