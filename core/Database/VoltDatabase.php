<?php

declare(strict_types=1);

namespace Volt\Core\Database;

use CodeIgniter\Database\BaseConnection;
use Config\Database as DatabaseConfig;

final class VoltDatabase
{
    /**
     * Shared database connections keyed by group name.
     *
     * @var array<string, BaseConnection>
     */
    private static array $instances = [];

    private function __construct()
    {
    }

    /**
     * Get a shared database connection for the requested group.
     */
    public static function connection(?string $group = null): BaseConnection
    {
        $resolvedGroup = $group ?? self::defaultGroup();

        if (! isset(self::$instances[$resolvedGroup])) {
            self::$instances[$resolvedGroup] = DatabaseConfig::connect($resolvedGroup, true);
        }

        return self::$instances[$resolvedGroup];
    }

    /**
     * Clear cached connections, useful for tests or runtime reconfiguration.
     */
    public static function reset(): void
    {
        self::$instances = [];
    }

    private static function defaultGroup(): string
    {
        return (new DatabaseConfig())->defaultGroup;
    }
}
