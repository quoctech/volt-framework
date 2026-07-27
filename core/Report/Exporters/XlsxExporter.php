<?php

declare(strict_types=1);

namespace Volt\Core\Report\Exporters;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class XlsxExporter
{
    public function export(array $columns, array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = array_map(static fn (array $col): string => $col['label'] ?? $col['field'] ?? '', $columns);
        foreach ($headers as $colIdx => $header) {
            $sheet->setCellValueByColumnAndRow($colIdx + 1, 1, $header);
            $sheet->getStyleByColumnAndRow($colIdx + 1, 1)->getFont()->setBold(true);
        }

        foreach ($rows as $rowIdx => $row) {
            foreach ($columns as $colIdx => $col) {
                $key = $col['label'] ?? $col['field'] ?? '';
                $sheet->setCellValueByColumnAndRow($colIdx + 1, $rowIdx + 2, $row[$key] ?? '');
            }
        }

        foreach (range(1, count($headers)) as $colIdx) {
            $sheet->getColumnDimensionByColumn($colIdx)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');

        return ob_get_clean();
    }
}
