<?php

declare(strict_types=1);

namespace Volt\Core\System\Services;

use CodeIgniter\CodeIgniter;
use Config\Cache as CacheConfig;
use Config\Database as DatabaseConfig;
use Throwable;
use Volt\Core\Config\Lang\LangService;
use Volt\Core\Database\VoltDatabase;
use Volt\Core\System\Services\SystemSettingService;

class SystemStatusService
{
    /**
     * @return array{
     *     generatedAt:string,
     *     overallStatus:string,
     *     summary:array{ok:int,warning:int,error:int,total:int},
     *     checks:array<int, array<string, mixed>>,
     *     environment:array<int, array<string, string>>,
     *     statistics:array<int, array<string, string>>,
     *     extensions:array<int, array<string, string>>,
     *     resources:array<int, array<string, string>>
     * }
     */
    public function getStatusReport(): array
    {
        $this->applySystemTimezone();

        $checks = [
            $this->checkPhpRuntime(),
            $this->checkDatabaseConnection(),
            $this->checkCacheLayer(),
            $this->checkSystemResources(),
            $this->checkWritableDirectories(),
            $this->checkCoreTables(),
        ];

        $summary = $this->summarizeChecks($checks);

        return [
            'generatedAt'   => date('Y-m-d H:i:s'),
            'overallStatus' => $summary['overall'],
            'summary'       => [
                'ok'      => $summary['ok'],
                'warning' => $summary['warning'],
                'error'   => $summary['error'],
                'total'   => $summary['total'],
            ],
            'checks'        => $checks,
            'environment'   => $this->buildEnvironmentDetails(),
            'statistics'    => $this->buildStatistics(),
            'extensions'    => $this->buildExtensionDetails(),
            'resources'     => $this->buildResourceDetails(),
        ];
    }

