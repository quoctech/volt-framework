<?php

declare(strict_types=1);

namespace Volt\Core\AwesomeBar\Models;

use CodeIgniter\Database\BaseConnection;
use Volt\Core\Database\VoltDatabase;

class AwesomeBarModel
{
    private readonly BaseConnection $db;

    public function __construct()
    {
        $this->db = VoltDatabase::connection();
    }

    public function search(string $keyword, array $allowedEntities = []): array
    {
        $keyword = mb_trim($keyword);

        if ($keyword === '') {
            return $this->suggest();
        }

        $builder = $this->db->table('sys_awesome_bar')
            ->select('id, item_type, item_name, label, description, route, module, is_core')
            ->groupStart()
                ->like('label', $keyword)
                ->orLike('item_name', $keyword)
                ->orLike('description', $keyword)
            ->groupEnd()
            ->orderBy('is_core', 'DESC')
            ->orderBy('label', 'ASC')
            ->limit(20);

        if ($allowedEntities !== []) {
            $builder->whereIn('item_name', $allowedEntities);
        }

        $rows = $builder->get()->getResultArray();

        return array_map(static fn (array $row): array => [
            'id'          => (int) ($row['id'] ?? 0),
            'item_type'   => (string) ($row['item_type'] ?? ''),
            'item_name'   => (string) ($row['item_name'] ?? ''),
            'label'       => (string) ($row['label'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'route'       => (string) ($row['route'] ?? ''),
            'module'      => (string) ($row['module'] ?? ''),
            'is_core'     => (bool) ($row['is_core'] ?? false),
        ], $rows);
    }

    public function suggest(int $limit = 8): array
    {
        $rows = $this->db->table('sys_awesome_bar')
            ->select('id, item_type, item_name, label, description, route, module, is_core')
            ->orderBy('is_core', 'DESC')
            ->orderBy('updated_at', 'DESC')
            ->orderBy('label', 'ASC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        return array_map(static fn (array $row): array => [
            'id'          => (int) ($row['id'] ?? 0),
            'item_type'   => (string) ($row['item_type'] ?? ''),
            'item_name'   => (string) ($row['item_name'] ?? ''),
            'label'       => (string) ($row['label'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'route'       => (string) ($row['route'] ?? ''),
            'module'      => (string) ($row['module'] ?? ''),
            'is_core'     => (bool) ($row['is_core'] ?? false),
        ], $rows);
    }

    public function registerEntity(string $entityName, string $label, string $module, string $owner): void
    {
        $existing = $this->db->table('sys_awesome_bar')
            ->where('item_type', 'entity')
            ->where('item_name', $entityName)
            ->get()
            ->getRow();

        $moduleSnake = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $module) ?? '');
        $entitySnake = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $entityName) ?? '');

        $route = site_url("{$moduleSnake}/{$entitySnake}");

        if ($existing) {
            $this->db->table('sys_awesome_bar')
                ->where('id', $existing->id)
                ->update([
                    'label'      => $label,
                    'module'     => $module,
                    'route'      => $route,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        } else {
            $this->db->table('sys_awesome_bar')->insert([
                'item_type'   => 'entity',
                'item_name'   => $entityName,
                'label'       => $label,
                'route'       => $route,
                'module'      => $module,
                'is_core'     => 0,
                'owner'       => $owner,
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function removeEntity(string $entityName): void
    {
        $this->db->table('sys_awesome_bar')
            ->where('item_type', 'entity')
            ->where('item_name', $entityName)
            ->delete();
    }

    public function seedCorePages(): void
    {
        $pages = [
            ['item_name' => 'entity_list',    'label' => 'Entity List',    'description' => 'Xem danh sách entity metadata',                'route' => 'desk/entities',         'module' => null],
            ['item_name' => 'entity_builder',  'label' => 'Entity Builder',  'description' => 'Xây dựng và cấu hình entity',                    'route' => 'desk/entity-builder',   'module' => null],
            ['item_name' => 'create_module',   'label' => 'Create Module',   'description' => 'Tạo module mới',                               'route' => 'desk/create-module',    'module' => null],
            ['item_name' => 'user_list',       'label' => 'User List',       'description' => 'Quản lý người dùng',                            'route' => 'desk/users',            'module' => null],
            ['item_name' => 'role_list',       'label' => 'Role List',       'description' => 'Quản lý role và phân quyền',                    'route' => 'desk/roles',            'module' => null],
            ['item_name' => 'system_status',   'label' => 'System Status',   'description' => 'Kiểm tra trạng thái runtime, cache và database', 'route' => 'desk/system-status',    'module' => null],
            ['item_name' => 'error_logs',      'label' => 'Error Logs',      'description' => 'Xem nhật ký lỗi hệ thống và stack trace runtime', 'route' => 'desk/error-logs',       'module' => null],
            ['item_name' => 'desk',            'label' => 'Desk',            'description' => 'Trang chủ Volt Desk',                           'route' => 'desk',                  'module' => null],
        ];

        foreach ($pages as $page) {
            $existing = $this->db->table('sys_awesome_bar')
                ->where('item_type', 'page')
                ->where('item_name', $page['item_name'])
                ->get()
                ->getRow();

            $payload = [
                'label'       => $page['label'],
                'description' => $page['description'],
                'route'       => $page['route'],
                'module'      => $page['module'],
                'is_core'     => 1,
                'owner'       => 'system',
                'updated_at'  => date('Y-m-d H:i:s'),
            ];

            if (! $existing) {
                $this->db->table('sys_awesome_bar')->insert([
                    'item_type'   => 'page',
                    'item_name'   => $page['item_name'],
                    ...$payload,
                    'created_at'  => date('Y-m-d H:i:s'),
                ]);
                continue;
            }

            $this->db->table('sys_awesome_bar')
                ->where('id', $existing->id)
                ->update($payload);
        }
    }

    public function getAccessibleEntityNames(): array
    {
        $rows = $this->db->table('sys_awesome_bar')
            ->select('item_name')
            ->where('item_type', 'entity')
            ->get()
            ->getResultArray();

        return array_map(static fn (array $row): string => (string) ($row['item_name'] ?? ''), $rows);
    }

    /**
     * @return list<string>
     */
    public function corePermissionEntities(): array
    {
        return [
            'error_logs',
        ];
    }
}
