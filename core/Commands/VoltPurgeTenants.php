<?php

declare(strict_types=1);

namespace Volt\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;
use Volt\Core\Tenant\Models\TenantModel;
use Volt\Core\Tenant\Services\TenantService;

class VoltPurgeTenants extends BaseCommand
{
    protected $group = 'Volt Core';
    protected $name = 'volt:purge-tenants';
    protected $description = 'Purge tenants đã quá hạn grace period (backup trước khi drop DB).';

    public function run(array $params): void
    {
        $service = new TenantService();
        $model = new TenantModel();

        $force = array_key_exists('force', $params) || in_array('-f', $params, true);
        $due = $force ? $model->getTrashed() : $model->getDuePurge();

        if ($due === []) {
            CLI::write('Không có tenant nào cần purge.', 'green');
            return;
        }

        foreach ($due as $tenant) {
            $name = (string) $tenant['name'];

            try {
                $backup = $service->purge($name, $force);
                CLI::write("Purged '{$name}'" . ($backup !== '' ? " (backup: {$backup})" : ''), 'green');
            } catch (Throwable $e) {
                CLI::error("Purge failed for '{$name}': " . $e->getMessage());
            }
        }
    }
}
