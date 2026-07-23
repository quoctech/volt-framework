<?php

declare(strict_types=1);

namespace Volt\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\MigrationRunner;
use Config\Migrations;
use Throwable;

final class VoltCoreMigrateStatus extends BaseCommand
{
    private const CORE_NAMESPACE = 'Volt\Core';

    protected $group = 'Volt Core';
    protected $name = 'volt:core-migrate-status';
    protected $description = 'Hiển thị trạng thái migrations của core Volt\\Core.';
    protected $usage = 'volt:core-migrate-status';

    public function run(array $params): void
    {
        try {
            $runner = new MigrationRunner(config(Migrations::class));
            $runner->setNamespace(self::CORE_NAMESPACE);

            $available = $runner->findMigrations();
            $history = $runner->getHistory();

            $executed = [];
            foreach ($history as $item) {
                $executed[$item->version] = (int) $item->batch;
            }

            if ($available === []) {
                CLI::write('Không tìm thấy core migrations.', 'yellow');
                return;
            }

            $rows = [];
            foreach ($available as $migration) {
                $rows[] = [
                    $migration->version,
                    $migration->name,
                    array_key_exists($migration->version, $executed) ? 'Yes' : 'No',
                    (string) ($executed[$migration->version] ?? '-'),
                ];
            }

            CLI::table($rows, ['Version', 'Migration', 'Ran?', 'Batch']);
        } catch (Throwable $e) {
            try {
                service('voltErrorLog')->logException($e, 'migration', [
                    'command' => $this->name,
                    'namespace' => self::CORE_NAMESPACE,
                ]);
            } catch (Throwable) {
            }

            CLI::error($e->getMessage());
        }
    }
}
