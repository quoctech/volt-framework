<?php

declare(strict_types=1);

namespace Volt\Core\Models;

use CodeIgniter\Model;
use RuntimeException;
use Volt\Core\Audit\AuditTrailWriter;
use Volt\Core\Auth\Entities\UserEntity;
use Volt\Core\Security\PermissionResolver;
use Volt\Core\Validation\MetadataValidator;

abstract class VoltModel extends Model
{
    protected $beforeInsert = ['voltBeforeInsert'];
    protected $afterInsert = ['voltAfterInsert'];
    protected $beforeUpdate = ['voltBeforeUpdate'];
    protected $afterUpdate = ['voltAfterUpdate'];
    protected $beforeDelete = ['voltBeforeDelete'];
    protected $afterDelete = ['voltAfterDelete'];

    protected string $entityName = '';
    protected bool $enforcePermissions = true;

    /** @var array<string, array<string, mixed>> */
    protected array $auditSnapshots = [];

    /** @var array<string, array<string, true>> */
    private array $tableColumns = [];

    private ?PermissionResolver $permissionResolver = null;
    private ?AuditTrailWriter $auditTrailWriter = null;
    private ?MetadataValidator $metadataValidator = null;
    private ?UserEntity $actor = null;

    public function setEntityName(string $entityName): static
    {
        $this->entityName = (new MetadataValidator())->assertEntityName($entityName);

        return $this;
    }

    public function setActor(?UserEntity $actor): static
    {
        $this->actor = $actor;

        return $this;
    }

    public function withPermissionChecks(bool $enabled = true): static
    {
        $this->enforcePermissions = $enabled;

        return $this;
    }

    protected function voltBeforeInsert(array $data): array
    {
        $this->assertPermission('create');

        return $this->normalizeWritePayload($data, true);
    }

    protected function voltAfterInsert(array $data): array
    {
        $this->writeAudit('create', $data, null);

        return $data;
    }

    protected function voltBeforeUpdate(array $data): array
    {
        $this->assertPermission('write');
        $this->captureSnapshot('update', $data);

        return $this->normalizeWritePayload($data, false);
    }

    protected function voltAfterUpdate(array $data): array
    {
        $this->writeAudit('update', $data, 'update');

        return $data;
    }

    protected function voltBeforeDelete(array $data): array
    {
        $this->assertPermission('delete');
        $this->captureSnapshot('delete', $data);

        return $data;
    }

    protected function voltAfterDelete(array $data): array
    {
        $this->writeAudit('delete', $data, 'delete');

        return $data;
    }

    public function canRead(?string $state = null): bool
    {
        return $this->can('read', $state);
    }

    public function canWrite(string $action, ?string $state = null): bool
    {
        return $this->can($action, $state);
    }

    protected function can(string $action, ?string $state = null, ?string $field = null): bool
    {
        if (! $this->enforcePermissions) {
            return true;
        }

        $resolver = $this->permissionResolver();
        $entityName = $this->entityName !== '' ? $this->entityName : $this->table;

        return $resolver->can($entityName, $action, $state, $field, $this->resolveActor());
    }

