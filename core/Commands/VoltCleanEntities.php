<?php

declare(strict_types=1);

namespace Volt\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RuntimeException;
use Volt\Core\AwesomeBar\Models\AwesomeBarModel;
use Volt\Core\Database\TableNameResolver;
use Volt\Core\Database\VoltDatabase;
use Volt\Core\Engine\VoltMetadataCompiler;
use Volt\Core\Metadata\ArtifactScaffolder;
use Volt\Core\Metadata\EntityBuilderService;
use Volt\Core\Metadata\EntityMetadataCache;

final class VoltCleanEntities extends BaseCommand
{
    protected $group = 'Volt Core';
    protected $name = 'volt:clean-entities';
    protected $description = 'Quét entity/app artifacts dư thừa và hỏi xác nhận trước khi xóa.';
    protected $usage = 'volt:clean-entities';

    public function run(array $params): void
    {
        try {
            $scan = $this->scanCandidates();
        } catch (\Throwable $throwable) {
            CLI::error('Không thể quét entity cleanup candidates: ' . $throwable->getMessage());

            return;
        }

        if ($scan['artifact_orphans'] === [] && $scan['table_orphans'] === []) {
            CLI::write('Không phát hiện entity dư thừa nào cần dọn.', 'green');

            return;
        }

        $this->renderScanReport($scan);

        $deleted = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($scan['artifact_orphans'] as $candidate) {
            $label = sprintf(
                '%s/%s [%s]',
                $candidate['module_dir'],
                $candidate['entity_dir'],
                $candidate['reason']
            );

            if (! $this->shouldDelete("Xóa entity artifact {$label}?")) {
                CLI::write("Bỏ qua {$label}.", 'yellow');
                $skipped++;

                continue;
            }

            try {
                $this->deleteArtifactOrphan($candidate);
                CLI::write("Đã xóa {$label}.", 'green');
                $deleted++;
            } catch (\Throwable $throwable) {
                CLI::error("Lỗi khi xóa {$label}: {$throwable->getMessage()}");
                $failed++;
            }
        }

        foreach ($scan['table_orphans'] as $candidate) {
            $label = sprintf('%s [%s]', $candidate['table_name'], $candidate['reason']);

            if (! $this->shouldDelete("Xóa bảng dư thừa {$label}?")) {
                CLI::write("Bỏ qua {$label}.", 'yellow');
                $skipped++;

                continue;
            }

            try {
                $this->dropTableOrphan($candidate);
                CLI::write("Đã xóa {$label}.", 'green');
                $deleted++;
            } catch (\Throwable $throwable) {
                CLI::error("Lỗi khi xóa {$label}: {$throwable->getMessage()}");
                $failed++;
            }
        }

        CLI::newLine();
        CLI::write("Hoàn tất. Deleted: {$deleted}, Skipped: {$skipped}, Failed: {$failed}", $failed > 0 ? 'yellow' : 'green');
    }

