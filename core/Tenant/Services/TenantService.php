<?php

declare(strict_types=1);

namespace Volt\Core\Tenant\Services;

use Config\Volt;
use Throwable;
use Volt\Core\Audit\AuditTrailWriter;
use Volt\Core\Database\VoltDatabase;
use Volt\Core\System\Services\BackupService;
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

    public function getTrashed(): array
    {
        return $this->tenantModel->getTrashed();
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

        $this->auditHub('tenant:create', $name, [
            'label' => $label,
            'domain' => $domain,
            'db_name' => $dbName,
            'is_active' => $isActive,
        ]);

        return $this->tenantModel->find($name) ?? $payload;
    }

    public function delete(string $name): void
    {
        $existing = $this->tenantModel->find($name);
        $this->tenantModel->delete($name, true);

        $this->auditHub('tenant:delete', $name, [], is_array($existing) ? $existing : []);
    }

    /**
     * Soft-delete: giữ lại DB, chỉ đánh dấu deleted_at + purge_at (sau grace period).
     */
    public function softDelete(string $name): void
    {
        $tenant = $this->tenantModel->find($name);
        if ($tenant === null) {
            return;
        }

        $graceDays = (int) config(Volt::class)->tenantDeleteGraceDays;
        $actor = $this->currentActor();

        $this->tenantModel->update($name, [
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => $actor,
            'purge_at'   => date('Y-m-d H:i:s', time() + ($graceDays * 86400)),
        ]);

        $this->auditHub('tenant:soft_delete', $name, [
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => $actor,
            'purge_at'   => $tenant['purge_at'] ?? date('Y-m-d H:i:s', time() + ($graceDays * 86400)),
        ], $tenant);
    }

    /**
     * Khôi phục tenant đã soft-delete.
     */
    public function restore(string $name): void
    {
        $tenant = $this->tenantModel->onlyDeleted()->find($name);
        if ($tenant === null) {
            return;
        }

        $this->tenantModel->update($name, [
            'deleted_at' => null,
            'deleted_by' => null,
            'purge_at'   => null,
        ]);

        $this->auditHub('tenant:restore', $name, ['is_active' => (int) $tenant['is_active']], $tenant);
    }

    /**
     * Purge: backup trước, drop DB, rồi xóa hẳn dòng sys_tenant.
     * Trả về đường dẫn file backup (rỗng nếu bỏ qua backup).
     */
    public function purge(string $name, bool $skipGrace = false): string
    {
        $tenant = $this->tenantModel->onlyDeleted()->find($name);
        if ($tenant === null) {
            throw new \InvalidArgumentException("Tenant '{$name}' not found or not in trash.");
        }

        if (! $skipGrace && ! $this->isPurgeDue($tenant)) {
            throw new \InvalidArgumentException(
                'Tenant chưa hết thời gian chờ purge (' . config(Volt::class)->tenantDeleteGraceDays . ' ngày).',
            );
        }

        $backupFile = '';

        try {
            $backupService = new BackupService();
            $backupFile = $backupService->backup(
                (string) $tenant['db_name'],
                (string) $tenant['db_host'],
                (int) $tenant['db_port'],
                (string) $tenant['db_username'],
                (string) $tenant['db_password'],
            );
        } catch (Throwable $e) {
            throw new \RuntimeException('Backup trước khi purge thất bại, không xóa tenant: ' . $e->getMessage(), 0, $e);
        }

        VoltDatabase::dropTenantDatabase(
            (string) $tenant['db_name'],
            (string) $tenant['db_host'],
            (int) $tenant['db_port'],
        );

        $this->tenantModel->delete($name, true);

        $this->auditHub('tenant:purge', $name, ['backup' => $backupFile], $tenant);

        return $backupFile;
    }

    private function isPurgeDue(array $tenant): bool
    {
        $purgeAt = $tenant['purge_at'] ?? null;
        if ($purgeAt === null) {
            return true;
        }

        return strtotime((string) $purgeAt) <= time();
    }

    private function currentActor(): string
    {
        try {
            return service('voltAuth')->currentUser()?->name ?? 'system';
        } catch (Throwable) {
            return 'system';
        }
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

    /**
     * @param array<string, mixed> $after
     * @param array<string, mixed> $before
     */
    private function auditHub(string $action, string $name, array $after = [], array $before = []): void
    {
        $actor = 'system';

        try {
            $actor = service('voltAuth')->currentUser()?->name ?? 'system';
        } catch (\Throwable) {
        }

        (new AuditTrailWriter(VoltDatabase::hubConnection()))->write(
            AuditTrailWriter::CAT_TENANT,
            $action,
            'sys_tenant',
            $name,
            $before,
            $after,
            $actor,
            ['tenant' => $name],
        );
    }
}
