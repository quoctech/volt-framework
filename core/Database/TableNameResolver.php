<?php

declare(strict_types=1);

namespace Volt\Core\Database;

final class TableNameResolver
{
    public const SYSTEM_PREFIX = 'sys_';
    public const ENTITY_PREFIX = 'tab_';

    public static function system(string $tableName): string
    {
        return self::SYSTEM_PREFIX . self::normalizeIdentifier($tableName);
    }

    public static function entity(string $entityName): string
    {
        return self::ENTITY_PREFIX . self::normalizeIdentifier($entityName);
    }

    public static function legacyEntity(string $entityName): string
    {
        return self::normalizeIdentifier($entityName);
    }

    public static function normalizeIdentifier(string $value): string
    {
        $value = preg_replace('/(?<!^)[A-Z]/', '_$0', $value) ?? $value;
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_]+/', '_', $value) ?? '';
        $value = preg_replace('/_+/', '_', $value) ?? '';

        return trim($value, '_');
    }
}
