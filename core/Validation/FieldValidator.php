<?php

declare(strict_types=1);

namespace Volt\Core\Validation;

use Config\Services;

final class FieldValidator
{
    private const TYPE_RULES = [
        'Email'    => 'valid_email',
        'URL'      => 'valid_url_strict',
        'Int'      => 'integer',
        'Float'    => 'decimal',
        'Currency' => 'numeric',
        'Check'    => 'in_list[0,1]',
    ];

    public function validate(array $fields, array $payload): array
    {
        $rules = $this->buildRules($fields, $payload);
        if ($rules === []) {
            return [];
        }

        $validation = Services::validation();
        $validation->setRules($rules);

        if ($validation->run($payload)) {
            return [];
        }

        return $validation->getErrors();
    }

    private function buildRules(array $fields, array $payload): array
    {
        $rules = [];

        foreach ($fields as $field) {
            $fieldname = (string) ($field['fieldname'] ?? '');
            if ($fieldname === '') {
                continue;
            }

            $fieldRules = [];

            $isRequired = ! empty($field['reqd']);
            $hasValue = array_key_exists($fieldname, $payload)
                && $payload[$fieldname] !== null
                && $payload[$fieldname] !== '';

            if ($isRequired) {
                $fieldRules[] = 'required';
            }

            if (! $hasValue && ! $isRequired) {
                continue;
            }

            $fieldtype = (string) ($field['fieldtype'] ?? 'Input');
            $typeRule = self::TYPE_RULES[$fieldtype] ?? null;
            if ($typeRule !== null) {
                $fieldRules[] = $typeRule;
            }

            $length = isset($field['length']) ? (int) $field['length'] : 0;
            if ($length > 0 && in_array($fieldtype, ['Input', 'Data', 'Text', 'Code', 'Password'], true)) {
                $fieldRules[] = "max_length[{$length}]";
            }

            if ($fieldtype === 'Password') {
                $fieldRules[] = 'min_length[8]';
            }

            if ($fieldtype === 'Select' && ! empty($field['options'])) {
                $options = $this->parseOptions($field['options']);
                if ($options !== []) {
                    $fieldRules[] = 'in_list[' . implode(',', $options) . ']';
                }
            }

            if ($fieldRules !== []) {
                $rules[$fieldname] = implode('|', array_unique($fieldRules));
            }
        }

        return $rules;
    }

    private function parseOptions(mixed $options): array
    {
        if (is_string($options)) {
            $decoded = json_decode($options, true);
            if (is_array($decoded)) {
                return array_map('strval', $decoded);
            }
            $parts = explode("\n", $options);
            return array_values(array_filter(array_map('trim', $parts)));
        }

        if (is_array($options)) {
            return array_map('strval', $options);
        }

        return [];
    }
}
