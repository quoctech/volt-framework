<?php

declare(strict_types=1);

namespace Volt\Core\Database;

use CodeIgniter\Database\BaseBuilder;
use Volt\Core\Security\PermissionResolver;

final class QueryParser
{
    private const ALLOWED_OPERATORS = [
        '=', '!=', '>', '>=', '<', '<=',
        'like', 'not like',
        'in', 'not in',
        'between',
    ];

    private const SYSTEM_FIELDS = [
        'name', 'owner', 'creation', 'modified',
        'docstatus', 'workflow_state', 'amended_from',
    ];

    private const PER_PAGE_OPTIONS = [10, 20, 50, 100, 200, 500, 1000, 2500];

    private const DEFAULT_PER_PAGE = 50;

    private readonly BaseBuilder $builder;

    private readonly string $entityName;

    private readonly ?PermissionResolver $permissionResolver;

    /** @var array<string, string> fieldname => fieldtype */
    private readonly array $entityFields;

    /** @var list<string> */
    private array $readableFields = [];

    /** @var list<string>|null null = select * */
    private ?array $selectedFields = null;

    private int $page = 1;

    private int $perPage = self::DEFAULT_PER_PAGE;

    private const STRING_TYPES = [
        'Data', 'Input', 'Text', 'Small Text', 'Long Text',
        'Link', 'Read Only', 'Password', 'Select',
    ];

    public function __construct(
        BaseBuilder $builder,
        string $entityName,
        ?PermissionResolver $permissionResolver = null,
        ?array $compiledMeta = null,
    ) {
        $this->builder = $builder;
        $this->entityName = $entityName;
        $this->permissionResolver = $permissionResolver;
        $this->entityFields = $this->extractFields($compiledMeta);
        $this->readableFields = $this->resolveReadableFields();
    }

    /**
     * Parse and apply all query parameters to the builder.
     *
     * @param array<string, mixed> $params
     * @return array{builder: BaseBuilder, total: int, page: int, perPage: int}
     */
    public function apply(array $params): array
    {
        $this->applySoftDeleteFilter();
        $this->parseFields($params);
        $this->parseFreeTextSearch($params);
        $this->parseFilters($params);
        $this->parseOrderBy($params);
        $this->parsePagination($params);

        $countBuilder = clone $this->builder;
        $total = (int) $countBuilder->countAllResults(false);

        $this->builder->limit($this->perPage, ($this->page - 1) * $this->perPage);

        return [
            'builder' => $this->builder,
            'total'   => $total,
            'page'    => $this->page,
            'perPage' => $this->perPage,
        ];
    }

    /**
     * Loại bản ghi đã xóa mềm khỏi kết quả truy vấn.
     * An toàn với entity chế độ xóa thẳng: cột deleted_at luôn NULL.
     */
    private function applySoftDeleteFilter(): void
    {
        $db = $this->builder->db();
        if (! $db instanceof \CodeIgniter\Database\BaseConnection) {
            return;
        }

        $table = $db->prefixTable($this->builder->getTable() ?? '');
        if ($table === '') {
            return;
        }

        if (! $db->fieldExists('deleted_at', $table)) {
            return;
        }

        $this->builder->where($table . '.deleted_at', null);
    }

    /** @return list<string> Fields selected (or readable fields if none specified) */
    public function getSelectedFields(): array
    {
        return $this->selectedFields ?? $this->readableFields;
    }

    // ========================================================================
    //  FIELDS
    // ========================================================================

    private function parseFields(array $params): void
    {
        $raw = $params['fields'] ?? null;
        if ($raw === null || $raw === '' || $raw === []) {
            return;
        }

        $requested = is_string($raw)
            ? array_map('trim', explode(',', $raw))
            : (is_array($raw) ? $raw : []);

        $valid = [];
        foreach ($requested as $field) {
            $field = mb_trim((string) $field);
            if ($field === '' || ! $this->isValidField($field)) {
                continue;
            }
            if (! in_array($field, $this->readableFields, true)) {
                continue;
            }
            $valid[] = $field;
        }

        if ($valid !== []) {
            $this->selectedFields = $valid;
            $this->builder->select($valid);
        }
    }

    // ========================================================================
    //  FREE-TEXT SEARCH (q)
    // ========================================================================

    private function parseFreeTextSearch(array $params): void
    {
        $q = mb_trim((string) ($params['q'] ?? ''));
        if ($q === '') {
            return;
        }

        $stringFields = $this->resolveSearchableFields();
        if ($stringFields === []) {
            return;
        }

        $this->builder->groupStart();
        $first = true;
        foreach ($stringFields as $field) {
            if ($first) {
                $this->builder->like($field, $q);
                $first = false;
            } else {
                $this->builder->orLike($field, $q);
            }
        }
        $this->builder->groupEnd();
    }

    /** @return list<string> */
    private function resolveSearchableFields(): array
    {
        $systemText = ['name', 'owner'];

        $entityText = array_keys(array_filter(
            $this->entityFields,
            fn(string $type): bool => in_array($type, self::STRING_TYPES, true),
        ));

        return array_values(array_intersect(
            array_merge($systemText, $entityText),
            $this->readableFields,
        ));
    }

    // ========================================================================
    //  FILTERS
    // ========================================================================

