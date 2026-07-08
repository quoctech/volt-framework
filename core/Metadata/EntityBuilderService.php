<?php

declare(strict_types=1);

namespace Volt\Core\Metadata;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use RuntimeException;
use Volt\Core\Database\VoltDatabase;
use Volt\Core\Validation\MetadataValidator;

final class EntityBuilderService
{
    private BaseConnection $db;
    private MetadataValidator $validator;

    public function __construct(?BaseConnection $db = null, ?MetadataValidator $validator = null)
    {
        $this->db = $db ?? VoltDatabase::connection();
        $this->validator = $validator ?? service('voltMetadataValidator');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listEntities(): array
    {
        return $this->db->table('sys_entity')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @param array<string, mixed> $entity
     * @param array<int, array<string, mixed>> $fields
     *
     * @return array<string, mixed>
     */
    public function createEntity(array $entity, array $fields): array
    {
        $normalizedEntity = $this->validator->normalizeEntityRow($entity);
        $normalizedEntity['states'] = json_encode($normalizedEntity['states'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $normalizedEntity['custom_attributes'] = json_encode($normalizedEntity['custom_attributes'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';

        if ($fields === []) {
            throw new InvalidArgumentException('Entity phải có ít nhất một field.');
        }

        $normalizedFields = [];
        foreach ($fields as $index => $field) {
            if (! is_array($field)) {
                continue;
            }

            $field['parent'] = $normalizedEntity['name'];
            $field['idx'] = $index + 1;
            $normalizedFields[] = $this->validator->normalizeFieldRow($field);
        }

        if ($normalizedFields === []) {
            throw new InvalidArgumentException('Field list không hợp lệ.');
        }

        $this->db->transStart();

        $exists = $this->db->table('sys_entity')
            ->where('name', $normalizedEntity['name'])
            ->countAllResults() > 0;

        if ($exists) {
            $this->db->transRollback();
            throw new RuntimeException('Entity đã tồn tại.');
        }

        $this->db->table('sys_entity')->insert($normalizedEntity);

        foreach ($normalizedFields as $field) {
            $this->db->table('sys_entity_field')->insert([
                'parent' => $field['parent'],
                'fieldname' => $field['fieldname'],
                'label' => $field['label'],
                'fieldtype' => $field['fieldtype'],
                'length' => $field['length'],
                'options' => $field['options'],
                'reqd' => $field['reqd'],
                'read_only' => $field['read_only'],
                'hidden' => $field['hidden'],
                'idx' => $field['idx'],
            ]);
        }

        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new RuntimeException('Không thể tạo Entity.');
        }

        return [
            'entity' => $normalizedEntity,
            'fields' => $normalizedFields,
        ];
    }
}
