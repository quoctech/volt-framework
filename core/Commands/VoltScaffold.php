<?php

declare(strict_types=1);

namespace Volt\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Volt\Core\Database\VoltDatabase;
use Volt\Core\Metadata\ArtifactScaffolder;
use Volt\Core\Engine\VoltMetadataCompiler;

class VoltScaffold extends BaseCommand
{
    protected $group = 'Volt Core';

    protected $name = 'volt:scaffold';

    protected $description = 'Regenerate all entity artifacts (controllers, models, views, JS) from metadata';

    protected $usage = 'volt:scaffold [EntityName] hoặc volt:scaffold --all';

    protected $arguments = [
        'EntityName' => 'Tên thực thể cần tạo lại artifact (Ví dụ: Employee)',
    ];

    protected $options = [
        '--all' => 'Tạo lại artifact cho tất cả thực thể',
    ];

    public function run(array $params): void
    {
        if (CLI::getOption('all')) {
            CLI::write('Regenerating artifacts for all entities...', 'yellow');

            $entities = VoltDatabase::connection()
                ->table('sys_entity')
                ->select('name, module')
                ->get()
                ->getResultArray();

            if (empty($entities)) {
                CLI::error('No entities found in sys_entity!');
                return;
            }

            $success = 0;
            $failed = 0;

            foreach ($entities as $entity) {
                $entityName = (string) ($entity['name'] ?? '');
                $moduleName = (string) ($entity['module'] ?? '');

                if ($entityName === '' || $moduleName === '') {
                    continue;
                }

                if ($this->scaffoldEntity($moduleName, $entityName)) {
                    CLI::write("  OK: {$entityName}", 'green');
                    $success++;
                } else {
                    CLI::error("FAIL: {$entityName}");
                    $failed++;
                }
            }

            CLI::write("Done. Success: {$success}, Failed: {$failed}", 'yellow');
        } else {
            $entityName = $params[0] ?? null;
            if (! $entityName) {
                CLI::error('Usage: ' . $this->usage);
                return;
            }

            $entity = VoltDatabase::connection()
                ->table('sys_entity')
                ->select('name, module')
                ->where('name', $entityName)
                ->get()
                ->getRowArray();

            if (! $entity) {
                CLI::error("Entity not found: {$entityName}");
                return;
            }

            if ($this->scaffoldEntity((string) ($entity['module'] ?? ''), (string) ($entity['name'] ?? ''))) {
                CLI::write("OK: {$entityName}", 'green');
            } else {
                CLI::error("FAIL: {$entityName}");
            }
        }
    }

    private function scaffoldEntity(string $moduleName, string $entityName): bool
    {
        try {
            $compiler = new VoltMetadataCompiler();
            $compiled = $compiler->compileEntity($entityName, null, true);

            $scaffolder = new ArtifactScaffolder();
            $scaffolder->scaffoldEntity($moduleName, $entityName, $compiled);

            return true;
        } catch (\Throwable $e) {
            service('voltErrorLog')->logException($e, [
                'module' => $moduleName,
                'entity' => $entityName,
            ], 'entity_scaffold', 'volt_scaffold_failed');
            CLI::error("  Error: {$e->getMessage()}");
            return false;
        }
    }
}
