<?php

declare(strict_types=1);

namespace Volt\Core\Report\Services;

use CodeIgniter\Database\BaseConnection;
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

        [$fromClause, $bindings] = $this->buildFromClause($entities);

        $hasAggregation = false;
        $selectParts = [];
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
                $selectParts[] = "{$agg}({$expression}) AS " . $this->quoteLabel($label);
                $hasAggregation = true;
            } else {
                $selectParts[] = "{$expression} AS " . $this->quoteLabel($label);
                $groupByParts[] = $expression;
            }
        }

        if ($selectParts === []) {
            throw new InvalidArgumentException('At least one column is required.');
        }

        $sql = 'SELECT ' . implode(', ', $selectParts) . ' FROM ' . $fromClause;

        [$whereSql, $whereBindings] = $this->buildWhereClause($filters);
        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
            $bindings = array_merge($bindings, $whereBindings);
        }

        if ($hasAggregation && $groupByParts !== []) {
            $sql .= ' GROUP BY ' . implode(', ', $groupByParts);
        }

        $having = $query['having'] ?? [];
        if ($having !== []) {
            $havingParts = [];
            foreach ($having as $h) {
                $havingParts[] = "{$h['field']} {$h['operator']} ?";
                $bindings[] = $h['value'];
            }
            $sql .= ' HAVING ' . implode(' AND ', $havingParts);
        }

        foreach ($orderBy as $order) {
            $dir = strtoupper($order['dir'] ?? 'ASC');
            $dir = in_array($dir, ['ASC', 'DESC'], true) ? $dir : 'ASC';
            $sql .= ' ORDER BY ' . ($order['field'] ?? '1') . " {$dir}";
        }

        $sql .= " LIMIT {$limit}";

        return $this->executeSqlQuery($sql, $bindings, $columns);
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

        [$fromClause, $bindings] = $this->buildFromClause($entities);

        $selectParts = [];
        $columnsMeta = [];
        $fieldsForSelect = [];

        foreach ($rowFields as $rf) {
            $selectParts[] = "{$rf} AS " . $this->quoteLabel($rf);
            $columnsMeta[] = ['field' => $rf, 'label' => $rf, 'aggregation' => null, 'is_row' => true];
            $fieldsForSelect[] = $rf;
        }

        if ($colField !== '') {
            $selectParts[] = "{$colField} AS " . $this->quoteLabel($colField);
            $fieldsForSelect[] = $colField;
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
            $selectParts[] = "{$expr} AS " . $this->quoteLabel($label);
            $columnsMeta[] = [
                'field'       => $field,
                'label'       => $label,
                'aggregation' => $agg ?: null,
                'is_value'    => true,
            ];
            $fieldsForSelect[] = $expr;
        }

        if ($selectParts === []) {
            throw new InvalidArgumentException('At least one field is required.');
        }

        $sql = 'SELECT ' . implode(', ', $selectParts) . ' FROM ' . $fromClause;

        [$whereSql, $whereBindings] = $this->buildWhereClause($filters);
        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
            $bindings = array_merge($bindings, $whereBindings);
        }

        if ($rowFields !== []) {
            $sql .= ' GROUP BY ' . implode(', ', $rowFields) . ", {$colField}";
        }

        $sql .= " LIMIT {$limit}";

        return $this->executeSqlQuery($sql, $bindings, $columnsMeta);
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

    private function buildFromClause(array $entities): array
    {
        $fromParts = [];
        $bindings = [];

        foreach ($entities as $i => $entity) {
            $entityName = $entity['entity'] ?? '';
            $alias = $entity['alias'] ?? $entityName;
            $tableName = TableNameResolver::entity($entityName);

            if ($i === 0) {
                $fromParts[] = "\"{$tableName}\" AS {$alias}";
            } else {
                $joinType = strtoupper(mb_trim($entity['join_type'] ?? 'LEFT'));
                if (! in_array($joinType, ['LEFT', 'RIGHT', 'INNER', 'FULL', 'CROSS'], true)) {
                    $joinType = 'LEFT';
                }
                $joinOn = $entity['join_on'] ?? '';
                $fromParts[] = "{$joinType} JOIN \"{$tableName}\" AS {$alias} ON {$joinOn}";
            }
        }

        return [implode(' ', $fromParts), $bindings];
    }

    private function buildWhereClause(array $filters): array
    {
        if ($filters === []) {
            return ['', []];
        }

        $parts = [];
        $bindings = [];

        foreach ($filters as $filter) {
            $field = $filter['field'] ?? '';
            $operator = mb_strtolower(mb_trim($filter['operator'] ?? '='));
            $value = $filter['value'] ?? '';

            if ($field === '') {
                continue;
            }

            if (in_array($operator, ['in', 'not in'], true)) {
                $vals = is_array($value) ? $value : [$value];
                $placeholders = implode(', ', array_fill(0, count($vals), '?'));
                $parts[] = "{$field} {$operator} ({$placeholders})";
                $bindings = array_merge($bindings, $vals);
            } elseif ($operator === 'between' && is_array($value) && count($value) === 2) {
                $parts[] = "{$field} BETWEEN ? AND ?";
                $bindings[] = (string) $value[0];
                $bindings[] = (string) $value[1];
            } elseif (in_array($operator, ['like', 'not like'], true)) {
                $parts[] = "{$field} {$operator} ?";
                $bindings[] = '%' . $value . '%';
            } elseif ($operator === 'is' || $operator === 'is not') {
                $parts[] = "{$field} {$operator} ?";
                $bindings[] = $value;
            } else {
                if (! in_array($operator, ['=', '!=', '<>', '>', '>=', '<', '<='], true)) {
                    $operator = '=';
                }
                $parts[] = "{$field} {$operator} ?";
                $bindings[] = $value;
            }
        }

        return [implode(' AND ', $parts), $bindings];
    }

    private function executeSqlQuery(string $sql, array $bindings, array $columns): array
    {
        $result = $this->db->query($sql, $bindings);
        $rows = $result->getResultArray();

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
}
