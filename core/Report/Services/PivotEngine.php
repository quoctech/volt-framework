<?php

declare(strict_types=1);

namespace Volt\Core\Report\Services;

use InvalidArgumentException;

class PivotEngine
{
    private readonly ReportQueryBuilder $queryBuilder;

    public function __construct(?ReportQueryBuilder $queryBuilder = null)
    {
        $this->queryBuilder = $queryBuilder ?? new ReportQueryBuilder();
    }

    public function build(array $query): array
    {
        $rowFields = $query['row_fields'] ?? [];
        $colField = $query['column_field'] ?? '';
        $values = $query['values'] ?? [];

        if ($rowFields === []) {
            throw new InvalidArgumentException('At least one row field is required.');
        }
        if ($colField === '') {
            throw new InvalidArgumentException('Column field is required.');
        }
        if ($values === []) {
            throw new InvalidArgumentException('At least one value field is required.');
        }

        $flatResult = $this->queryBuilder->build($query);
        $rows = $flatResult['rows'];

        return $this->pivot($rows, $rowFields, $colField, $values);
    }

    private function pivot(array $rows, array $rowFields, string $colField, array $values): array
    {
        $aggregation = ! empty($values[0]['aggregation']) ? mb_strtoupper(mb_trim($values[0]['aggregation'])) : '';
        $valueField = $values[0]['field'] ?? '';
        $valueLabel = $values[0]['label'] ?? $valueField;

        $distinctCols = [];
        $grouped = [];

        foreach ($rows as $row) {
            $colValue = (string) ($row[$colField] ?? '');
            if ($colValue !== '' && ! in_array($colValue, $distinctCols, true)) {
                $distinctCols[] = $colValue;
            }

            $keyParts = [];
            foreach ($rowFields as $rf) {
                $keyParts[] = (string) ($row[$rf] ?? '');
            }
            $key = implode("\x00", $keyParts);

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'keys'   => $keyParts,
                    'values' => [],
                ];
            }

            $cellValue = isset($row[$valueField]) && $row[$valueField] !== '' ? (float) ($row[$valueField]) : 0;
            $grouped[$key]['values'][] = [
                'col'   => $colValue,
                'value' => $cellValue,
            ];
        }

        sort($distinctCols);

        $pivotRows = [];
        foreach ($grouped as $g) {
            $pivotRow = [];

            foreach ($rowFields as $i => $rf) {
                $pivotRow[$rf] = $g['keys'][$i];
            }

            $aggMap = [];
            foreach ($g['values'] as $entry) {
                $col = $entry['col'];
                if (! isset($aggMap[$col])) {
                    $aggMap[$col] = [];
                }
                $aggMap[$col][] = $entry['value'];
            }

            foreach ($distinctCols as $col) {
                $cellValues = $aggMap[$col] ?? [];
                $pivotRow[$col] = $this->aggregate($cellValues, $aggregation);
            }

            $pivotRows[] = $pivotRow;
        }

        return [
            'columns'     => $this->buildColumnMeta($rowFields, $distinctCols, $valueLabel),
            'rows'        => $pivotRows,
            'total'       => count($pivotRows),
            'row_fields'  => $rowFields,
            'col_field'   => $colField,
            'col_values'  => $distinctCols,
            'value_label' => $valueLabel,
            'aggregation' => $aggregation,
        ];
    }

    private function aggregate(array $values, string $aggregation): float|int
    {
        if ($values === []) {
            return 0;
        }

        return match ($aggregation) {
            'SUM'   => array_sum($values),
            'COUNT' => count($values),
            'AVG'   => array_sum($values) / count($values),
            'MIN'   => min($values),
            'MAX'   => max($values),
            default => array_sum($values),
        };
    }

    private function buildColumnMeta(array $rowFields, array $distinctCols, string $valueLabel): array
    {
        $meta = [];
        foreach ($rowFields as $rf) {
            $meta[] = ['field' => $rf, 'label' => $rf, 'aggregation' => null, 'is_row' => true];
        }
        foreach ($distinctCols as $col) {
            $meta[] = ['field' => $col, 'label' => $col, 'aggregation' => null, 'is_col' => true];
        }
        return $meta;
    }
}
