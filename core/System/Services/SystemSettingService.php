<?php

declare(strict_types=1);

namespace Volt\Core\System\Services;

use Throwable;
use Volt\Core\Database\VoltDatabase;

class SystemSettingService
{
    private const TABLE = 'sys_setting';

    private static ?array $cache = null;

    public function get(string $key, string $default = ''): string
    {
        $all = $this->all();

        return $all[$key] ?? $default;
    }

    public function set(string $key, string $value): void
    {
        self::$cache[$key] = $value;

        try {
            $db = VoltDatabase::connection();
            $existing = $db->table(self::TABLE)->where('key', $key)->get()->getRowArray();

            if ($existing) {
                $db->table(self::TABLE)->where('key', $key)->update([
                    'value'      => $value,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            } else {
                $db->table(self::TABLE)->insert([
                    'key'         => $key,
                    'value'       => $value,
                    'type'        => 'string',
                    'updated_at'  => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (Throwable $throwable) {
            service('voltErrorLog')->logException($throwable, ['key' => $key], 'system_setting', 'system_setting_set_failed');
        }
    }

    private const DEFAULTS = [
        'language' => 'en',
        'timezone' => 'UTC',
    ];

    public function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        self::$cache = self::DEFAULTS;

        try {
            $db = VoltDatabase::connection();
            $rows = $db->table(self::TABLE)->get()->getResultArray();

            foreach ($rows as $row) {
                self::$cache[(string) $row['key']] = (string) ($row['value'] ?? '');
            }
        } catch (Throwable $throwable) {
            service('voltErrorLog')->logException($throwable, [], 'system_setting', 'system_setting_all_failed');
        }

        return self::$cache;
    }

    public function getLanguage(): string
    {
        return $this->get('language', 'en');
    }

    public function getTimezone(): string
    {
        return $this->get('timezone', 'UTC');
    }

    public function clearCache(): void
    {
        self::$cache = null;
    }
}
