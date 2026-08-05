<?php

declare(strict_types=1);

namespace Volt\Core\Database;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Config as DatabaseConfig;
use CodeIgniter\Database\MigrationRunner;
use Config\Database as AppDatabaseConfig;
use Config\Migrations;
use Throwable;
use Volt\Core\Audit\AuditTrailWriter;
use Volt\Core\Audit\RequestContext;

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

        if (self::databaseExists($dbName, $dbHost, $dbPort, $default)) {
            return;
        }

        $sql = sprintf(
            'CREATE DATABASE "%s" OWNER "%s"',
            str_replace('"', '""', $dbName),
            str_replace('"', '""', $default['username']),
        );
        $cmd = sprintf(
            'PGPASSWORD=%s psql -U %s -h %s -p %d -d postgres -c %s 2>&1',
            escapeshellarg($default['password']),
            escapeshellarg($default['username']),
            escapeshellarg($dbHost),
            $dbPort,
            escapeshellarg($sql),
        );

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new \RuntimeException(implode("\n", $output));
        }

        self::auditHubEvent('tenant:db_created', $dbName, ['db_host' => $dbHost, 'db_port' => $dbPort]);
    }

    public static function dropTenantDatabase(string $dbName, string $dbHost = 'localhost', int $dbPort = 5432): void
    {
        self::$instances = [];

        $default = self::getDefaultDbConfig();
        $sql = sprintf(
            'DROP DATABASE IF EXISTS "%s" WITH (FORCE)',
            str_replace('"', '""', $dbName),
        );
        $cmd = sprintf(
            'PGPASSWORD=%s psql -U %s -h %s -p %d -d postgres -c %s 2>&1',
            escapeshellarg($default['password']),
            escapeshellarg($default['username']),
            escapeshellarg($dbHost),
            $dbPort,
            escapeshellarg($sql),
        );

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new \RuntimeException(implode("\n", $output));
        }

        self::auditHubEvent('tenant:db_dropped', $dbName, ['db_host' => $dbHost, 'db_port' => $dbPort]);
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

        self::auditHubEvent('tenant:db_migrated', $dbName, ['db_host' => $dbHost, 'db_port' => $dbPort]);
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

    private static function auditHubEvent(string $action, string $tenantName, array $after = []): void
    {
        try {
            $db = self::hubConnection();
            $actor = 'system';

            if (function_exists('service')) {
                try {
                    $actor = service('voltAuth')->currentUser()?->name ?? 'system';
                } catch (Throwable) {
                }
            }

            (new AuditTrailWriter($db))->write(
                AuditTrailWriter::CAT_TENANT,
                $action,
                'sys_tenant',
                $tenantName,
                [],
                $after,
                $actor,
                ['tenant' => $tenantName, 'request_id' => RequestContext::requestId()],
            );
        } catch (Throwable) {
            // Không làm hỏng luồng tạo/xóa DB nếu ghi audit gặp sự cố
        }
    }

    private static function databaseExists(string $dbName, string $dbHost, int $dbPort, array $default): bool
    {
        $dsn = sprintf(
            'host=%s port=%d dbname=postgres user=%s password=%s',
            $dbHost,
            $dbPort,
            $default['username'],
            $default['password'],
        );

        $conn = @pg_connect($dsn);

        if ($conn === false) {
            return false;
        }

        $escapedDb = pg_escape_string($conn, $dbName);
        $r = @pg_query($conn, "SELECT 1 FROM pg_database WHERE datname = '{$escapedDb}'");

        if ($r === false) {
            pg_close($conn);

            return false;
        }

        $exists = pg_fetch_row($r) !== false;
        pg_close($conn);

        return $exists;
    }
}