    protected function assertPermission(string $action, ?string $state = null, ?string $field = null): void
    {
        if ($this->can($action, $state, $field)) {
            return;
        }

        throw new RuntimeException(sprintf('Permission denied for %s on %s', $action, $this->entityName !== '' ? $this->entityName : $this->table));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function normalizeWritePayload(array $data, bool $isInsert): array
    {
        if (isset($data['data']) && is_array($data['data'])) {
            $data['data'] = $this->normalizeRowPayload($data['data']);
            $data['data'] = $this->applySystemFields($data['data'], $isInsert);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected function applySystemFields(array $row, bool $isInsert): array
    {
        $timestamp = date('Y-m-d H:i:s');
        $actorName = $this->resolveActorName();

        if ($isInsert && $this->hasColumn('owner') && (! isset($row['owner']) || trim((string) $row['owner']) === '')) {
            $row['owner'] = $actorName;
        }

        if ($isInsert && $this->hasColumn('creation') && (! isset($row['creation']) || trim((string) $row['creation']) === '')) {
            $row['creation'] = $timestamp;
        }

        if ($this->hasColumn('modified')) {
            $row['modified'] = $timestamp;
        }

        if ($isInsert && $this->hasColumn('created_at') && (! isset($row['created_at']) || trim((string) $row['created_at']) === '')) {
            $row['created_at'] = $timestamp;
        }

        if ($this->hasColumn('updated_at')) {
            $row['updated_at'] = $timestamp;
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function captureSnapshot(string $context, array $data): void
    {
        $id = $this->resolveDocumentId($data);
        if ($id === null) {
            return;
        }

        $record = $this->find($id);
        $this->auditSnapshots[$this->snapshotKey($context, $id)] = $this->normalizeRowObject($record);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function writeAudit(string $action, array $data, ?string $snapshotContext = null): void
    {
        $entityName = $this->entityName !== '' ? $this->entityName : $this->table;
        $id = $this->resolveDocumentId($data);

        if ($id === null) {
            return;
        }

        $after = isset($data['data']) && is_array($data['data']) ? $this->normalizeRowPayload($data['data']) : [];
        $before = [];

        if ($snapshotContext !== null) {
            $snapshotKey = $this->snapshotKey($snapshotContext, $id);
            $before = $this->auditSnapshots[$snapshotKey] ?? [];
            unset($this->auditSnapshots[$snapshotKey]);
        }

        if ($action === 'create') {
            $before = [];
        }

        $this->auditTrailWriter()->write($entityName, (string) $id, $action, $before, $after, $this->resolveActorName());
    }

    /**
     * @param mixed $row
     *
     * @return array<string, mixed>
     */
    protected function normalizeRowObject(mixed $row): array
    {
        if (is_array($row)) {
            return $this->normalizeRowPayload($row);
        }

        if (is_object($row) && method_exists($row, 'toRawArray')) {
            /** @var array<string, mixed> $raw */
            $raw = $row->toRawArray();

            return $this->normalizeRowPayload($raw);
        }

        return [];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    protected function normalizeRowPayload(array $row): array
    {
        foreach ($row as $key => $value) {
            if (is_object($value) && method_exists($value, 'toRawArray')) {
                $row[$key] = $value->toRawArray();
                continue;
            }

            if (is_string($value)) {
                $decoded = json_decode($value, true);

                if (is_array($decoded)) {
                    $row[$key] = $decoded;
                    continue;
                }

                $unserialized = @unserialize($value, ['allowed_classes' => false]);

                if (is_array($unserialized)) {
                    $row[$key] = $unserialized;
                }
            }
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function resolveDocumentId(array $data): string|int|null
    {
        if (array_key_exists('id', $data)) {
            $id = $this->normalizeDocumentId($data['id']);
            if ($id !== null) {
                return $id;
            }
        }

        if (isset($data['data']) && is_array($data['data'])) {
            if (array_key_exists($this->primaryKey, $data['data'])) {
                $id = $this->normalizeDocumentId($data['data'][$this->primaryKey]);
                if ($id !== null) {
                    return $id;
                }
            }

            if (array_key_exists('name', $data['data'])) {
                $id = $this->normalizeDocumentId($data['data']['name']);
                if ($id !== null) {
                    return $id;
                }
            }
        }

        return null;
    }

    protected function resolveActor(): ?UserEntity
    {
        if ($this->actor instanceof UserEntity) {
            return $this->actor;
        }

        $auth = service('voltAuth');
        $actor = $auth->currentUser();

        if ($actor instanceof UserEntity) {
            $this->actor = $actor;
        }

        return $actor;
    }

    protected function resolveActorName(): string
    {
        $actor = $this->resolveActor();

        return $actor instanceof UserEntity ? (string) $actor->name : 'system';
    }

    protected function permissionResolver(): PermissionResolver
    {
        if (! $this->permissionResolver instanceof PermissionResolver) {
            $this->permissionResolver = service('voltPermissionResolver');
        }

        return $this->permissionResolver;
    }

    protected function auditTrailWriter(): AuditTrailWriter
    {
        if (! $this->auditTrailWriter instanceof AuditTrailWriter) {
            $this->auditTrailWriter = service('voltAuditTrailWriter');
        }

        return $this->auditTrailWriter;
    }

    protected function metadataValidator(): MetadataValidator
    {
        if (! $this->metadataValidator instanceof MetadataValidator) {
            $this->metadataValidator = service('voltMetadataValidator');
        }

        return $this->metadataValidator;
    }

    private function snapshotKey(string $context, string|int $id): string
    {
        return $context . ':' . (string) $id;
    }

    private function normalizeDocumentId(mixed $id): string|int|null
    {
        if (is_string($id)) {
            $id = trim($id);

            return $id !== '' ? $id : null;
        }

        if (is_int($id)) {
            return $id;
        }

        if (is_array($id)) {
            foreach ($id as $candidate) {
                $normalized = $this->normalizeDocumentId($candidate);
                if ($normalized !== null) {
                    return $normalized;
                }
            }
        }

        return null;
    }

    private function hasColumn(string $column): bool
    {
        if (! isset($this->tableColumns[$this->table])) {
            $columns = $this->db->getFieldNames($this->table) ?: [];
            $this->tableColumns[$this->table] = array_fill_keys($columns, true);
        }

        return isset($this->tableColumns[$this->table][$column]);
    }
}
