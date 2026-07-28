<?php

declare(strict_types=1);

namespace Volt\Core\Database;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Config as DatabaseConfig;
use Config\Database as AppDatabaseConfig;

final class VoltDatabase
{
    public const TENANT_SESSION_KEY = 'tenant';
    private const TENANT_PREFIX = 'tenant_';

    private static array $instances = [];

    private function __construct()
    {
    }

    public static function connection(?string $group = null): BaseConnection
    {
        if ($group === null) {
            $tenant = self::resolveTenant(null);
            if ($tenant !== null) {
                return self::tenantConnection($tenant);
            }
            $group = self::defaultGroup();
        }

        if (! isset(self::$instances[$group])) {
            self::$instances[$group] = DatabaseConfig::connect($group, true);
        }

        return self::$instances[$group];
    }

    public static function tenantConnection(string $tenantName): BaseConnection
    {
        $cacheKey = self::TENANT_PREFIX . $tenantName;

        if (isset(self::$instances[$cacheKey])) {
            return self::$instances[$cacheKey];
        }

        $row = self::connection()
            ->table('sys_tenant')
            ->select('db_host, db_port, db_name, db_username, db_password')
            ->where('name', $tenantName)
            ->where('is_active', 1)
            ->get()
            ->getRowArray();

        if ($row === null) {
            throw new \RuntimeException("Tenant '{$tenantName}' not found or inactive.");
        }

        $config = [
            'DSN'         => '',
            'hostname'    => (string) ($row['db_host'] ?? 'localhost'),
            'port'        => (int) ($row['db_port'] ?? 5432),
            'username'    => (string) ($row['db_username'] ?? 'volt_admin'),
            'password'    => (string) ($row['db_password'] ?? ''),
            'database'    => (string) ($row['db_name'] ?? ''),
            'DBDriver'    => 'Postgre',
            'DBPrefix'    => '',
            'pConnect'    => false,
            'DBDebug'     => true,
            'charset'     => 'utf8',
            'DBCollat'    => 'utf8_general_ci',
            'swapPre'     => '',
            'encrypt'     => false,
            'compress'    => false,
            'strictOn'    => false,
            'failover'    => [],
        ];

        self::$instances[$cacheKey] = DatabaseConfig::connect($config, true);

        return self::$instances[$cacheKey];
    }

    public static function resolveTenant(?string $tenantName): ?string
    {
        $tenant = $tenantName ?? session(self::TENANT_SESSION_KEY);
        if ($tenant === null || $tenant === '') {
            return null;
        }
        return $tenant;
    }

    public static function reset(): void
    {
        self::$instances = [];
    }

    private static function defaultGroup(): string
    {
        return config(AppDatabaseConfig::class)->defaultGroup;
    }
}
