<?php

declare(strict_types=1);

namespace Volt\Core\Report\Exporters;

class CsvExporter
{
    public function export(array $columns, array $rows, string $delimiter = ','): string
    {
        $handle = fopen('php://temp', 'r+');

        $headers = array_map(static fn (array $col): string => $col['label'] ?? $col['field'] ?? '', $columns);
        fputcsv($handle, $headers, $delimiter);

        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $col) {
                $field = $col['field'] ?? '';
                $line[] = $row[$field] ?? '';
            }
            fputcsv($handle, $line, $delimiter);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
