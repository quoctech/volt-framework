<?php

declare(strict_types=1);

namespace Volt\Core\Report\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseBuilder;
use InvalidArgumentException;
use Volt\Core\Database\TableNameResolver;
use Volt\Core\Database\VoltDatabase;

class ReportQueryBuilder
{
    private readonly BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? VoltDatabase::connection();
    }

    public function build(array $query): array
    {
        $type = $query['type'] ?? 'query';

        if ($type === 'sql') {
            return $this->executeSql($query);
        }

        if ($type === 'pivot') {
            return $this->buildFlatQuery($query);
        }

        return $this->buildSelectQuery($query);
    }

    public function getEntityFields(string $entityName): array
    {
        $entityName = mb_trim($entityName);
        if ($entityName === '') {
            return [];
        }

        $systemFields = [
            ['fieldname' => 'name', 'label' => 'ID', 'fieldtype' => 'Data', 'options' => ''],
            ['fieldname' => 'owner', 'label' => 'Owner', 'fieldtype' => 'Data', 'options' => ''],
            ['fieldname' => 'creation', 'label' => 'Created On', 'fieldtype' => 'Datetime', 'options' => ''],
            ['fieldname' => 'modified', 'label' => 'Modified', 'fieldtype' => 'Datetime', 'options' => ''],
            ['fieldname' => 'docstatus', 'label' => 'Doc Status', 'fieldtype' => 'Int', 'options' => ''],
        ];

        $fields = $this->db->table('sys_entity_field')
            ->select('fieldname, label, fieldtype, options')
            ->where('parent', $entityName)
            ->orderBy('idx', 'ASC')
            ->orderBy('fieldname', 'ASC')
            ->get()
            ->getResultArray();

        return array_merge($systemFields, array_map(static fn (array $f): array => [
            'fieldname' => (string) ($f['fieldname'] ?? ''),
            'label'     => (string) ($f['label'] ?? ''),
            'fieldtype' => (string) ($f['fieldtype'] ?? 'Data'),
            'options'   => (string) ($f['options'] ?? ''),
        ], $fields));
    }

    public function suggestJoins(array $entities): array
    {
        if (count($entities) < 2) {
            return [];
        }

        $suggestions = [];
        $entityNames = array_map(static fn (array $e): string => mb_strtolower($e['entity'] ?? ''), $entities);

        for ($i = 1; $i < count($entities); $i++) {
            $currentEntity = $entities[$i]['entity'] ?? '';
            $currentAlias = $entities[$i]['alias'] ?? $currentEntity;
            $targetAlias = $entities[0]['alias'] ?? $entities[0]['entity'] ?? '';

            $fields = $this->db->table('sys_entity_field')
                ->select('fieldname, options')
                ->where('parent', $currentEntity)
                ->where('fieldtype', 'Link')
                ->get()
                ->getResultArray();

            foreach ($fields as $field) {
                $target = mb_strtolower(mb_trim((string) ($field['options'] ?? '')));
                if ($target !== '' && in_array($target, $entityNames, true)) {
                    $suggestions[] = [
                        'from_alias' => $currentAlias,
                        'from_field' => (string) ($field['fieldname'] ?? ''),
                        'to_alias'   => $targetAlias,
                        'to_field'   => 'name',
                        'condition'  => "{$currentAlias}.{$field['fieldname']} = {$targetAlias}.name",
                    ];
                }
            }
        }

        return $suggestions;
    }

    private function buildSelectQuery(array $query): array
    {
        $entities = $query['entities'] ?? [];
        $columns = $query['columns'] ?? [];
        $filters = $query['filters'] ?? [];
        $orderBy = $query['order_by'] ?? [];
        $limit = min(max((int) ($query['limit'] ?? 100), 1), 5000);

        if ($entities === []) {
            throw new InvalidArgumentException('At least one entity is required.');
        }

        $entityAliasMap = $this->buildEntityAliasMap($entities);

        $columns = $this->resolveColumnAliases($columns, $entityAliasMap);
        $filters = $this->resolveFilterAliases($filters, $entityAliasMap);
        $orderBy = $this->resolveOrderByAliases($orderBy, $entityAliasMap);

        $builder = $this->buildQueryBuilder($entities);

        $hasAggregation = false;
        $groupByParts = [];

        foreach ($columns as $col) {
            $expression = $col['field'] ?? '';
            if ($expression === '') {
                continue;
            }
            $label = $col['label'] ?? $expression;

            if (! empty($col['aggregation'])) {
                $agg = strtoupper(mb_trim($col['aggregation']));
                $this->validateAggregation($agg);
                $builder->select("{$agg}({$expression}) AS " . $this->quoteLabel($label));
                $hasAggregation = true;
            } else {
                $builder->select("{$expression} AS " . $this->quoteLabel($label));
                $groupByParts[] = $expression;
            }
        }

        if ($hasAggregation && $groupByParts !== []) {
            $builder->groupBy(implode(', ', $groupByParts));
        }

        $this->applyFilters($builder, $filters);

        $having = $query['having'] ?? [];
        if ($having !== []) {
            foreach ($having as $h) {
                $builder->having("{$h['field']} {$h['operator']}", $h['value'], true);
            }
        }

        foreach ($orderBy as $order) {
            $dir = strtoupper($order['dir'] ?? 'ASC');
            $dir = in_array($dir, ['ASC', 'DESC'], true) ? $dir : 'ASC';
            $builder->orderBy($order['field'] ?? '1', $dir);
        }

        $builder->limit($limit);
        $rows = $builder->get()->getResultArray();

        return $this->buildResult($columns, $rows);
    }

    private function buildFlatQuery(array $query): array
    {
        $entities = $query['entities'] ?? [];
        $rowFields = $query['row_fields'] ?? [];
        $colField = $query['column_field'] ?? '';
        $values = $query['values'] ?? [];
        $filters = $query['filters'] ?? [];
        $limit = min(max((int) ($query['limit'] ?? 5000), 1), 10000);

        if ($entities === []) {
            throw new InvalidArgumentException('At least one entity is required.');
        }

        $entityAliasMap = $this->buildEntityAliasMap($entities);
        $rowFields = array_map(fn(string $f) => $this->resolveEntityPrefix($f, $entityAliasMap), $rowFields);
        $colField = $colField !== '' ? $this->resolveEntityPrefix($colField, $entityAliasMap) : $colField;
        foreach ($values as &$v) {
            if (! empty($v['field'])) {
                $v['field'] = $this->resolveEntityPrefix($v['field'], $entityAliasMap);
            }
        }
        unset($v);
        $filters = $this->resolveFilterAliases($filters, $entityAliasMap);

        $builder = $this->buildQueryBuilder($entities);

        $columnsMeta = [];

        foreach ($rowFields as $rf) {
            $builder->select("{$rf} AS " . $this->quoteLabel($rf));
            $columnsMeta[] = ['field' => $rf, 'label' => $rf, 'aggregation' => null, 'is_row' => true];
        }

        if ($colField !== '') {
            $builder->select("{$colField} AS " . $this->quoteLabel($colField));
        }

        foreach ($values as $v) {
            $field = $v['field'] ?? '';
            $agg = ! empty($v['aggregation']) ? strtoupper(mb_trim($v['aggregation'])) : '';
            if ($field === '') {
                continue;
            }
            if ($agg !== '') {
                $this->validateAggregation($agg);
                $expr = "{$agg}({$field})";
            } else {
                $expr = $field;
            }
            $label = $v['label'] ?? $field;
            $builder->select("{$expr} AS " . $this->quoteLabel($label));
            $columnsMeta[] = [
                'field'       => $field,
                'label'       => $label,
                'aggregation' => $agg ?: null,
                'is_value'    => true,
            ];
        }

        if ($rowFields !== []) {
            $groupBy = implode(', ', $rowFields);
            if ($colField !== '') {
                $groupBy .= ', ' . $colField;
            }
            $builder->groupBy($groupBy);
        }

        $this->applyFilters($builder, $filters);

        $builder->limit($limit);
        $rows = $builder->get()->getResultArray();

        if ($columnsMeta === []) {
            throw new InvalidArgumentException('At least one field is required.');
        }

        return [
            'columns' => $columnsMeta,
            'rows'    => $rows,
            'total'   => count($rows),
        ];
    }

    private function executeSql(array $query): array
    {
        $rawSql = mb_trim($query['sql'] ?? '');

        if ($rawSql === '') {
            throw new InvalidArgumentException('SQL query is empty.');
        }

        $upper = mb_strtoupper(mb_trim($rawSql));

        if (! str_starts_with($upper, 'SELECT')) {
            throw new InvalidArgumentException('Only SELECT queries are allowed.');
        }

        $forbidden = ['DROP ', 'ALTER ', 'TRUNCATE ', 'DELETE ', 'INSERT ', 'UPDATE ', 'CREATE ', 'EXECUTE ', 'CALL ', 'COPY '];
        foreach ($forbidden as $keyword) {
            if (str_contains($upper, $keyword)) {
                throw new InvalidArgumentException("SQL contains forbidden keyword: {$keyword}");
            }
        }

        $limit = min(max((int) ($query['limit'] ?? 100), 1), 5000);
        $limitedSql = rtrim($rawSql, '; ');
        $limitedSql .= " LIMIT {$limit}";

        $result = $this->db->query($limitedSql);
        $rows = $result->getResultArray();

        $columns = [];
        foreach ($query['columns'] ?? [] as $col) {
            $columns[] = [
                'field'       => $col['field'] ?? '',
                'label'       => $col['label'] ?? ($col['field'] ?? ''),
                'aggregation' => $col['aggregation'] ?? null,
            ];
        }

        if ($columns === [] && $rows !== []) {
            $first = $rows[0];
            foreach (array_keys($first) as $key) {
                $columns[] = ['field' => (string) $key, 'label' => (string) $key, 'aggregation' => null];
            }
        }

        return [
            'columns' => $columns,
            'rows'    => $rows,
            'total'   => count($rows),
        ];
    }

    private function buildQueryBuilder(array $entities): BaseBuilder
    {
        $first = $entities[0];
        $firstEntity = $first['entity'] ?? '';
        $firstAlias = $first['alias'] ?? $firstEntity;
        $firstTable = TableNameResolver::entity($firstEntity);
        $builder = $this->db->table("{$firstTable} AS {$firstAlias}");

        for ($i = 1; $i < count($entities); $i++) {
            $entity = $entities[$i];
            $entityName = $entity['entity'] ?? '';
            $alias = $entity['alias'] ?? $entityName;
            $tableName = TableNameResolver::entity($entityName);
            $joinType = strtoupper(mb_trim($entity['join_type'] ?? 'LEFT'));

            if (! in_array($joinType, ['LEFT', 'RIGHT', 'INNER', 'FULL', 'CROSS'], true)) {
                $joinType = 'LEFT';
            }

            $joinOn = $entity['join_on'] ?? '';
            $builder->join("{$tableName} AS {$alias}", $joinOn, $joinType);
        }

        return $builder;
    }

    private function applyFilters(BaseBuilder $builder, array $filters): void
    {
        foreach ($filters as $filter) {
            $field = $filter['field'] ?? '';
            $operator = mb_strtolower(mb_trim($filter['operator'] ?? '='));
            $value = $filter['value'] ?? '';

            if ($field === '') {
                continue;
            }

            switch ($operator) {
                case 'in':
                    $vals = is_array($value) ? $value : [$value];
                    $builder->whereIn($field, $vals);
                    break;

                case 'not in':
                    $vals = is_array($value) ? $value : [$value];
                    $builder->whereNotIn($field, $vals);
                    break;

                case 'between':
                    $vals = is_array($value) && count($value) === 2 ? $value : [$value, $value];
                    $builder->groupStart();
                    $builder->where("{$field} >= ?", $vals[0]);
                    $builder->where("{$field} <= ?", $vals[1]);
                    $builder->groupEnd();
                    break;

                case 'like':
                    $builder->like($field, $value);
                    break;

                case 'not like':
                    $builder->notLike($field, $value);
                    break;

                case 'is':
                    $builder->where($field, $value === '' ? null : $value);
                    break;

                case 'is not':
                    $builder->where("{$field} IS NOT", $value === '' ? null : $value);
                    break;

                default:
                    if (! in_array($operator, ['=', '!=', '<>', '>', '>=', '<', '<='], true)) {
                        $operator = '=';
                    }
                    $builder->where("{$field} {$operator}", $value);
                    break;
            }
        }
    }

    private function buildResult(array $columns, array $rows): array
    {
        $colMeta = [];
        foreach ($columns as $col) {
            $colMeta[] = [
                'field'       => $col['field'] ?? '',
                'label'       => $col['label'] ?? ($col['field'] ?? ''),
                'aggregation' => $col['aggregation'] ?? null,
            ];
        }

        return [
            'columns' => $colMeta,
            'rows'    => $rows,
            'total'   => count($rows),
        ];
    }

    private function validateAggregation(string $agg): void
    {
        $allowed = ['SUM', 'COUNT', 'AVG', 'MIN', 'MAX', 'COUNT_DISTINCT'];
        if (! in_array($agg, $allowed, true)) {
            throw new InvalidArgumentException("Invalid aggregation: {$agg}");
        }
    }

    private function quoteLabel(string $label): string
    {
        return '"' . str_replace('"', '""', $label) . '"';
    }

    private function buildEntityAliasMap(array $entities): array
    {
        $map = [];
        foreach ($entities as $e) {
            $name = $e['entity'] ?? '';
            $alias = $e['alias'] ?? $name;
            if ($name !== '') {
                $map[$name] = $alias;
            }
        }
        return $map;
    }

    private function resolveEntityPrefix(string $field, array $aliasMap): string
    {
        foreach ($aliasMap as $entityName => $alias) {
            $prefix = $entityName . '.';
            if (str_starts_with($field, $prefix)) {
                return $alias . '.' . mb_substr($field, mb_strlen($prefix));
            }
        }
        return $field;
    }

    private function resolveColumnAliases(array $columns, array $aliasMap): array
    {
        foreach ($columns as &$col) {
            if (! empty($col['field'])) {
                $col['field'] = $this->resolveEntityPrefix($col['field'], $aliasMap);
            }
        }
        return $columns;
    }

    private function resolveFilterAliases(array $filters, array $aliasMap): array
    {
        foreach ($filters as &$filter) {
            if (! empty($filter['field'])) {
                $filter['field'] = $this->resolveEntityPrefix($filter['field'], $aliasMap);
            }
        }
        return $filters;
    }

    private function resolveOrderByAliases(array $orderBy, array $aliasMap): array
    {
        foreach ($orderBy as &$order) {
            if (! empty($order['field'])) {
                $order['field'] = $this->resolveEntityPrefix($order['field'], $aliasMap);
            }
        }
        return $orderBy;
    }
}
