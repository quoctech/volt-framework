<?php

declare(strict_types=1);

namespace Volt\Core\Engine;

use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Database\BaseConnection;
use Config\Services;
use InvalidArgumentException;
use RuntimeException;
use Volt\Core\Database\VoltDatabase;

final class VoltMetadataCompiler
{
    private const CACHE_VERSION = 'v1';
    private const INDEX_KEY_PREFIX = 'volt:metadata:index:';
    private const ENTITY_KEY_PREFIX = 'volt:metadata:entity:';

    private BaseConnection $db;
    private CacheInterface $cache;
    private int $cacheTtl;

    public function __construct(?BaseConnection $db = null, ?CacheInterface $cache = null)
    {
        $this->db = $db ?? VoltDatabase::connection();
        $this->cache = $cache ?? Services::cache();
        $this->cacheTtl = (int) env('volt.metadata.cacheTtl', 86400);
    }

    /**
     * Compile one entity from sys_entity, sys_entity_field and sys_entity_custom.
     *
     * @return array<string, mixed>
     */
    public function compileEntity(string $entityName, ?string $role = null, bool $forceRefresh = false): array
    {
        $cacheKey = $this->entityCacheKey($entityName, $role);

        if (! $forceRefresh) {
            $cached = $this->cache->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $entity = $this->db->table('sys_entity')
            ->where('name', $entityName)
            ->get()
            ->getRowArray();

        if (! is_array($entity) || $entity === []) {
            throw new InvalidArgumentException("Entity not found: {$entityName}");
        }

        $fields = $this->db->table('sys_entity_field')
            ->where('parent', $entityName)
            ->orderBy('idx', 'ASC')
            ->get()
            ->getResultArray();

        $customRows = $this->db->table('sys_entity_custom')
            ->where('entity_name', $entityName)
            ->groupStart()
                ->where('apply_to_role', null)
                ->orWhere('apply_to_role', $role)
            ->groupEnd()
            ->orderBy('apply_to_role', 'ASC')
            ->get()
            ->getResultArray();

        $base = $this->buildBaseConfig($entity, $fields);
        $mergedCustom = [];
        $patchSources = [];

        foreach ($customRows as $customRow) {
            $customMeta = $this->normalizeCustomMeta($customRow['custom_meta'] ?? []);
            $patchSources[] = [
                'apply_to_role' => $customRow['apply_to_role'] ?? null,
                'custom_meta'   => $customMeta,
            ];
            $mergedCustom = $this->deepPatch($mergedCustom, $customMeta);
        }

        $compiled = $this->deepPatch($base, $mergedCustom);
        $compiled['cache'] = [
            'key'       => $cacheKey,
            'ttl'       => $this->cacheTtl,
            'compiledAt'=> gmdate('c'),
            'role'      => $role,
        ];
        $compiled['source'] = [
            'entity'     => $entity,
            'fields'     => $fields,
            'customRows' => $patchSources,
        ];
        $compiled['derived'] = $this->buildDerivedIndexes($compiled);

        if (! $this->cache->save($cacheKey, $compiled, $this->cacheTtl)) {
            throw new RuntimeException("Failed to save metadata cache for {$entityName}");
        }

        $this->rememberIndex($entityName, $role, $cacheKey);

        return $compiled;
    }

    /**
     * Warm metadata cache for all entities.
     *
     * @return array{total:int,warmed:int,failed:int,errors:array<int,array<string,mixed>>}
     */
    public function warmAll(?string $role = null, bool $forceRefresh = false): array
    {
        $entities = $this->db->table('sys_entity')
            ->select('name')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        $summary = [
            'total' => count($entities),
            'warmed' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($entities as $entity) {
            try {
                $this->compileEntity((string) $entity['name'], $role, $forceRefresh);
                $summary['warmed']++;
            } catch (\Throwable $throwable) {
                $summary['failed']++;
                $summary['errors'][] = [
                    'entity' => $entity['name'] ?? null,
                    'message' => $throwable->getMessage(),
                ];
            }
        }

        return $summary;
    }

    /**
     * Invalidate one entity cache entry or the whole index for that entity.
     */
    public function invalidateEntity(string $entityName, ?string $role = null): bool
    {
        $indexKey = $this->indexKey($entityName);
        $index = $this->cache->get($indexKey);

        $deleted = false;

        if (is_array($index)) {
            $cacheKeys = $this->resolveIndexedKeys($index, $role);
            foreach ($cacheKeys as $cacheKey) {
                $deleted = $this->cache->delete($cacheKey) || $deleted;
            }
        } else {
            $deleted = $this->cache->delete($this->entityCacheKey($entityName, $role)) || $deleted;
        }

        $deleted = $this->cache->delete($indexKey) || $deleted;

        return $deleted;
    }

    /**
     * @param array<string, mixed> $entity
     * @param array<int, array<string, mixed>> $fields
     *
     * @return array<string, mixed>
     */
    private function buildBaseConfig(array $entity, array $fields): array
    {
        $fieldMap = [];
        $mainFields = [];
        $childFields = [];

        foreach ($fields as $field) {
            $normalized = $this->normalizeFieldRow($field);
            $fieldMap[$normalized['fieldname']] = $normalized;

            if ($normalized['is_child_table']) {
                $childFields[] = $normalized['fieldname'];
                continue;
            }

            $mainFields[] = $normalized['fieldname'];
        }

        return [
            'entity' => $this->normalizeEntityRow($entity),
            'fields' => $fieldMap,
            'field_order' => array_keys($fieldMap),
            'main_fields' => $mainFields,
            'child_fields' => $childFields,
        ];
    }

    /**
     * @param array<string, mixed> $field
     *
     * @return array<string, mixed>
     */
    private function normalizeFieldRow(array $field): array
    {
        $fieldname = (string) ($field['fieldname'] ?? '');
        $options = (string) ($field['options'] ?? '');
        $isChildTable = $this->isChildTable($field);

        return [
            'id' => isset($field['id']) ? (int) $field['id'] : null,
            'parent' => (string) ($field['parent'] ?? ''),
            'fieldname' => $fieldname,
            'label' => (string) ($field['label'] ?? ''),
            'fieldtype' => (string) ($field['fieldtype'] ?? ''),
            'length' => isset($field['length']) ? (int) $field['length'] : null,
            'options' => $options,
            'reqd' => (int) ($field['reqd'] ?? 0),
            'read_only' => (int) ($field['read_only'] ?? 0),
            'hidden' => (int) ($field['hidden'] ?? 0),
            'idx' => (int) ($field['idx'] ?? 0),
            'is_child_table' => $isChildTable,
            'storage_mode' => $isChildTable ? 'separate_table' : 'embedded_jsonb',
        ];
    }

    /**
     * @param array<string, mixed> $entity
     *
     * @return array<string, mixed>
     */
    private function normalizeEntityRow(array $entity): array
    {
        return [
            'name' => (string) ($entity['name'] ?? ''),
            'module' => (string) ($entity['module'] ?? ''),
            'issingle' => (int) ($entity['issingle'] ?? 0),
            'istable' => (int) ($entity['istable'] ?? 0),
            'autoname' => (string) ($entity['autoname'] ?? ''),
            'states' => $this->normalizeJsonValue($entity['states'] ?? []),
            'custom_attributes' => $this->normalizeJsonValue($entity['custom_attributes'] ?? []),
        ];
    }

    /**
     * @param mixed $customMeta
     *
     * @return array<string, mixed>
     */
    private function normalizeCustomMeta(mixed $customMeta): array
    {
        return $this->normalizeJsonValue($customMeta);
    }

    /**
     * @param mixed $value
     *
     * @return array<string, mixed>
     */
    private function normalizeJsonValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * Deep merge associative arrays while replacing list values.
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $patch
     *
     * @return array<string, mixed>
     */
    private function deepPatch(array $base, array $patch): array
    {
        foreach ($patch as $key => $value) {
            if (
                is_array($value)
                && isset($base[$key])
                && is_array($base[$key])
                && ! array_is_list($value)
                && ! array_is_list($base[$key])
            ) {
                $base[$key] = $this->deepPatch($base[$key], $value);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * Build fast lookup indexes for the compiled payload.
     *
     * @param array<string, mixed> $compiled
     *
     * @return array<string, mixed>
     */
    private function buildDerivedIndexes(array $compiled): array
    {
        $fieldMap = $compiled['fields'] ?? [];
        $derived = [
            'main_field_count' => count($compiled['main_fields'] ?? []),
            'child_field_count' => count($compiled['child_fields'] ?? []),
            'field_names' => array_keys(is_array($fieldMap) ? $fieldMap : []),
        ];

        if (is_array($fieldMap)) {
            $derived['required_fields'] = [];
            $derived['hidden_fields'] = [];
            $derived['read_only_fields'] = [];

            foreach ($fieldMap as $fieldname => $field) {
                if (($field['reqd'] ?? 0) === 1) {
                    $derived['required_fields'][] = $fieldname;
                }

                if (($field['hidden'] ?? 0) === 1) {
                    $derived['hidden_fields'][] = $fieldname;
                }

                if (($field['read_only'] ?? 0) === 1) {
                    $derived['read_only_fields'][] = $fieldname;
                }
            }
        }

        return $derived;
    }

    private function isChildTable(array $field): bool
    {
        if (($field['fieldtype'] ?? null) !== 'Table') {
            return false;
        }

        return str_contains((string) ($field['options'] ?? ''), 'separate');
    }

    private function entityCacheKey(string $entityName, ?string $role = null): string
    {
        $segment = $this->sanitizeCacheSegment($entityName);
        $roleSegment = $role === null || $role === '' ? 'global' : $this->sanitizeCacheSegment($role);

        return self::ENTITY_KEY_PREFIX . self::CACHE_VERSION . ':' . $segment . ':' . $roleSegment;
    }

    private function indexKey(string $entityName): string
    {
        return self::INDEX_KEY_PREFIX . self::CACHE_VERSION . ':' . $this->sanitizeCacheSegment($entityName);
    }

    private function rememberIndex(string $entityName, ?string $role, string $cacheKey): void
    {
        $indexKey = $this->indexKey($entityName);
        $index = $this->cache->get($indexKey);

        if (! is_array($index)) {
            $index = [
                'global' => null,
                'roles' => [],
            ];
        }

        if ($role === null || $role === '') {
            $index['global'] = $cacheKey;
        } else {
            $index['roles'][$this->sanitizeCacheSegment($role)] = $cacheKey;
        }

        $this->cache->save($indexKey, $index, $this->cacheTtl);
    }

    /**
     * @param array<string, mixed> $index
     *
     * @return array<int, string>
     */
    private function resolveIndexedKeys(array $index, ?string $role = null): array
    {
        if ($role === null || $role === '') {
            $keys = [];

            if (isset($index['global']) && is_string($index['global'])) {
                $keys[] = $index['global'];
            }

            if (isset($index['roles']) && is_array($index['roles'])) {
                foreach ($index['roles'] as $cacheKey) {
                    if (is_string($cacheKey)) {
                        $keys[] = $cacheKey;
                    }
                }
            }

            return array_values(array_unique($keys));
        }

        $roleKey = $this->sanitizeCacheSegment($role);
        if (isset($index['roles'][$roleKey]) && is_string($index['roles'][$roleKey])) {
            return [$index['roles'][$roleKey]];
        }

        return [];
    }

    private function sanitizeCacheSegment(string $value): string
    {
        $clean = strtolower(trim($value));
        $clean = preg_replace('/[^a-z0-9:_-]+/', '_', $clean);

        return $clean === '' ? 'default' : $clean;
    }
}
