<?php

declare(strict_types=1);

namespace Volt\Core\Engine;

use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Database\BaseConnection;
use Config\Services;
use InvalidArgumentException;
use RuntimeException;
use Volt\Core\Database\VoltDatabase;
use Volt\Core\Engine\WorkflowEngine;
use Volt\Core\Validation\MetadataValidator;

final class VoltMetadataCompiler
{
    private const CACHE_VERSION = 'v1';
    private const INDEX_KEY_PREFIX = 'volt_metadata_index_';
    private const ENTITY_KEY_PREFIX = 'volt_metadata_entity_';

    private readonly BaseConnection $db;
    private readonly CacheInterface $cache;
    private readonly MetadataValidator $validator;
    private readonly WorkflowEngine $workflowEngine;
    private readonly int $cacheTtl;

    public function __construct(?BaseConnection $db = null, ?CacheInterface $cache = null)
    {
        $this->db = $db ?? VoltDatabase::connection();
        $this->cache = $cache ?? Services::cache();
        $this->validator = new MetadataValidator();
        $this->workflowEngine = new WorkflowEngine($this->db);
        $this->cacheTtl = (int) env('volt.metadata.cacheTtl', 86400);
    }

    /**
     * Compile one entity from sys_entity, sys_entity_field and sys_entity_custom.
     *
     * @return array<string, mixed>
     */
    public function compileEntity(string $entityName, ?string $role = null, bool $forceRefresh = false): array
    {
        $entityName = $this->validator->assertEntityName($entityName);
        $cacheEntityName = self::normalizeEntityName($entityName);
        $cacheKey = $this->entityCacheKey($cacheEntityName, $role);

        if (! $forceRefresh) {
            $cached = $this->cache->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $entity = $this->db->table('sys_entity')
            ->where('LOWER(name)', self::normalizeEntityName($entityName))
            ->get()
            ->getRowArray();

        if (! is_array($entity) || $entity === []) {
            throw new InvalidArgumentException("Entity not found: {$entityName}");
        }

        $entityName = (string) ($entity['name'] ?? $entityName);

        $fields = $this->db->table('sys_entity_field')
            ->where('LOWER(parent)', self::normalizeEntityName($entityName))
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
                service('voltErrorLog')->logException($throwable, [
                    'entity' => $entity['name'] ?? null,
                    'role' => $role,
                ], 'metadata_compiler', 'metadata_compiler_warm_failed');
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
        $childTables = [];

        foreach ($fields as $field) {
            $normalized = $this->validator->normalizeFieldRow($field);
            $fieldMap[$normalized['fieldname']] = $normalized;

            if ($normalized['is_child_table']) {
                $childFields[] = $normalized['fieldname'];
                $childTables[$normalized['fieldname']] = [
                    'child_entity' => $this->parseChildEntityName($normalized['options'] ?? ''),
                    'storage' => $normalized['storage_mode'] ?? 'separate_table',
                ];
                continue;
            }

            $mainFields[] = $normalized['fieldname'];
        }

        $entityName = (string) ($entity['name'] ?? '');
        $workflow = $this->workflowEngine->getWorkflow($entityName);
        $isSubmittable = $this->workflowEngine->isSubmittable($entityName);

        return [
            'entity' => $this->validator->normalizeEntityRow($entity),
            'fields' => $fieldMap,
            'field_order' => array_keys($fieldMap),
            'main_fields' => $mainFields,
            'child_fields' => $childFields,
            'child_tables' => $childTables,
            'workflow' => [
                'active' => $workflow !== null,
                'is_submittable' => $isSubmittable,
                'name' => $workflow['name'] ?? null,
                'label' => $workflow['label'] ?? null,
                'states' => $workflow['states'] ?? [],
                'states_order' => $workflow['states_order'] ?? [],
            ],
        ];
    }

    private function parseChildEntityName(string $options): string
    {
        $parts = explode(':', $options);
        $name = mb_trim($parts[0]);

        $name = preg_replace('/[^a-zA-Z0-9_]/', '', $name) ?? '';
        $name = strtolower($name);

        return $name !== '' ? $name : '';
    }

    private function normalizeCustomMeta(mixed $customMeta): array
    {
        return $this->validator->normalizeCustomMeta($customMeta);
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

    private function entityCacheKey(string $entityName, ?string $role = null): string
    {
        $segment = $this->sanitizeCacheSegment($entityName);
        $roleSegment = $role === null || $role === '' ? 'global' : $this->sanitizeCacheSegment($role);

        return self::ENTITY_KEY_PREFIX . self::CACHE_VERSION . '_' . $segment . '_' . $roleSegment;
    }

    private function indexKey(string $entityName): string
    {
        return self::INDEX_KEY_PREFIX . self::CACHE_VERSION . '_' . $this->sanitizeCacheSegment($entityName);
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

    private static function normalizeEntityName(string $name): string
    {
        $name = preg_replace('/(?<!^)[A-Z]/', '_$0', $name) ?? $name;
        $name = mb_strtolower(mb_trim($name));
        $name = preg_replace('/[^a-z0-9_]+/', '_', $name) ?? '';
        $name = preg_replace('/_+/', '_', $name) ?? '';
        return mb_trim($name, '_');
    }

    private function sanitizeCacheSegment(string $value): string
    {
        $clean = mb_strtolower(mb_trim($value));
        $clean = preg_replace('/[^a-z0-9_-]+/', '_', $clean);
        $clean = preg_replace('/_+/', '_', $clean);
        $clean = mb_trim($clean, '_');

        return $clean === '' ? 'default' : $clean;
    }
}