    /**
     * @param array<string, mixed> $params
     * @return list<array{field: string, op: string, value: mixed}>
     */
    private function parseFilters(array $params): array
    {
        $raw = $params['filters'] ?? null;
        if ($raw === null || $raw === '' || $raw === []) {
            return [];
        }

        $parsed = is_string($raw)
            ? (json_decode($raw, true) ?? [])
            : (is_array($raw) ? $raw : []);

        if (! is_array($parsed) || $parsed === []) {
            return [];
        }

        $filters = [];
        foreach ($parsed as $filter) {
            $resolved = $this->resolveFilter($filter);
            if ($resolved !== null) {
                $filters[] = $resolved;
            }
        }

        $this->applyFilterClauses($filters);

        return $filters;
    }

    /**
     * @param array<int, mixed> $filter
     * @return array{field: string, op: string, value: mixed}|null
     */
    private function resolveFilter(mixed $filter): ?array
    {
        if (! is_array($filter) || count($filter) < 2) {
            return null;
        }

        $field = mb_trim((string) ($filter[0] ?? ''));
        $op   = mb_strtolower(mb_trim((string) ($filter[1] ?? '')));
        $value = $filter[2] ?? null;

        if ($field === '' || ! $this->isValidField($field)) {
            return null;
        }
        if (! in_array($op, self::ALLOWED_OPERATORS, true)) {
            return null;
        }
        if (! in_array($field, $this->readableFields, true)) {
            return null;
        }

        return ['field' => $field, 'op' => $op, 'value' => $value];
    }

    /**
     * @param list<array{field: string, op: string, value: mixed}> $filters
     */
    private function applyFilterClauses(array $filters): void
    {
        foreach ($filters as $f) {
            $this->applyFilterClause($f['field'], $f['op'], $f['value']);
        }
    }

    private function applyFilterClause(string $field, string $op, mixed $value): void
    {
        match ($op) {
            '='        => $this->builder->where($field, $value),
            '!='       => $this->builder->where("{$field} !=", $value),
            '>'        => $this->builder->where("{$field} >", $value),
            '>='       => $this->builder->where("{$field} >=", $value),
            '<'        => $this->builder->where("{$field} <", $value),
            '<='       => $this->builder->where("{$field} <=", $value),
            'like'     => $this->builder->like($field, $value),
            'not like' => $this->builder->notLike($field, $value),
            'in'       => $this->builder->whereIn($field, is_array($value) ? $value : [$value]),
            'not in'   => $this->builder->whereNotIn($field, is_array($value) ? $value : [$value]),
            'between'  => $this->applyBetween($field, $value),
        };
    }

    private function applyBetween(string $field, mixed $value): void
    {
        if (! is_array($value) || count($value) < 2) {
            return;
        }
        $this->builder->where("{$field} >=", $value[0]);
        $this->builder->where("{$field} <=", $value[1]);
    }

    // ========================================================================
    //  ORDER BY
    // ========================================================================

    private function parseOrderBy(array $params): void
    {
        $raw = mb_trim((string) ($params['order_by'] ?? ''));
        if ($raw === '') {
            return;
        }

        $parts = preg_split('/\s+/', $raw, 2);
        $field = $parts[0] ?? '';
        $dir   = mb_strtoupper(mb_trim($parts[1] ?? ''));

        if ($field === '' || ! $this->isValidField($field)) {
            return;
        }
        if (! in_array($dir, ['ASC', 'DESC'], true)) {
            $dir = 'ASC';
        }

        $this->builder->orderBy($field, $dir);
    }

    // ========================================================================
    //  PAGINATION
    // ========================================================================

    private function parsePagination(array $params): void
    {
        $this->page    = max(1, (int) ($params['page'] ?? 1));
        $this->perPage = $this->clampPerPage((int) ($params['per_page'] ?? self::DEFAULT_PER_PAGE));
    }

    private function clampPerPage(int $perPage): int
    {
        if (in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            return $perPage;
        }

        return self::DEFAULT_PER_PAGE;
    }

    // ========================================================================
    //  FIELD VALIDATION
    // ========================================================================

    private function isValidField(string $field): bool
    {
        if (in_array($field, self::SYSTEM_FIELDS, true)) {
            return true;
        }

        return isset($this->entityFields[$field]);
    }

    /**
     * @return list<string>
     */
    private function resolveReadableFields(): array
    {
        $all = array_merge(self::SYSTEM_FIELDS, array_keys($this->entityFields));

        if ($this->permissionResolver === null) {
            return $all;
        }

        return array_values(array_filter(
            $all,
            fn(string $field): bool => $this->permissionResolver->can(
                $this->entityName, 'read', null, $field,
            ),
        ));
    }

    /**
     * @param array<string, mixed>|null $compiledMeta
     * @return array<string, string> fieldname => fieldtype
     */
    private function extractFields(?array $compiledMeta): array
    {
        if ($compiledMeta === null) {
            return [];
        }

        $fields = $compiledMeta['fields'] ?? [];
        if (! is_array($fields)) {
            return [];
        }

        $map = [];
        foreach ($fields as $key => $field) {
            if (is_array($field) && isset($field['fieldname'])) {
                $map[(string) $field['fieldname']] = (string) ($field['fieldtype'] ?? 'Data');
            } elseif (is_string($key)) {
                $map[$key] = 'Data';
            }
        }

        return $map;
    }
}
