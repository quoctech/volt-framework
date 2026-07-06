<?php

declare(strict_types=1);

namespace Volt\Core\Database\Seeds;

use CodeIgniter\Database\Seeder;

class VoltPermissionSeeder extends Seeder
{
    public function run()
    {
        $rows = [
            [
                'role' => 'admin',
                'entity' => 'Note',
                'state' => '*',
                'actions' => json_encode(['read' => 1, 'write' => 1, 'create' => 1, 'delete' => 1, 'submit' => 1], JSON_UNESCAPED_UNICODE),
                'field_permissions' => json_encode(['title' => ['read' => 1, 'write' => 1], 'body' => ['read' => 1, 'write' => 1], 'status' => ['read' => 1, 'write' => 1]], JSON_UNESCAPED_UNICODE),
            ],
            [
                'role' => 'editor',
                'entity' => 'Note',
                'state' => '*',
                'actions' => json_encode(['read' => 1, 'write' => 1, 'create' => 1, 'delete' => 0, 'submit' => 0], JSON_UNESCAPED_UNICODE),
                'field_permissions' => json_encode(['title' => ['read' => 1, 'write' => 1], 'body' => ['read' => 1, 'write' => 1], 'status' => ['read' => 1, 'write' => 1]], JSON_UNESCAPED_UNICODE),
            ],
            [
                'role' => 'viewer',
                'entity' => 'Note',
                'state' => '*',
                'actions' => json_encode(['read' => 1, 'write' => 0, 'create' => 0, 'delete' => 0, 'submit' => 0], JSON_UNESCAPED_UNICODE),
                'field_permissions' => json_encode(['title' => ['read' => 1, 'write' => 0], 'body' => ['read' => 1, 'write' => 0], 'status' => ['read' => 1, 'write' => 0]], JSON_UNESCAPED_UNICODE),
            ],
        ];

        $builder = $this->db->table('sys_permission');

        foreach ($rows as $row) {
            $exists = $this->db->table('sys_permission')
                ->where('role', $row['role'])
                ->where('entity', $row['entity'])
                ->where('state', $row['state'])
                ->countAllResults() > 0;

            if (! $exists) {
                $this->db->table('sys_permission')->insert($row);
            }
        }
    }
}
