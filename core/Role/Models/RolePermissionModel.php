<?php

declare(strict_types=1);

namespace Volt\Core\Role\Models;

use CodeIgniter\Database\BaseConnection;
use Volt\Core\Database\VoltDatabase;

class RolePermissionModel
{
    private BaseConnection $db;

    public function __construct()
    {
        $this->db = VoltDatabase::connection();
    }

    public function getPermissionsForRole(string $role): array
    {
        $rows = $this->db->table('sys_permission')
            ->where('role', $role)
            ->get()
            ->getResultArray();

        $permissions = [];

        foreach ($rows as $row) {
            $entity = (string) ($row['entity'] ?? '');
            $actions = $this->decodeJson($row['actions'] ?? []);

            $permissions[$entity] = [
                'id'     => (int) ($row['id'] ?? 0),
                'role'   => (string) ($row['role'] ?? ''),
                'entity' => $entity,
                'state'  => (string) ($row['state'] ?? '*'),
                'read'   => (int) ($actions['read'] ?? 0),
                'write'  => (int) ($actions['write'] ?? 0),
                'create' => (int) ($actions['create'] ?? 0),
                'delete' => (int) ($actions['delete'] ?? 0),
                'submit' => (int) ($actions['submit'] ?? 0),
                'import' => (int) ($actions['import'] ?? 0),
                'amend'  => (int) ($actions['amend'] ?? 0),
                'report' => (int) ($actions['report'] ?? 0),
                'export' => (int) ($actions['export'] ?? 0),
                'print'  => (int) ($actions['print'] ?? 0),
                'email'  => (int) ($actions['email'] ?? 0),
            ];
        }

        return $permissions;
    }

    public function savePermissions(string $role, string $entity, array $actions): void
    {
        $existing = $this->db->table('sys_permission')
            ->where('role', $role)
            ->where('entity', $entity)
            ->where('state', '*')
            ->get()
            ->getRow();

        $payload = [
            'role'              => $role,
            'entity'            => $entity,
            'state'             => '*',
            'actions'           => json_encode([
                'read'   => (int) ($actions['read'] ?? 0),
                'write'  => (int) ($actions['write'] ?? 0),
                'create' => (int) ($actions['create'] ?? 0),
                'delete' => (int) ($actions['delete'] ?? 0),
                'submit' => (int) ($actions['submit'] ?? 0),
                'import' => (int) ($actions['import'] ?? 0),
                'amend'  => (int) ($actions['amend'] ?? 0),
                'report' => (int) ($actions['report'] ?? 0),
                'export' => (int) ($actions['export'] ?? 0),
                'print'  => (int) ($actions['print'] ?? 0),
                'email'  => (int) ($actions['email'] ?? 0),
            ], JSON_UNESCAPED_UNICODE),
            'field_permissions' => '{}',
        ];

        if ($existing) {
            $this->db->table('sys_permission')
                ->where('id', $existing->id)
                ->update($payload);
        } else {
            $this->db->table('sys_permission')
                ->insert($payload);
        }
    }

    public function deletePermissionsForRole(string $role): void
    {
        $this->db->table('sys_permission')
            ->where('role', $role)
            ->delete();
    }

    public function deletePermission(string $role, string $entity): void
    {
        $this->db->table('sys_permission')
            ->where('role', $role)
            ->where('entity', $entity)
            ->delete();
    }

    public function getAllEntityNames(): array
    {
        $fromEntity = $this->db->table('sys_entity')
            ->select('name')
            ->get()
            ->getResultArray();

        $fromPermission = $this->db->query("SELECT DISTINCT entity AS name FROM sys_permission")
            ->getResultArray();

        $names = array_unique(array_merge(
            array_map(static fn (array $row): string => (string) ($row['name'] ?? ''), $fromEntity),
            array_map(static fn (array $row): string => (string) ($row['name'] ?? ''), $fromPermission),
        ));

        sort($names);

        return $names;
    }

    private function decodeJson(mixed $value): array
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
}
