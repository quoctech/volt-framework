<?php

declare(strict_types=1);

namespace Volt\Core\Report\Models;

use CodeIgniter\Database\BaseConnection;
use Volt\Core\Database\VoltDatabase;

class ReportModel
{
    private readonly BaseConnection $db;

    public function __construct()
    {
        $this->db = VoltDatabase::connection();
    }

    public function getAll(): array
    {
        return $this->db->table('sys_report')
            ->orderBy('label', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function getByName(string $name): ?array
    {
        $row = $this->db->table('sys_report')
            ->where('name', $name)
            ->get()
            ->getRowArray();

        return is_array($row) ? $row : null;
    }

    public function exists(string $name): bool
    {
        return $this->db->table('sys_report')
            ->where('name', $name)
            ->countAllResults() > 0;
    }

    public function upsert(array $data): void
    {
        $now = date('Y-m-d H:i:s');
        $data['updated_at'] = $now;

        if ($this->exists($data['name'])) {
            $this->db->table('sys_report')
                ->where('name', $data['name'])
                ->update($data);
        } else {
            $data['created_at'] = $now;
            $this->db->table('sys_report')->insert($data);
        }
    }

    public function delete(string $name): void
    {
        $this->db->table('sys_report')
            ->where('name', $name)
            ->delete();
    }
}