    /**
     * @return array{
     *   artifact_orphans:list<array<string, string|bool>>,
     *   table_orphans:list<array<string, string>>
     * }
     */
    private function scanCandidates(): array
    {
        $db = VoltDatabase::connection();
        $metadataRows = $db->table('sys_entity')
            ->select('name, module')
            ->get()
            ->getResultArray();

        $metadataByEntity = [];
        foreach ($metadataRows as $row) {
            $name = TableNameResolver::normalizeIdentifier((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $metadataByEntity[$name] = [
                'name' => (string) ($row['name'] ?? ''),
                'module' => (string) ($row['module'] ?? ''),
            ];
        }

        $existingTables = array_map(
            static fn (string $table): string => strtolower($table),
            array_map('strval', $db->listTables())
        );
        $existingTableMap = array_fill_keys($existingTables, true);

        $artifactRows = $this->discoverArtifactEntities();
        $artifactEntityNames = [];
        $artifactOrphans = [];

        foreach ($artifactRows as $artifact) {
            $entityName = (string) $artifact['entity_name'];
            $tableName = TableNameResolver::entity($entityName);
            $artifactEntityNames[$entityName] = true;
            $hasMetadata = isset($metadataByEntity[$entityName]);
            $hasTable = isset($existingTableMap[strtolower($tableName)]);

            if ($hasMetadata && $hasTable) {
                continue;
            }

            $artifactOrphans[] = [
                'entity_name' => $entityName,
                'module_name' => (string) $artifact['module_name'],
                'module_dir' => (string) $artifact['module_dir'],
                'entity_dir' => (string) $artifact['entity_dir'],
                'table_name' => $tableName,
                'has_metadata' => $hasMetadata,
                'has_table' => $hasTable,
                'reason' => $this->artifactReason($hasMetadata, $hasTable),
            ];
        }

        $artifactCleanupEntities = array_fill_keys(
            array_map(static fn (array $row): string => (string) $row['entity_name'], $artifactOrphans),
            true
        );

        $tableOrphans = [];
        foreach ($existingTables as $tableName) {
            if (! str_starts_with($tableName, TableNameResolver::ENTITY_PREFIX)) {
                continue;
            }

            $entityName = TableNameResolver::normalizeIdentifier(substr($tableName, strlen(TableNameResolver::ENTITY_PREFIX)));
            if ($entityName === '') {
                continue;
            }

            if (isset($metadataByEntity[$entityName]) || isset($artifactEntityNames[$entityName]) || isset($artifactCleanupEntities[$entityName])) {
                continue;
            }

            $tableOrphans[] = [
                'entity_name' => $entityName,
                'table_name' => $tableName,
                'reason' => 'table exists without metadata and artifact',
            ];
        }

        return [
            'artifact_orphans' => $artifactOrphans,
            'table_orphans' => $tableOrphans,
        ];
    }

    /**
     * @return list<array{module_name:string,module_dir:string,entity_name:string,entity_dir:string}>
     */
    private function discoverArtifactEntities(): array
    {
        $modulesRoot = APPPATH . 'Modules';
        if (! is_dir($modulesRoot)) {
            return [];
        }

        $moduleDirs = scandir($modulesRoot);
        if ($moduleDirs === false) {
            return [];
        }

        $rows = [];

        foreach ($moduleDirs as $moduleDir) {
            if ($moduleDir === '.' || $moduleDir === '..') {
                continue;
            }

            $entitiesPath = $modulesRoot . '/' . $moduleDir . '/Entities';
            if (! is_dir($entitiesPath)) {
                continue;
            }

            $moduleName = $this->resolveModuleName($modulesRoot . '/' . $moduleDir, $moduleDir);
            $entityDirs = scandir($entitiesPath);
            if ($entityDirs === false) {
                continue;
            }

            foreach ($entityDirs as $entityDir) {
                if ($entityDir === '.' || $entityDir === '..') {
                    continue;
                }

                if (! is_dir($entitiesPath . '/' . $entityDir)) {
                    continue;
                }

                $rows[] = [
                    'module_name' => $moduleName,
                    'module_dir' => $moduleDir,
                    'entity_name' => TableNameResolver::normalizeIdentifier($entityDir),
                    'entity_dir' => $entityDir,
                ];
            }
        }

        usort(
            $rows,
            static fn (array $left, array $right): int => [$left['module_dir'], $left['entity_dir']] <=> [$right['module_dir'], $right['entity_dir']]
        );

        return $rows;
    }

    private function resolveModuleName(string $modulePath, string $moduleDir): string
    {
        $moduleJsonPath = $modulePath . '/module.json';
        if (is_file($moduleJsonPath)) {
            $payload = json_decode((string) file_get_contents($moduleJsonPath), true);
            if (is_array($payload) && is_string($payload['name'] ?? null) && trim((string) $payload['name']) !== '') {
                return TableNameResolver::normalizeIdentifier((string) $payload['name']);
            }
        }

        return TableNameResolver::normalizeIdentifier($moduleDir);
    }

    private function artifactReason(bool $hasMetadata, bool $hasTable): string
    {
        if (! $hasMetadata && ! $hasTable) {
            return 'artifact exists without metadata and table';
        }

        if (! $hasMetadata) {
            return 'artifact exists without metadata';
        }

        return 'artifact exists but physical table is missing';
    }

    /**
     * @param array<string, string|bool> $candidate
     */
    private function deleteArtifactOrphan(array $candidate): void
    {
        $entityName = (string) $candidate['entity_name'];

        if (($candidate['has_metadata'] ?? false) === true) {
            $this->builderService()->deleteEntity($entityName);

            return;
        }

        $tableName = (string) $candidate['table_name'];
        if (($candidate['has_table'] ?? false) === true) {
            $this->dropTable($tableName);
        }

        $this->artifactScaffolder()->removeEntity((string) $candidate['module_name'], $entityName);
        $this->awesomeBar()->removeEntity($entityName);
        $this->metadataCache()->delete($entityName);
        $this->metadataCompiler()->invalidateEntity($entityName);
    }

    /**
     * @param array<string, string> $candidate
     */
    private function dropTableOrphan(array $candidate): void
    {
        $this->dropTable((string) $candidate['table_name']);
        $entityName = (string) $candidate['entity_name'];
        $this->awesomeBar()->removeEntity($entityName);
        $this->metadataCache()->delete($entityName);
        $this->metadataCompiler()->invalidateEntity($entityName);
    }

    private function dropTable(string $tableName): void
    {
        $tableName = strtolower(trim($tableName));
        if (! preg_match('/^tab_[a-z0-9_]+$/', $tableName)) {
            throw new RuntimeException("Refusing to drop unsafe table identifier: {$tableName}");
        }

        VoltDatabase::connection()->query('DROP TABLE IF EXISTS ' . $tableName);
    }

    /**
     * @param array{
     *   artifact_orphans:list<array<string, string|bool>>,
     *   table_orphans:list<array<string, string>>
     * } $scan
     */
    private function renderScanReport(array $scan): void
    {
        CLI::write('Phát hiện candidate cần dọn:', 'yellow');

        if ($scan['artifact_orphans'] !== []) {
            CLI::newLine();
            CLI::write('Artifact orphans:', 'light_gray');
            foreach ($scan['artifact_orphans'] as $row) {
                CLI::write(sprintf(
                    '  - %s/%s -> entity=%s, table=%s, reason=%s',
                    $row['module_dir'],
                    $row['entity_dir'],
                    $row['entity_name'],
                    $row['table_name'],
                    $row['reason']
                ));
            }
        }

        if ($scan['table_orphans'] !== []) {
            CLI::newLine();
            CLI::write('Table orphans:', 'light_gray');
            foreach ($scan['table_orphans'] as $row) {
                CLI::write(sprintf(
                    '  - %s -> entity=%s, reason=%s',
                    $row['table_name'],
                    $row['entity_name'],
                    $row['reason']
                ));
            }
        }

        CLI::newLine();
    }

    private function shouldDelete(string $question): bool
    {
        $answer = strtolower(trim(CLI::prompt($question, ['n', 'y'])));

        return $answer === 'y';
    }

    private function builderService(): EntityBuilderService
    {
        static $service = null;

        return $service ??= new EntityBuilderService();
    }

    private function artifactScaffolder(): ArtifactScaffolder
    {
        static $service = null;

        return $service ??= new ArtifactScaffolder();
    }

    private function awesomeBar(): AwesomeBarModel
    {
        static $service = null;

        return $service ??= new AwesomeBarModel();
    }

    private function metadataCache(): EntityMetadataCache
    {
        static $service = null;

        return $service ??= new EntityMetadataCache();
    }

    private function metadataCompiler(): VoltMetadataCompiler
    {
        static $service = null;

        return $service ??= new VoltMetadataCompiler();
    }
}
