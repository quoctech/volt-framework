<?php

declare(strict_types=1);

namespace Volt\Core\Metadata;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Database\RawSql;
use InvalidArgumentException;
use Throwable;
use Volt\Core\Database\VoltDatabase;
use Volt\Core\Engine\SchemaSync;

final class EntityBuilderService
{
    private BaseConnection $db;
    private EntityMetadataCache $metadataCache;
    private ArtifactScaffolder $artifactScaffolder;

    public function __construct(?BaseConnection $db = null, ?EntityMetadataCache $metadataCache = null, ?ArtifactScaffolder $artifactScaffolder = null)
    {
        $this->db = $db ?? VoltDatabase::connection();
        $this->metadataCache = $metadataCache ?? new EntityMetadataCache();
        $this->artifactScaffolder = $artifactScaffolder ?? new ArtifactScaffolder();
        $this->ensureModuleTable();
    }

    /**
     * @return array<int, string>
     */
    public function listEntityNames(): array
    {
        $rows = $this->db->table('sys_entity')
            ->select('name')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['name'] ?? ''),
            $rows
        )));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listEntities(?string $module = null): array
    {
        $builder = $this->db->table('sys_entity')
            ->select('name, module, autoname')
            ->select(new RawSql("COALESCE(custom_attributes, '{}'::jsonb) AS custom_attributes"))
            ->orderBy('module', 'ASC')
            ->orderBy('name', 'ASC');

        if (is_string($module) && trim($module) !== '') {
            $builder->where('module', $this->normalizeIdentifier($module));
        }

        $rows = $builder->get()->getResultArray();

        return array_map(function (array $row): array {
            $custom = $this->decodeJsonObject($row['custom_attributes'] ?? '{}');

            return [
                'name' => (string) ($row['name'] ?? ''),
                'module' => (string) ($row['module'] ?? ''),
                'label' => (string) ($custom['label'] ?? $this->titleize((string) ($row['name'] ?? ''))),
                'autoname' => (string) ($row['autoname'] ?? 'HASH'),
                'is_submittable' => (bool) ($custom['is_submittable'] ?? false),
            ];
        }, $rows);
    }

    /**
     * @return array<int, string>
     */
    public function listModules(): array
    {
        $rows = $this->db->table('sys_module')
            ->select('name')
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        $modules = array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['name'] ?? ''),
            $rows
        )));

        return $modules;
    }

    /**
     * @return array<string, mixed>
     */
    public function createModule(array $payload): array
    {
        $name = $this->normalizeIdentifier((string) ($payload['name'] ?? ''));
        $label = trim((string) ($payload['label'] ?? ''));

        if ($name === '') {
            throw new InvalidArgumentException('Module name is required.');
        }

        $label = $label !== '' ? $label : $this->titleize($name);
        $scaffold = $this->artifactScaffolder->scaffoldModule($name, $label);
        // Module là "nguồn gốc" của entity nên phải có metadata DB và thư mục app cùng lúc.
        $exists = $this->db->table('sys_module')->where('name', $scaffold['name'])->countAllResults() > 0;

        if ($exists) {
            $this->db->table('sys_module')
                ->where('name', $scaffold['name'])
                ->update([
                    'label' => $scaffold['label'],
                    'namespace' => $scaffold['namespace'],
                    'module_path' => $scaffold['module_path'],
                    'is_active' => 1,
                    'updated_at' => new RawSql('CURRENT_TIMESTAMP'),
                ]);
        } else {
            $this->db->table('sys_module')->insert([
                'name' => $scaffold['name'],
                'label' => $scaffold['label'],
                'namespace' => $scaffold['namespace'],
                'module_path' => $scaffold['module_path'],
                'is_active' => 1,
                'created_at' => new RawSql('CURRENT_TIMESTAMP'),
                'updated_at' => new RawSql('CURRENT_TIMESTAMP'),
            ]);
        }

        return $scaffold;
    }

    /**
     * @return array<string, mixed>
     */
    public function loadEntity(string $entityName): array
    {
        $entityName = $this->normalizeEntityName($entityName);
        if ($entityName === '') {
            throw new InvalidArgumentException('Entity name is required.');
        }

        $entity = $this->db->table('sys_entity')
            ->select('name, module, issingle, istable, autoname')
            ->select(new RawSql("COALESCE(states, '{}'::jsonb) AS states"))
            ->select(new RawSql("COALESCE(custom_attributes, '{}'::jsonb) AS custom_attributes"))
            ->where('name', $entityName)
            ->get()
            ->getRowArray();

        if (! is_array($entity)) {
            throw new InvalidArgumentException('Entity not found.');
        }

        $customPatch = $this->db->table('sys_entity_custom')
            ->select('entity_name, apply_to_role')
            ->select(new RawSql("COALESCE(custom_meta, '{}'::jsonb) AS custom_meta"))
            ->where('entity_name', $entityName)
            ->get()
            ->getRowArray();

        $customMeta = is_array($customPatch) ? $this->decodeJsonObject($customPatch['custom_meta'] ?? '{}') : [];
        $fields = $this->db->table('sys_entity_field')
            ->select('id, parent, fieldname, label, fieldtype, length, options, reqd, read_only, hidden, idx')
            ->where('parent', $entityName)
            ->orderBy('idx', 'ASC')
            ->get()
            ->getResultArray();

        $entityPayload = $this->hydrateEntity($entity);
        $fieldPayloads = [];

        foreach ($fields as $field) {
            $fieldPayloads[] = $this->hydrateField(
                $field,
                $customMeta['fields'][$field['fieldname'] ?? ''] ?? []
            );
        }

        return [
            'entity'       => $entityPayload,
            'fields'       => $fieldPayloads,
            'custom_patch' => $customMeta,
            'compiled'     => $this->compileMetadata($entityPayload, $fieldPayloads, $customMeta),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function saveEntity(array $payload): array
    {
        $entity = $this->normalizeEntityPayload($payload['entity'] ?? []);
        $fields = $this->normalizeFieldPayload($payload['fields'] ?? []);
        $customPatch = $this->normalizeJsonObject($payload['custom_patch'] ?? []);

        if ($entity['name'] === '') {
            throw new InvalidArgumentException('Entity name is required.');
        }

        try {
            $this->db->transException(true)->transStart();
            $moduleMeta = $this->createModule([
                'name' => $entity['module'],
                'label' => $this->titleize($entity['module']),
            ]);
            $entity['module'] = $moduleMeta['name'];

            $this->db->table('sys_entity')
                ->set($this->entityUpsertRow($entity))
                ->onConstraint('name')
                ->updateFields(['module', 'issingle', 'istable', 'autoname', 'states', 'custom_attributes'])
                ->upsert();

            $fieldRows = $this->fieldUpsertRows($entity['name'], $fields);
            $fieldnames = array_column($fieldRows, 'fieldname');

            $cleanupBuilder = $this->db->table('sys_entity_field')->where('parent', $entity['name']);
            if ($fieldnames !== []) {
                $cleanupBuilder->whereNotIn('fieldname', $fieldnames);
            }
            $cleanupBuilder->delete();

            if ($fieldRows !== []) {
                $this->db->table('sys_entity_field')
                    ->onConstraint(['parent', 'fieldname'])
                    ->updateFields(['label', 'fieldtype', 'length', 'options', 'reqd', 'read_only', 'hidden', 'idx'])
                    ->upsertBatch($fieldRows);
            }

            // Session và custom field patch không có cột riêng trong bảng base nên được gom vào custom_meta.
            $customPatch = $this->mergeFieldCustomIntoPatch($customPatch, $fields);
            $this->db->table('sys_entity_custom')
                ->set([
                    'entity_name'   => $entity['name'],
                    'apply_to_role' => null,
                    'custom_meta'   => json_encode($customPatch, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ])
                ->onConstraint('entity_name')
                ->updateFields(['apply_to_role', 'custom_meta'])
                ->upsert();

            $sync = new SchemaSync();
            $result = $sync->syncEntity($entity['name']);
            if (($result['status'] ?? 'error') !== 'success') {
                throw new DatabaseException((string) ($result['message'] ?? 'Schema synchronization failed.'));
            }

            $compiled = $this->compileMetadata($entity, $fields, $customPatch);
            // Mỗi lần save entity đều đồng bộ lại artifact để code app luôn khớp metadata mới nhất.
            $artifacts = $this->artifactScaffolder->scaffoldEntity($entity['module'], $entity['name'], $compiled);
            $this->metadataCache->put($entity['name'], $compiled);

            $this->db->transComplete();

            return [
                'entity'   => $entity,
                'fields'   => $fields,
                'compiled' => $compiled,
                'artifacts' => $artifacts,
            ];
        } catch (Throwable $throwable) {
            try {
                $this->db->transRollback();
            } catch (Throwable) {
            }

            throw $throwable;
        }
    }

    /**
     * @param array<string, mixed> $entity
     *
     * @return array<string, mixed>
     */
    private function hydrateEntity(array $entity): array
    {
        $states = $this->decodeJsonObject($entity['states'] ?? '{}');
        $custom = $this->decodeJsonObject($entity['custom_attributes'] ?? '{}');

        return [
            'name'           => (string) ($entity['name'] ?? ''),
            'module'         => (string) ($entity['module'] ?? ''),
            'label'          => (string) ($custom['label'] ?? $this->titleize((string) ($entity['name'] ?? ''))),
            'is_submittable' => (bool) ($custom['is_submittable'] ?? false),
            'issingle'       => (bool) ($entity['issingle'] ?? false),
            'istable'        => (bool) ($entity['istable'] ?? false),
            'autoname'       => (string) ($entity['autoname'] ?? 'HASH'),
            'states'         => $states,
            's_custom_jsonb' => $custom,
        ];
    }

    /**
     * @param array<string, mixed> $field
     * @param array<string, mixed> $fieldCustom
     *
     * @return array<string, mixed>
     */
    private function hydrateField(array $field, array $fieldCustom = []): array
    {
        return [
            'id'             => isset($field['id']) ? (int) $field['id'] : null,
            'parent'         => (string) ($field['parent'] ?? ''),
            'fieldname'      => (string) ($field['fieldname'] ?? ''),
            'label'          => (string) ($field['label'] ?? ''),
            'fieldtype'      => (string) ($field['fieldtype'] ?? 'Input'),
            'length'         => isset($field['length']) ? (int) $field['length'] : null,
            'options'        => (string) ($field['options'] ?? ''),
            'is_required'    => (bool) ($field['reqd'] ?? false),
            'read_only'      => (bool) ($field['read_only'] ?? false),
            'hidden'         => (bool) ($field['hidden'] ?? false),
            'idx'            => isset($field['idx']) ? (int) $field['idx'] : 0,
            'f_custom_jsonb' => $this->normalizeJsonObject($fieldCustom),
        ];
    }

    /**
     * @param mixed $payload
     *
     * @return array<string, mixed>
     */
    private function normalizeEntityPayload(mixed $payload): array
    {
        if (! is_array($payload)) {
            throw new InvalidArgumentException('Entity payload must be an object.');
        }

        $name = $this->normalizeEntityName((string) ($payload['name'] ?? ''));
        $label = trim((string) ($payload['label'] ?? ''));

        return [
            'name'           => $name,
            'module'         => $this->normalizeIdentifier((string) ($payload['module'] ?? 'core')),
            'label'          => $label !== '' ? $label : $this->titleize($name),
            'is_submittable' => $this->toBool($payload['is_submittable'] ?? false),
            'issingle'       => $this->toBool($payload['issingle'] ?? false),
            'istable'        => $this->toBool($payload['istable'] ?? false),
            'autoname'       => trim((string) ($payload['autoname'] ?? 'HASH')) ?: 'HASH',
            'states'         => $this->normalizeJsonObject($payload['states'] ?? []),
            's_custom_jsonb' => $this->normalizeJsonObject($payload['s_custom_jsonb'] ?? []),
        ];
    }

    /**
     * @param mixed $payload
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeFieldPayload(mixed $payload): array
    {
        if (! is_array($payload)) {
            throw new InvalidArgumentException('Fields payload must be an array.');
        }

        $rows = [];
        $fieldnames = [];

        foreach (array_values($payload) as $index => $field) {
            if (! is_array($field)) {
                throw new InvalidArgumentException('Each field payload must be an object.');
            }

            $fieldname = $this->normalizeIdentifier((string) ($field['fieldname'] ?? ''));
            $label = trim((string) ($field['label'] ?? ''));
            $fieldtype = $this->normalizeFieldType((string) ($field['fieldtype'] ?? 'Input'));
            $options = trim((string) ($field['options'] ?? ''));

            if ($fieldname === '') {
                throw new InvalidArgumentException('Fieldname is required for every field.');
            }

            if (in_array($fieldname, $fieldnames, true)) {
                throw new InvalidArgumentException('Duplicate fieldname detected: ' . $fieldname);
            }

            if (in_array($fieldtype, ['Select', 'Table'], true) && $options === '') {
                throw new InvalidArgumentException("Field {$fieldname} requires options.");
            }

            $fieldnames[] = $fieldname;

            $rows[] = [
                'id'             => isset($field['id']) ? (int) $field['id'] : null,
                'fieldname'      => $fieldname,
                'label'          => $label !== '' ? $label : $this->titleize($fieldname),
                'fieldtype'      => $fieldtype,
                'length'         => $this->normalizeNullableInt($field['length'] ?? null),
                'options'        => $options,
                'is_required'    => $this->toBool($field['is_required'] ?? false),
                'read_only'      => $this->toBool($field['read_only'] ?? false),
                'hidden'         => $this->toBool($field['hidden'] ?? false),
                'idx'            => $index + 1,
                'f_custom_jsonb' => $this->normalizeJsonObject($field['f_custom_jsonb'] ?? []),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $entity
     *
     * @return array<string, mixed>
     */
    private function entityUpsertRow(array $entity): array
    {
        return [
            'name'              => $entity['name'],
            'module'            => $entity['module'],
            'issingle'          => $entity['issingle'] ? 1 : 0,
            'istable'           => $entity['istable'] ? 1 : 0,
            'autoname'          => $entity['autoname'],
            'states'            => json_encode($entity['states'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'custom_attributes' => json_encode(array_merge($entity['s_custom_jsonb'], [
                'label'          => $entity['label'],
                'is_submittable' => $entity['is_submittable'],
            ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     *
     * @return array<int, array<string, mixed>>
     */
    private function fieldUpsertRows(string $entityName, array $fields): array
    {
        return array_map(
            static fn (array $field): array => [
                'parent'    => $entityName,
                'fieldname' => $field['fieldname'],
                'label'     => $field['label'],
                'fieldtype' => $field['fieldtype'],
                'length'    => $field['length'],
                'options'   => $field['options'],
                'reqd'      => $field['is_required'] ? 1 : 0,
                'read_only' => $field['read_only'] ? 1 : 0,
                'hidden'    => $field['hidden'] ? 1 : 0,
                'idx'       => $field['idx'],
            ],
            $fields
        );
    }

    /**
     * @param array<string, mixed> $customPatch
     * @param array<int, array<string, mixed>> $fields
     *
     * @return array<string, mixed>
     */
    private function mergeFieldCustomIntoPatch(array $customPatch, array $fields): array
    {
        $customPatch['fields'] = [];

        foreach ($fields as $field) {
            $fieldname = (string) ($field['fieldname'] ?? '');
            if ($fieldname === '') {
                continue;
            }

            $customPatch['fields'][$fieldname] = $field['f_custom_jsonb'] ?? [];
        }

        return $customPatch;
    }

    /**
     * @param array<string, mixed> $entity
     * @param array<int, array<string, mixed>> $fields
     * @param array<string, mixed> $customPatch
     *
     * @return array<string, mixed>
     */
    private function compileMetadata(array $entity, array $fields, array $customPatch): array
    {
        $fieldMap = [];
        foreach ($fields as $field) {
            $fieldMap[$field['fieldname']] = $field;
        }

        return [
            'entity_name'  => $entity['name'],
            'module'       => $entity['module'],
            'table_name'   => strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $entity['name']) ?? $entity['name']),
            'entity'       => $entity,
            'fields'       => $fields,
            'field_map'    => $fieldMap,
            'custom_patch' => $customPatch,
            'compiled_at'  => gmdate(DATE_ATOM),
        ];
    }

    private function ensureModuleTable(): void
    {
        // Tự bảo đảm bảng module tồn tại để builder chạy độc lập, không phụ thuộc migration tay.
        $this->db->query(
            'CREATE TABLE IF NOT EXISTS sys_module (
                name VARCHAR(100) PRIMARY KEY,
                label VARCHAR(150) NOT NULL,
                namespace VARCHAR(255) NOT NULL,
                module_path VARCHAR(255) NOT NULL,
                is_active SMALLINT NOT NULL DEFAULT 1,
                created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );

    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeJsonObject(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (! is_array($decoded)) {
                throw new InvalidArgumentException('Invalid JSON object supplied.');
            }

            $value = $decoded;
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException('JSON payload must be an object.');
        }

        return array_is_list($value) ? [] : $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(mixed $value): array
    {
        if (is_array($value)) {
            return array_is_list($value) ? [] : $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_is_list($decoded) ? [] : $decoded;
    }

    private function normalizeEntityName(string $value): string
    {
        return $this->normalizeIdentifier($value);
    }

    private function normalizeIdentifier(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9_]+/', '_', $normalized) ?? '';
        $normalized = preg_replace('/_+/', '_', $normalized) ?? '';
        $normalized = trim($normalized, '_');

        if ($normalized === '' || preg_match('/^[0-9]/', $normalized) === 1) {
            return '';
        }

        return $normalized;
    }

    private function normalizeFieldType(string $value): string
    {
        // Input là kiểu nhập liệu chuẩn, map vật lý như Data để UI gần với Frappe hơn.
        $allowed = ['Input', 'Data', 'Int', 'Float', 'Select', 'Check', 'Text', 'Date', 'Code', 'Table'];

        if (! in_array($value, $allowed, true)) {
            throw new InvalidArgumentException("Invalid field type: {$value}");
        }

        return $value;
    }

    private function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(1, (int) $value);
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes'], true);
    }

    private function titleize(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }
}
