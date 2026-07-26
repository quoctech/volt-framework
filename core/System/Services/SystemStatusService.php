<?php

declare(strict_types=1);

namespace Volt\Core\System\Services;

use Volt\Core\Config\Lang\LangService;

class SystemStatusService
{
    public function getStatusReport(): array
    {
        $this->applySystemTimezone();

        return [
            'generatedAt' => date('Y-m-d H:i:s'),
            'phpVersion'  => PHP_VERSION,
            'extensions'  => $this->buildExtensionDetails(),
            'resources'   => $this->buildResourceDetails(),
        ];
    }

    private function applySystemTimezone(): void
    {
        try {
            $tz = service('voltSystemSetting')->getTimezone();
            if ($tz !== '' && $tz !== 'UTC') {
                date_default_timezone_set($tz);
            }
        } catch (\Throwable $throwable) {
            service('voltErrorLog')->logException($throwable, [], 'system_status', 'system_status_apply_timezone_failed');
        }
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
}
