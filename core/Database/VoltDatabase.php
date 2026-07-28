<?php

declare(strict_types=1);

namespace Volt\Core\Database;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Config as DatabaseConfig;
use CodeIgniter\Database\MigrationRunner;
use Config\Database as AppDatabaseConfig;
use Config\Migrations;

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
                try {
                    return self::tenantConnection($tenant);
                } catch (\Throwable) {
                    return self::fallbackConnection();
                }
            }
            $group = self::defaultGroup();
        }

        return self::resolvedConnection($group);
    }

    private static function resolvedConnection(string $group): BaseConnection
    {
        if (! isset(self::$instances[$group])) {
            self::$instances[$group] = DatabaseConfig::connect($group, true);
        }

        return self::$instances[$group];
    }

    private static function fallbackConnection(): BaseConnection
    {
        return self::resolvedConnection(self::defaultGroup());
    }

    public static function hubConnection(): BaseConnection
    {
        return self::resolvedConnection(self::defaultGroup());
    }

    public static function tenantConnection(string $tenantName): BaseConnection
    {
        $cacheKey = self::TENANT_PREFIX . $tenantName;

        if (isset(self::$instances[$cacheKey])) {
            return self::$instances[$cacheKey];
        }

        try {
            $hub = self::fallbackConnection();
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Cannot connect to hub database to resolve tenant '{$tenantName}': " . $e->getMessage(),
                (int) $e->getCode(),
                $e,
            );
        }

        try {
            $row = $hub
                ->table('sys_tenant')
                ->select('db_host, db_port, db_name, db_username, db_password')
                ->where('name', $tenantName)
                ->where('is_active', 1)
                ->get()
                ->getRowArray();
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Cannot resolve tenant '{$tenantName}': sys_tenant table may not exist yet. Run 'php spark migrate -n Volt\\Core' first. Error: " . $e->getMessage(),
                (int) $e->getCode(),
                $e,
            );
        }

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

    public static function createTenantDatabase(string $dbName, string $dbHost = 'localhost', int $dbPort = 5432): void
    {
        $default = self::getDefaultDbConfig();
        $conn = pg_connect("host={$dbHost} port={$dbPort} dbname=postgres user={$default['username']} password={$default['password']}");

        if ($conn === false) {
            throw new \RuntimeException('Cannot connect to PostgreSQL to create database.');
        }

        $escapedDb = pg_escape_string($conn, $dbName);
        $escapedUser = pg_escape_string($conn, $dbUser);
        $sql = "CREATE DATABASE \"{$escapedDb}\" OWNER \"{$escapedUser}\"";

        $result = pg_query($conn, $sql);

        pg_close($conn);

        if ($result === false) {
            throw new \RuntimeException("Failed to create database '{$dbName}'.");
        }
    }

    public static function dropTenantDatabase(string $dbName, string $dbHost = 'localhost', int $dbPort = 5432): void
    {
        $default = self::getDefaultDbConfig();
        $conn = pg_connect("host={$dbHost} port={$dbPort} dbname=postgres user={$default['username']} password={$default['password']}");

        if ($conn === false) {
            throw new \RuntimeException('Cannot connect to PostgreSQL to drop database.');
        }

        $escapedDb = pg_escape_string($conn, $dbName);
        $sql = "DROP DATABASE IF EXISTS \"{$escapedDb}\"";

        $result = pg_query($conn, $sql);

        pg_close($conn);

        if ($result === false) {
            throw new \RuntimeException("Failed to drop database '{$dbName}'.");
        }
    }

    public static function migrateTenantDatabase(string $dbName, string $dbHost = 'localhost', int $dbPort = 5432, string $dbUser = 'volt_admin', string $dbPassword = ''): void
    {
        $config = [
            'DSN'      => '',
            'hostname' => $dbHost,
            'port'     => $dbPort,
            'username' => $dbUser,
            'password' => $dbPassword,
            'database' => $dbName,
            'DBDriver' => 'Postgre',
            'DBPrefix' => '',
            'pConnect' => false,
            'DBDebug'  => true,
            'charset'  => 'utf8',
            'swapPre'  => '',
            'encrypt'  => false,
            'compress' => false,
            'strictOn' => false,
            'failover' => [],
        ];

        $db = DatabaseConfig::connect($config, true);
        $runner = new MigrationRunner(config(Migrations::class), $db);
        $runner->setNamespace('Volt\Core');
        $runner->latest();
    }

    private static function defaultGroup(): string
    {
        return config(AppDatabaseConfig::class)->defaultGroup;
    }

    private static function getDefaultDbConfig(): array
    {
        $db = config(AppDatabaseConfig::class);
        $group = $db->defaultGroup;
        $conn = $db->{$group};

        return [
            'hostname' => $conn['hostname'] ?? 'localhost',
            'port'     => (int) ($conn['port'] ?? 5432),
            'username' => $conn['username'] ?? 'volt_admin',
            'password' => $conn['password'] ?? '',
        ];
    }
}
