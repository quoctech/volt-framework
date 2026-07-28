<?php

declare(strict_types=1);

namespace Volt\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\MigrationRunner;
use CodeIgniter\Database\BaseConnection;
use Config\Migrations;
use Throwable;
use Volt\Core\Database\VoltDatabase;

class TenantMigrate extends BaseCommand
{
    protected $group = 'Volt Core';
    protected $name = 'volt:tenant-migrate';
    protected $description = 'Run core migrations on a specific tenant database.';

    public function run(array $params): void
    {
        $tenantName = $params[0] ?? CLI::prompt('Tenant name', null, 'required');

        try {
            $db = VoltDatabase::tenantConnection($tenantName);

            $runner = new MigrationRunner(config(Migrations::class), $db);
            $runner->setNamespace('Volt\Core');

            CLI::write("Running migrations for tenant '{$tenantName}'...", 'yellow');

            $success = $runner->latest();

            foreach ($runner->getCliMessages() as $message) {
                CLI::write($message);
            }

            if (! $success) {
                CLI::error("Migration failed for tenant '{$tenantName}'.");
                return;
            }

            CLI::write("Migrations completed for tenant '{$tenantName}'.", 'green');
        } catch (Throwable $e) {
            CLI::error($e->getMessage());
        }
    }
}