    /**
     * @param array<int, array{status:string}> $checks
     *
     * @return array{overall:string,ok:int,warning:int,error:int,total:int}
     */
    public function summarizeChecks(array $checks): array
    {
        $summary = [
            'overall' => 'ok',
            'ok'      => 0,
            'warning' => 0,
            'error'   => 0,
            'total'   => count($checks),
        ];

        foreach ($checks as $check) {
            $status = $check['status'] ?? 'warning';

            if (! isset($summary[$status])) {
                $status = 'warning';
            }

            $summary[$status]++;
        }

        if ($summary['error'] > 0) {
            $summary['overall'] = 'error';
        } elseif ($summary['warning'] > 0) {
            $summary['overall'] = 'warning';
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private function checkPhpRuntime(): array
    {
        $requiredVersion = '8.2.0';
        $status = version_compare(PHP_VERSION, $requiredVersion, '>=') ? 'ok' : 'error';

        return [
            'title'       => $this->t('check_php_runtime_title'),
            'status'      => $status,
            'summary'     => $this->t('check_php_runtime_summary', [
                'version' => PHP_VERSION,
                'required' => $requiredVersion,
            ]),
            'details'     => [
                'PHP Version'      => PHP_VERSION,
                'Required Version' => $requiredVersion,
                'SAPI'             => PHP_SAPI,
                'Memory Limit'     => (string) ini_get('memory_limit'),
                'Max Execution'    => (string) ini_get('max_execution_time') . 's',
            ],
            'recommendation' => $status === 'ok'
                ? $this->t('check_php_runtime_recommendation_ok')
                : $this->t('check_php_runtime_recommendation_error'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkDatabaseConnection(): array
    {
        $config = new DatabaseConfig();
        $details = [
            'Default Group' => $config->defaultGroup,
            'Driver'        => (string) ($config->default['DBDriver'] ?? 'unknown'),
            'Host'          => (string) ($config->default['hostname'] ?? ''),
            'Database'      => (string) ($config->default['database'] ?? ''),
        ];

        try {
            $db = VoltDatabase::connection();
            $row = $db->query('SELECT CURRENT_TIMESTAMP AS server_time')->getRowArray();
            $driver = strtoupper((string) ($config->default['DBDriver'] ?? ''));
            $status = $driver === 'POSTGRE' ? 'ok' : 'warning';

            $details['Server Time'] = (string) ($row['server_time'] ?? '');
            $details['Platform'] = $db->getPlatform();

            return [
                'title'       => $this->t('check_database_connection_title'),
                'status'      => $status,
                'summary'     => $this->t('check_database_connection_summary_ok'),
                'details'     => $details,
                'recommendation' => $status === 'ok'
                    ? $this->t('check_database_connection_recommendation_ok')
                    : $this->t('check_database_connection_recommendation_warning'),
            ];
        } catch (Throwable $exception) {
            service('voltErrorLog')->logException($exception, [], 'system_status', 'system_status_database_check_failed');
            return [
                'title'       => $this->t('check_database_connection_title'),
                'status'      => 'error',
                'summary'     => $this->t('check_database_connection_summary_error'),
                'details'     => $details + ['Error' => $exception->getMessage()],
                'recommendation' => $this->t('check_database_connection_recommendation_error'),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkCacheLayer(): array
    {
        $config = new CacheConfig();
        $cache = cache();
        $handlerClass = $cache::class;

        $details = [
            'Configured Handler' => $config->handler,
            'Backup Handler'     => $config->backupHandler,
            'Runtime Handler'    => $handlerClass,
        ];

        try {
            $key = 'system_status_' . bin2hex(random_bytes(8));
            $saved = $cache->save($key, 'ok', 30);
            $value = $cache->get($key);
            $cache->delete($key);

            if (! $saved || $value !== 'ok') {
                return [
                    'title'       => $this->t('check_cache_layer_title'),
                    'status'      => 'warning',
                    'summary'     => $this->t('check_cache_layer_summary_roundtrip_warning'),
                    'details'     => $details,
                    'recommendation' => $this->t('check_cache_layer_recommendation_roundtrip_warning'),
                ];
            }

            $status = $config->handler === 'redis' ? 'ok' : 'warning';
            $summary = $config->handler === 'redis'
                ? $this->t('check_cache_layer_summary_ok')
                : $this->t('check_cache_layer_summary_warning');

            return [
                'title'       => $this->t('check_cache_layer_title'),
                'status'      => $status,
                'summary'     => $summary,
                'details'     => $details,
                'recommendation' => $status === 'ok'
                    ? $this->t('check_cache_layer_recommendation_ok')
                    : $this->t('check_cache_layer_recommendation_warning'),
            ];
        } catch (Throwable $exception) {
            service('voltErrorLog')->logException($exception, [], 'system_status', 'system_status_cache_check_failed');
            return [
                'title'       => $this->t('check_cache_layer_title'),
                'status'      => 'error',
                'summary'     => $this->t('check_cache_layer_summary_error'),
                'details'     => $details + ['Error' => $exception->getMessage()],
                'recommendation' => $this->t('check_cache_layer_recommendation_error'),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkWritableDirectories(): array
    {
        $directories = [
            'writable/cache'    => WRITEPATH . 'cache',
            'writable/logs'     => WRITEPATH . 'logs',
            'writable/session'  => WRITEPATH . 'session',
            'writable/uploads'  => WRITEPATH . 'uploads',
            'writable/debugbar' => WRITEPATH . 'debugbar',
        ];

        $details = [];
        $errors = [];

        foreach ($directories as $label => $path) {
            $state = is_dir($path) && is_writable($path) ? 'Writable' : 'Unavailable';
            $details[$label] = $state;

            if ($state !== 'Writable') {
                $errors[] = $label;
            }
        }

        return [
            'title'       => $this->t('check_writable_directories_title'),
            'status'      => $errors === [] ? 'ok' : 'error',
            'summary'     => $errors === []
                ? $this->t('check_writable_directories_summary_ok')
                : $this->t('check_writable_directories_summary_error'),
            'details'     => $details,
            'recommendation' => $errors === []
                ? $this->t('check_writable_directories_recommendation_ok')
                : $this->t('check_writable_directories_recommendation_error', ['items' => implode(', ', $errors)]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkSystemResources(): array
    {
        $resourceDetails = $this->buildResourceDetails();
        $details = [];

        foreach ($resourceDetails as $item) {
            $details[(string) ($item['label'] ?? '')] = (string) ($item['value'] ?? '');
        }

        $status = 'ok';
        $recommendation = $this->t('check_system_resources_recommendation_ok');

        $oneMinuteLoad = $this->extractNumericPrefix($details[$this->t('resource_cpu_load_1m')] ?? null);
        $memoryUsedPercent = $this->extractPercent($details[$this->t('resource_ram_used')] ?? null);

        if ($oneMinuteLoad !== null && $oneMinuteLoad >= 4.0) {
            $status = 'warning';
            $recommendation = $this->t('check_system_resources_recommendation_cpu_warning');
        }

        if ($memoryUsedPercent !== null && $memoryUsedPercent >= 90.0) {
            $status = 'error';
            $recommendation = $this->t('check_system_resources_recommendation_ram_error');
        } elseif ($memoryUsedPercent !== null && $memoryUsedPercent >= 75.0 && $status !== 'error') {
            $status = 'warning';
            $recommendation = $this->t('check_system_resources_recommendation_ram_warning');
        }

        return [
            'title'          => $this->t('check_system_resources_title'),
            'status'         => $status,
            'summary'        => $this->t('check_system_resources_summary'),
            'details'        => $details,
            'recommendation' => $recommendation,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkCoreTables(): array
    {
        $requiredTables = [
            'sys_entity',
            'sys_entity_field',
            'sys_entity_custom',
            'sys_user',
            'sys_permission',
            'sys_sequence',
            'sys_audit_trail',
            'sys_queue_job',
            'sys_module',
            'sys_role',
            'sys_awesome_bar',
            'sys_error_log',
        ];

        try {
            $db = VoltDatabase::connection();
            $existingTables = array_map('strval', $db->listTables());
            $missingTables = array_values(array_diff($requiredTables, $existingTables));

            return [
                'title'       => $this->t('check_core_tables_title'),
                'status'      => $missingTables === [] ? 'ok' : 'error',
                'summary'     => $missingTables === []
                    ? $this->t('check_core_tables_summary_ok')
                    : $this->t('check_core_tables_summary_error'),
                'details'     => [
                    $this->t('detail_required_tables') => (string) count($requiredTables),
                    $this->t('detail_detected_tables') => (string) count($existingTables),
                    $this->t('detail_missing_tables')  => $missingTables === [] ? $this->t('none') : implode(', ', $missingTables),
                ],
                'recommendation' => $missingTables === []
                    ? $this->t('check_core_tables_recommendation_ok')
                    : $this->t('check_core_tables_recommendation_error'),
            ];
        } catch (Throwable $exception) {
            service('voltErrorLog')->logException($exception, [], 'system_status', 'system_status_core_tables_check_failed');
            return [
                'title'       => $this->t('check_core_tables_title'),
                'status'      => 'error',
                'summary'     => $this->t('check_core_tables_summary_exception'),
                'details'     => ['Error' => $exception->getMessage()],
                'recommendation' => $this->t('check_core_tables_recommendation_exception'),
            ];
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildEnvironmentDetails(): array
    {
        $cacheConfig = new CacheConfig();
        $databaseConfig = new DatabaseConfig();

        try {
            $tz = service('voltSystemSetting')->getTimezone();
        } catch (Throwable $throwable) {
            service('voltErrorLog')->logException($throwable, [], 'system_status', 'system_status_environment_timezone_failed');
            $tz = date_default_timezone_get();
        }

        return [
            ['label' => 'Volt', 'value' => 'Core Workspace Build'],
            ['label' => 'CodeIgniter', 'value' => CodeIgniter::CI_VERSION],
            ['label' => 'PHP', 'value' => PHP_VERSION],
            ['label' => 'Environment', 'value' => ENVIRONMENT],
            ['label' => 'Default DB Driver', 'value' => (string) ($databaseConfig->default['DBDriver'] ?? 'unknown')],
            ['label' => 'Configured Cache', 'value' => $cacheConfig->handler],
            ['label' => 'Timezone (System)', 'value' => $tz],
            ['label' => 'Timezone (PHP)', 'value' => (string) date_default_timezone_get()],
        ];
    }

    private function applySystemTimezone(): void
    {
        try {
            $tz = service('voltSystemSetting')->getTimezone();
            if ($tz !== '' && $tz !== 'UTC') {
                date_default_timezone_set($tz);
            }
        } catch (Throwable $throwable) {
            service('voltErrorLog')->logException($throwable, [], 'system_status', 'system_status_apply_timezone_failed');
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildStatistics(): array
    {
        $stats = [
            ['label' => 'Modules', 'value' => 'n/a'],
            ['label' => 'Entities', 'value' => 'n/a'],
            ['label' => 'Users', 'value' => 'n/a'],
            ['label' => 'Roles', 'value' => 'n/a'],
            ['label' => 'Queue Jobs', 'value' => 'n/a'],
        ];

        try {
            $db = VoltDatabase::connection();

            $stats = [
                ['label' => 'Modules', 'value' => (string) $db->table('sys_module')->countAllResults()],
                ['label' => 'Entities', 'value' => (string) $db->table('sys_entity')->countAllResults()],
                ['label' => 'Users', 'value' => (string) $db->table('sys_user')->countAllResults()],
                ['label' => 'Roles', 'value' => (string) $db->table('sys_role')->countAllResults()],
                ['label' => 'Queue Jobs', 'value' => (string) $db->table('sys_queue_job')->countAllResults()],
            ];
        } catch (Throwable $throwable) {
            service('voltErrorLog')->logException($throwable, [], 'system_status', 'system_status_statistics_failed');
        }

        return $stats;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildExtensionDetails(): array
    {
        $extensions = ['pgsql', 'redis', 'mbstring', 'json'];
        $details = [];

        foreach ($extensions as $extension) {
            $details[] = [
                'label' => $extension,
                'value' => extension_loaded($extension) ? $this->t('loaded') : $this->t('missing'),
            ];
        }

        return $details;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildResourceDetails(): array
    {
        $memory = $this->readMemoryInfo();
        $load = sys_getloadavg();

        $items = [
            [
                'label' => $this->t('resource_cpu_load_1m'),
                'value' => is_array($load) && isset($load[0]) ? number_format((float) $load[0], 2) : $this->t('not_available'),
            ],
            [
                'label' => $this->t('resource_cpu_load_5m'),
                'value' => is_array($load) && isset($load[1]) ? number_format((float) $load[1], 2) : $this->t('not_available'),
            ],
            [
                'label' => $this->t('resource_cpu_load_15m'),
                'value' => is_array($load) && isset($load[2]) ? number_format((float) $load[2], 2) : $this->t('not_available'),
            ],
            [
                'label' => $this->t('resource_php_memory_usage'),
                'value' => $this->formatBytes(memory_get_usage(true)),
            ],
            [
                'label' => $this->t('resource_php_peak_memory'),
                'value' => $this->formatBytes(memory_get_peak_usage(true)),
            ],
        ];

        if ($memory !== null) {
            $usedBytes = max(0, $memory['total'] - $memory['available']);
            $usedPercent = $memory['total'] > 0 ? ($usedBytes / $memory['total']) * 100 : 0.0;

            $items[] = [
                'label' => $this->t('resource_ram_total'),
                'value' => $this->formatBytes($memory['total']),
            ];
            $items[] = [
                'label' => $this->t('resource_ram_available'),
                'value' => $this->formatBytes($memory['available']),
            ];
            $items[] = [
                'label' => $this->t('resource_ram_used'),
                'value' => $this->formatBytes($usedBytes) . ' (' . number_format($usedPercent, 1) . '%)',
            ];
        } else {
            $items[] = [
                'label' => $this->t('resource_ram_total'),
                'value' => $this->t('not_available'),
            ];
            $items[] = [
                'label' => $this->t('resource_ram_available'),
                'value' => $this->t('not_available'),
            ];
            $items[] = [
                'label' => $this->t('resource_ram_used'),
                'value' => $this->t('not_available'),
            ];
        }

        return $items;
    }

    private function t(string $key, array $params = []): string
    {
        return LangService::get('system_status_page.' . $key, $params);
    }

    /**
     * @return array{total:int,available:int}|null
     */
    private function readMemoryInfo(): ?array
    {
        $path = '/proc/meminfo';

        if (! is_readable($path)) {
            return null;
        }

        $contents = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if (! is_array($contents)) {
            return null;
        }

        $values = [];

        foreach ($contents as $line) {
            if (! preg_match('/^([A-Za-z_]+):\s+(\d+)\s+kB$/', $line, $matches)) {
                continue;
            }

            $values[$matches[1]] = (int) $matches[2] * 1024;
        }

        if (! isset($values['MemTotal'])) {
            return null;
        }

        $available = $values['MemAvailable'] ?? ($values['MemFree'] ?? 0);

        return [
            'total'     => (int) $values['MemTotal'],
            'available' => (int) $available,
        ];
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $unitIndex = 0;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        return number_format($value, $unitIndex === 0 ? 0 : 2) . ' ' . $units[$unitIndex];
    }

    private function extractNumericPrefix(?string $value): ?float
    {
        if ($value === null || ! preg_match('/^\s*([0-9]+(?:\.[0-9]+)?)/', $value, $matches)) {
            return null;
        }

        return (float) $matches[1];
    }

    private function extractPercent(?string $value): ?float
    {
        if ($value === null || ! preg_match('/\(([0-9]+(?:\.[0-9]+)?)%\)/', $value, $matches)) {
            return null;
        }

        return (float) $matches[1];
    }
}
