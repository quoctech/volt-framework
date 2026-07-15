<?php

declare(strict_types=1);

namespace Volt\Core\Security;

use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Database\BaseConnection;
use Config\Services;
use Volt\Core\Auth\Entities\UserEntity;
use Volt\Core\Auth\Services\AuthService;
use Volt\Core\Database\VoltDatabase;

final class PermissionResolver
{
    private const CACHE_VERSION = 'v1';
    private const CACHE_PREFIX = 'volt_permission_matrix_';

    private BaseConnection $db;
    private CacheInterface $cache;
    private AuthService $authService;
    private int $cacheTtl;

    public function __construct(?BaseConnection $db = null, ?CacheInterface $cache = null, ?AuthService $authService = null)
    {
        $this->db = $db ?? VoltDatabase::connection();
        $this->cache = $cache ?? Services::cache();
        $this->authService = $authService ?? service('voltAuth');
        $this->cacheTtl = (int) env('volt.permission.cacheTtl', 86400);
    }

    public function can(string $entity, string $action, ?string $state = null, ?string $field = null, ?UserEntity $user = null): bool
    {
        $entity = trim($entity);
        $action = trim($action);

        if ($entity === '' || $action === '') {
            return false;
        }

        $actor = $this->resolveActor($user);

        if (! $actor instanceof UserEntity) {
            return false;
        }

        if ($actor->isAdmin()) {
            return true;
        }

        $matrix = $this->matrixForUser($actor);
        $entityRules = $matrix[$entity] ?? null;

        if (! is_array($entityRules) || $entityRules === []) {
            return false;
        }

        $stateKey = $state === null || $state === '' ? '*' : $state;
        $rules = [];

        if (isset($entityRules[$stateKey])) {
            $rules[] = $entityRules[$stateKey];
        }

        if ($stateKey !== '*' && isset($entityRules['*'])) {
            $rules[] = $entityRules['*'];
        }

        foreach ($rules as $rule) {
            if ($this->ruleAllows($rule, $action, $field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function matrixForUser(?UserEntity $user = null): array
    {
        $actor = $this->resolveActor($user);

        if (! $actor instanceof UserEntity) {
            return [];
        }

        if ($actor->isAdmin()) {
            return ['*' => ['*' => ['read' => 1, 'write' => 1, 'create' => 1, 'delete' => 1, 'submit' => 1]]];
        }

        $cacheKey = $this->cacheKeyForRoles($this->normalizeRoles($actor->roles));
        $cached = $this->cache->get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $roles = $this->normalizeRoles($actor->roles);

        if ($roles === []) {
            return [];
        }

        $rows = $this->db->table('sys_permission')
            ->whereIn('role', $roles)
            ->orderBy('entity', 'ASC')
            ->orderBy('state', 'ASC')
            ->get()
            ->getResultArray();

        $matrix = [];

        foreach ($rows as $row) {
            $entity = (string) ($row['entity'] ?? '');
            $state = (string) ($row['state'] ?? '*');

            if ($entity === '') {
                continue;
            }

            $matrix[$entity][$state][] = [
                'role' => (string) ($row['role'] ?? ''),
                'actions' => $this->decodeJsonField($row['actions'] ?? []),
                'field_permissions' => $this->decodeJsonField($row['field_permissions'] ?? []),
            ];
        }

        $this->cache->save($cacheKey, $matrix, $this->cacheTtl);

        return $matrix;
    }

    public function clearCache(?string $role = null): void
    {
        if ($role !== null && $role !== '') {
            $this->cache->delete($this->cacheKeyForRoles([$role]));
        }
    }

    private function ruleAllows(array $rule, string $action, ?string $field = null): bool
    {
        $actions = is_array($rule['actions'] ?? null) ? $rule['actions'] : [];

        if ($field !== null && $field !== '') {
            $fieldPermissions = is_array($rule['field_permissions'] ?? null) ? $rule['field_permissions'] : [];

            if (isset($fieldPermissions[$field]) && is_array($fieldPermissions[$field])) {
                $fieldActions = $fieldPermissions[$field];

                return (int) ($fieldActions[$action] ?? 0) === 1;
            }
        }

        return (int) ($actions[$action] ?? 0) === 1;
    }

    private function resolveActor(?UserEntity $user = null): ?UserEntity
    {
        if ($user instanceof UserEntity) {
            return $user;
        }

        return $this->authService->currentUser();
    }

    /**
     * @param mixed $value
     *
     * @return array<string, mixed>
     */
    private function decodeJsonField(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            $unserialized = @unserialize($value, ['allowed_classes' => false]);
            if (is_array($unserialized)) {
                return $unserialized;
            }
        }

        return [];
    }

    /**
     * @param mixed $roles
     *
     * @return array<int, string>
     */
    private function normalizeRoles(mixed $roles): array
    {
        if (is_string($roles)) {
            $decoded = $this->decodeJsonField($roles);
            if ($decoded !== []) {
                $roles = $decoded;
            }
        }

        if (! is_array($roles)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $roles), static fn (string $role): bool => $role !== ''));
    }

    /**
     * @param array<int, string> $roles
     */
    private function cacheKeyForRoles(array $roles): string
    {
        sort($roles);

        return self::CACHE_PREFIX . self::CACHE_VERSION . '_' . hash('xxh128', implode('|', $roles));
    }
}
