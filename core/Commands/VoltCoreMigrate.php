<?php

declare(strict_types=1);

namespace Volt\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\MigrationRunner;
use Config\Migrations;
use Throwable;

final class VoltCoreMigrate extends BaseCommand
{
    private const CORE_NAMESPACE = 'Volt\Core';

    protected $group = 'Volt Core';
    protected $name = 'volt:core-migrate';
    protected $description = 'Chạy migrations của core Volt\\Core trên database hiện tại.';
    protected $usage = 'volt:core-migrate';

    public function run(array $params): void
    {
        try {
            $runner = new MigrationRunner(config(Migrations::class));
            $runner->setNamespace(self::CORE_NAMESPACE);

            CLI::write('Đang chạy migrations cho namespace ' . self::CORE_NAMESPACE . '...', 'yellow');

            $success = $runner->latest();

            foreach ($runner->getCliMessages() as $message) {
                CLI::write($message);
            }

            if (! $success) {
                CLI::error('Core migration thất bại.');
                return;
            }

            CLI::write('Core migrations hoàn tất.', 'green');
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
