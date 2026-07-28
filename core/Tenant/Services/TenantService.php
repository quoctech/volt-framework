<?php

declare(strict_types=1);

namespace Volt\Core\Tenant\Services;

use Volt\Core\Database\VoltDatabase;
use Volt\Core\Tenant\Models\TenantModel;

class TenantService
{
    private readonly TenantModel $tenantModel;

    public function __construct(?TenantModel $tenantModel = null)
    {
        $this->tenantModel = $tenantModel ?? new TenantModel();
    }

    public function getAll(): array
    {
        return $this->tenantModel->findAll();
    }

    public function getActive(): array
    {
        return $this->tenantModel->getActive();
    }

    public function getByName(string $name): ?array
    {
        return $this->tenantModel->find($name);
    }

    public function save(array $data): array
    {
        $name = $this->normalizeName((string) ($data['name'] ?? ''));
        $label = mb_trim((string) ($data['label'] ?? ''));
        $domain = mb_trim((string) ($data['domain'] ?? ''));
        $dbName = mb_trim((string) ($data['db_name'] ?? ''));
        $isActive = (int) ($data['is_active'] ?? 1);

        if ($name === '') {
            throw new \InvalidArgumentException('Tenant name is required.');
        }
        if ($label === '') {
            $label = $name;
        }
        if ($dbName === '') {
            throw new \InvalidArgumentException('Database name is required.');
        }

        $payload = [
            'name'        => $name,
            'label'       => $label,
            'domain'      => $domain,
            'db_host'     => mb_trim((string) ($data['db_host'] ?? 'localhost')),
            'db_port'     => (int) ($data['db_port'] ?? 5432),
            'db_name'     => $dbName,
            'db_username' => mb_trim((string) ($data['db_username'] ?? 'volt_admin')),
            'db_password' => $data['db_password'] ?? '',
            'is_active'   => $isActive,
        ];

        $this->tenantModel->save($payload);

        return $this->tenantModel->find($name) ?? $payload;
    }

    public function delete(string $name): void
    {
        $this->tenantModel->delete($name);
    }

    public function exists(string $name): bool
    {
        return $this->tenantModel->find($name) !== null;
    }

    public function resolveByDomain(string $host): ?array
    {
        return $this->tenantModel->findByDomain($host);
    }

    private function normalizeName(string $name): string
    {
        $name = mb_strtolower(mb_trim($name));
        $name = preg_replace('/[^a-z0-9_]+/', '_', $name) ?? '';
        $name = preg_replace('/_+/', '_', $name) ?? '';
        return mb_trim($name, '_');
    }
}
