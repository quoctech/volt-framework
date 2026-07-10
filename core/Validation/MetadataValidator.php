<?php

declare(strict_types=1);

namespace Volt\Core\Validation;

use InvalidArgumentException;

final class MetadataValidator
{
    private const ENTITY_NAME_PATTERN = '/^[A-Za-z][A-Za-z0-9_]*$/';
    private const MODULE_PATTERN = '/^[a-z][a-z0-9_]*$/';
    private const FIELD_NAME_PATTERN = '/^[a-z][a-z0-9_]*$/';

    /**
     * Common field types that the metadata layer accepts.
     *
     * Keep this list aligned with the builder UI and schema sync mapping.
     *
     * @var array<int, string>
     */
    private const FIELD_TYPES = [
        'Input',
        'Int',
        'Float',
        'Currency',
        'Data',
        'Text',
        'Check',
        'Date',
        'Datetime',
        'Time',
        'Email',
        'Phone',
        'URL',
        'Password',
        'Select',
        'MultiSelect',
        'JSON',
        'Link',
        'Table',
    ];

    public function assertEntityName(string $entityName): string
    {
        $entityName = trim($entityName);

        if ($entityName === '' || ! preg_match(self::ENTITY_NAME_PATTERN, $entityName)) {
            throw new InvalidArgumentException("Invalid entity name: {$entityName}");
        }

        return $entityName;
    }

    /**
     * @param array<string, mixed> $entity
     *
     * @return array<string, mixed>
     */
    public function normalizeEntityRow(array $entity): array
    {
        $name = $this->assertEntityName((string) ($entity['name'] ?? ''));
        $module = $this->normalizeModule((string) ($entity['module'] ?? ''));

        return [
            'name' => $name,
            'module' => $module,
            'issingle' => (int) ($entity['issingle'] ?? 0),
            'istable' => (int) ($entity['istable'] ?? 0),
            'autoname' => $this->normalizeAutoname((string) ($entity['autoname'] ?? '')),
            'states' => $this->normalizeJsonValue($entity['states'] ?? []),
            'custom_attributes' => $this->normalizeJsonValue($entity['custom_attributes'] ?? []),
        ];
    }

    /**
     * @param array<string, mixed> $field
     *
     * @return array<string, mixed>
     */
    public function normalizeFieldRow(array $field): array
    {
        $fieldname = $this->assertFieldName((string) ($field['fieldname'] ?? ''));
        $fieldtype = $this->normalizeFieldType((string) ($field['fieldtype'] ?? ''));
        $options = $field['options'] ?? '';

        if (is_array($options)) {
            $options = json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $options = is_string($options) ? $options : '';
        $isChildTable = $fieldtype === 'Table' && str_contains($options, 'separate');

        return [
            'id' => isset($field['id']) ? (int) $field['id'] : null,
            'parent' => $this->assertEntityName((string) ($field['parent'] ?? '')),
            'fieldname' => $fieldname,
            'label' => trim((string) ($field['label'] ?? '')),
            'fieldtype' => $fieldtype,
            'length' => isset($field['length']) ? (int) $field['length'] : null,
            'options' => $options,
            'reqd' => (int) ($field['reqd'] ?? 0),
            'read_only' => (int) ($field['read_only'] ?? 0),
            'hidden' => (int) ($field['hidden'] ?? 0),
            'idx' => (int) ($field['idx'] ?? 0),
            'is_child_table' => $isChildTable,
            'storage_mode' => $isChildTable ? 'separate_table' : 'embedded_jsonb',
        ];
    }

    /**
     * @param mixed $customMeta
     *
     * @return array<string, mixed>
     */
    public function normalizeCustomMeta(mixed $customMeta): array
    {
        return $this->normalizeJsonValue($customMeta);
    }

    public function normalizeFieldType(string $fieldType): string
    {
        $fieldType = trim($fieldType);

        if (! in_array($fieldType, self::FIELD_TYPES, true)) {
            throw new InvalidArgumentException("Invalid field type: {$fieldType}");
        }

        return $fieldType;
    }

    private function assertFieldName(string $fieldName): string
    {
        $fieldName = trim($fieldName);

        if ($fieldName === '' || ! preg_match(self::FIELD_NAME_PATTERN, $fieldName)) {
            throw new InvalidArgumentException("Invalid field name: {$fieldName}");
        }

        return $fieldName;
    }

    private function normalizeModule(string $module): string
    {
        $module = trim($module);

        if ($module === '') {
            return '';
        }

        if (! preg_match(self::MODULE_PATTERN, $module)) {
            throw new InvalidArgumentException("Invalid module name: {$module}");
        }

        return $module;
    }

    private function normalizeAutoname(string $autoname): string
    {
        $autoname = trim($autoname);

        return $autoname === '' ? 'HASH' : strtoupper($autoname);
    }

    /**
     * @param mixed $value
     *
     * @return array<string, mixed>
     */
    private function normalizeJsonValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return $decoded;
            }

            $unserialized = @unserialize($value, ['allowed_classes' => false]);

            if (is_array($unserialized)) {
                return $unserialized;
            }
        }

        return [];
    }
}
