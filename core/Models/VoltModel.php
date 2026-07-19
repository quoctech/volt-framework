<?php

declare(strict_types=1);

namespace Volt\Core\Models;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Model;
use RuntimeException;
use Volt\Core\Audit\AuditTrailWriter;
use Volt\Core\Auth\Entities\UserEntity;
use Volt\Core\Database\TableNameResolver;
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
    protected $beforeFind = ['voltBeforeFind'];

    protected string $entityName = '';
    protected bool $enforcePermissions = true;

    private const COL_NAME = 'name';
    private const COL_OWNER = 'owner';
    private const COL_CREATION = 'creation';
    private const COL_MODIFIED = 'modified';
    private const COL_DOCSTATUS = 'docstatus';
    private const COL_PARENT = 'parent';
    private const COL_PARENTFIELD = 'parentfield';
    private const COL_PARENTTYPE = 'parenttype';
    private const COL_IDX = 'idx';

    private const META_CHILD_TABLES = 'child_tables';
    private const META_CHILD_ENTITY = 'child_entity';
    private const META_STORAGE = 'storage';
    private const STORAGE_SEPARATE = 'separate_table';

    private const ACTION_CREATE = 'create';
    private const ACTION_WRITE = 'write';
    private const ACTION_DELETE = 'delete';
    private const ACTION_READ = 'read';

    private const DEFAULT_OWNER = 'system';
    private const KEY_DATA = 'data';

    /** @var array<string, array<string, mixed>> */
    protected array $auditSnapshots = [];

    /** @var array<string, array<string, true>> */
    private array $tableColumns = [];

    /** @var array<string, mixed>|null */
    private ?array $compiledMetadata = null;

    /** @var array<string, string> child_field => child_entity_name */
    private array $childTableMap = [];

    private ?PermissionResolver $permissionResolver = null;
    private ?AuditTrailWriter $auditTrailWriter = null;
    private ?MetadataValidator $metadataValidator = null;
    private ?UserEntity $actor = null;

    public function setEntityName(string $entityName): static
    {
        $this->entityName = (new MetadataValidator())->assertEntityName($entityName);

        return $this;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|object|string|int|null
     */
    public function insert($data = null, bool $returnID = true): array|object|string|int|null
    {
        $childData = $this->extractChildData($data);
        $data = $this->stripChildData($data);
        $data = $this->sanitizeParentData($data);

        $result = parent::insert($data, $returnID);

        if ($result !== false && $childData !== []) {
            $parentName = $this->resolveParentName($data, $result);
            $this->saveChildRecords($parentName, $childData);
        }

        return $result;
    }

    public function update($id = null, $data = null): bool
    {
        $childData = $this->extractChildData($data);
        $data = $this->stripChildData($data);
        $data = $this->sanitizeParentData($data);

        $result = parent::update($id, $data);

        if ($result && $childData !== []) {
            $parentName = $this->resolveParentName($data, $id);
            $this->saveChildRecords($parentName, $childData);
        }

        return $result;
    }

    /**
     * @param string|int|null $id
     * @return array<string, mixed>|object|null
     */
    public function find($id = null): array|object|null
    {
        $result = parent::find($id);

        if (is_array($result)) {
            $result = $this->attachChildRecords($result);
        } elseif (is_object($result) && method_exists($result, 'toRawArray')) {
            $raw = $result->toRawArray();
            $raw = $this->attachChildRecords($raw);
            foreach ($raw as $key => $value) {
                $result->{$key} = $value;
            }
        }

        return $result;
    }

    public function delete($id = null, bool $purge = false): bool
    {
        $this->deleteChildRecords((string) $id);

        return parent::delete($id, $purge);
    }

    /**
     * @param array<string, mixed>|null $data
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function extractChildData(?array $data): array
    {
        if ($data === null) {
            return [];
        }

        $childMap = $this->resolveChildTableMap();
        if ($childMap === []) {
            return [];
        }

        $childData = [];
        foreach ($childMap as $fieldname => $childEntity) {
            if (isset($data[$fieldname]) && is_array($data[$fieldname])) {
                $childData[$fieldname] = $data[$fieldname];
            }
        }

        return $childData;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function stripChildData(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $childMap = $this->resolveChildTableMap();
        foreach (array_keys($childMap) as $fieldname) {
            unset($data[$fieldname]);
        }

        return $data;
    }

    /**
     * @param array<string, mixed>|null $data
     * @return array<string, mixed>|null
     */
    private function sanitizeParentData(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveParentName(array $data, mixed $insertResult): string
    {
        if (isset($data[$this->primaryKey]) && is_string($data[$this->primaryKey]) && $data[$this->primaryKey] !== '') {
            return $data[$this->primaryKey];
        }

        if (isset($data[self::COL_NAME]) && is_string($data[self::COL_NAME]) && $data[self::COL_NAME] !== '') {
            return $data[self::COL_NAME];
        }

        if (is_string($insertResult) && $insertResult !== '') {
            return $insertResult;
        }

        return (string) $insertResult;
    }

    /**
     * @param array<int, array<string, mixed>> $childData
     */
    private function saveChildRecords(string $parentName, array $childData): void
    {
        $childMap = $this->resolveChildTableMap();
        if ($childMap === []) {
            return;
        }

        $parentType = $this->entityName !== '' ? $this->entityName : $this->table;

        $this->db->transStart();
        foreach ($childMap as $fieldname => $childEntity) {
            if (! isset($childData[$fieldname]) || ! is_array($childData[$fieldname])) {
                continue;
            }

            $rows = $childData[$fieldname];
            $childTable = TableNameResolver::entity($childEntity);

            // Delete removed rows (not in the new set)
            $this->db->table($childTable)
                ->where(self::COL_PARENT, $parentName)
                ->where(self::COL_PARENTFIELD, $fieldname)
                ->delete();

            // Batch insert rows (single query, avoids N+1)
            $timestamp = date('Y-m-d H:i:s');
            $batch = [];
            $idx = 0;
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $idx++;

                $row[self::COL_PARENT] = $parentName;
                $row[self::COL_PARENTFIELD] = $fieldname;
                $row[self::COL_PARENTTYPE] = $parentType;
                $row[self::COL_IDX] = $idx;
                $row[self::COL_OWNER] = $row[self::COL_OWNER] ?? self::DEFAULT_OWNER;
                $row[self::COL_CREATION] = $row[self::COL_CREATION] ?? $timestamp;
                $row[self::COL_MODIFIED] = $row[self::COL_MODIFIED] ?? $timestamp;
                $row[self::COL_DOCSTATUS] = $row[self::COL_DOCSTATUS] ?? 0;

                if (! isset($row[self::COL_NAME]) || (is_string($row[self::COL_NAME]) && trim($row[self::COL_NAME]) === '')) {
                    $row[self::COL_NAME] = bin2hex(random_bytes(16));
                }

                $batch[] = $row;
            }

            if ($batch !== []) {
                $this->db->table($childTable)->insertBatch($batch);
            }
        }
        $this->db->transComplete();
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function attachChildRecords(array $record): array
    {
        $childMap = $this->resolveChildTableMap();
        if ($childMap === []) {
            return $record;
        }

        $parentName = $record[$this->primaryKey] ?? $record[self::COL_NAME] ?? '';

        if ($parentName === '') {
            return $record;
        }

        $this->db->transStart();
        foreach ($childMap as $fieldname => $childEntity) {
            $childTable = TableNameResolver::entity($childEntity);

            $rows = $this->db->table($childTable)
                ->where(self::COL_PARENT, $parentName)
                ->where(self::COL_PARENTFIELD, $fieldname)
                ->orderBy(self::COL_IDX, 'ASC')
                ->get()
                ->getResultArray();

            $record[$fieldname] = $rows !== [] ? $rows : [];
        }

        $this->db->transComplete();

        return $record;
    }

    private function deleteChildRecords(string $parentName): void
    {
        $childMap = $this->resolveChildTableMap();
        if ($childMap === [] || $parentName === '') {
            return;
        }

        $this->db->transStart();
        foreach ($childMap as $fieldname => $childEntity) {
            $childTable = TableNameResolver::entity($childEntity);

            $this->db->table($childTable)
                ->where(self::COL_PARENT, $parentName)
                ->delete();
        }
        $this->db->transComplete();
    }

    /**
     * @return array<string, string> fieldname => child_entity_name
     */
    private function resolveChildTableMap(): array
    {
        if ($this->childTableMap !== []) {
            return $this->childTableMap;
        }

        $entityName = $this->entityName !== '' ? $this->entityName : $this->table;

        $meta = $this->loadCompiledMetadata();
        $childTables = $meta[self::META_CHILD_TABLES] ?? [];

        foreach ($childTables as $fieldname => $config) {
            if (($config[self::META_STORAGE] ?? '') === self::STORAGE_SEPARATE) {
                $childEntity = $config[self::META_CHILD_ENTITY] ?? '';
                if ($childEntity !== '') {
                    $this->childTableMap[$fieldname] = $childEntity;
                }
            }
        }

        return $this->childTableMap;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadCompiledMetadata(): array
    {
        if ($this->compiledMetadata !== null) {
            return $this->compiledMetadata;
        }

        $entityName = $this->entityName !== '' ? $this->entityName : $this->table;

        try {
            $compiler = service('voltMetadataCompiler');
            $this->compiledMetadata = $compiler->compileEntity($entityName);
        } catch (\Throwable) {
            $this->compiledMetadata = [];
        }

        return $this->compiledMetadata;
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
        $this->assertPermission(self::ACTION_CREATE);

        return $this->normalizeWritePayload($data, true);
    }

    protected function voltAfterInsert(array $data): array
    {
        $this->writeAudit(self::ACTION_CREATE, $data, null);

        return $data;
    }

    protected function voltBeforeUpdate(array $data): array
    {
        $this->assertPermission(self::ACTION_WRITE);
        $this->captureSnapshot(self::ACTION_WRITE, $data);

        return $this->normalizeWritePayload($data, false);
    }

    protected function voltAfterUpdate(array $data): array
    {
        $this->writeAudit(self::ACTION_WRITE, $data, self::ACTION_WRITE);

        return $data;
    }

    protected function voltBeforeDelete(array $data): array
    {
        $this->assertPermission(self::ACTION_DELETE);
        $this->captureSnapshot(self::ACTION_DELETE, $data);

        return $data;
    }

    protected function voltAfterDelete(array $data): array
    {
        $this->writeAudit(self::ACTION_DELETE, $data, self::ACTION_DELETE);

        return $data;
    }

    protected function voltBeforeFind(array $data): array
    {
        if (! $this->can(self::ACTION_READ)) {
            $data['returnData'] = true;
            $data['data'] = ($data['singleton'] ?? false) ? null : [];
        }

        return $data;
    }

    public function canRead(?string $state = null): bool
    {
        return $this->can(self::ACTION_READ, $state);
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
        if (isset($data[self::KEY_DATA]) && is_array($data[self::KEY_DATA])) {
            $data[self::KEY_DATA] = $this->normalizeRowPayload($data[self::KEY_DATA]);
            $data[self::KEY_DATA] = $this->applySystemFields($data[self::KEY_DATA], $isInsert);
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

        if ($isInsert && $this->hasColumn(self::COL_OWNER) && (! isset($row[self::COL_OWNER]) || trim((string) $row[self::COL_OWNER]) === '')) {
            $row[self::COL_OWNER] = $actorName;
        }

        if ($isInsert && $this->hasColumn(self::COL_CREATION) && (! isset($row[self::COL_CREATION]) || trim((string) $row[self::COL_CREATION]) === '')) {
            $row[self::COL_CREATION] = $timestamp;
        }

        if ($this->hasColumn(self::COL_MODIFIED)) {
            $row[self::COL_MODIFIED] = $timestamp;
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

        $after = isset($data[self::KEY_DATA]) && is_array($data[self::KEY_DATA]) ? $this->normalizeRowPayload($data[self::KEY_DATA]) : [];
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

        if (isset($data[self::KEY_DATA]) && is_array($data[self::KEY_DATA])) {
            if (array_key_exists($this->primaryKey, $data[self::KEY_DATA])) {
                $id = $this->normalizeDocumentId($data[self::KEY_DATA][$this->primaryKey]);
                if ($id !== null) {
                    return $id;
                }
            }

            if (array_key_exists(self::COL_NAME, $data[self::KEY_DATA])) {
                $id = $this->normalizeDocumentId($data[self::KEY_DATA][self::COL_NAME]);
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

        return $actor instanceof UserEntity ? (string) $actor->name : self::DEFAULT_OWNER;
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
