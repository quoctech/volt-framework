<?php

declare(strict_types=1);

namespace Volt\Core\Report\Services;

use CodeIgniter\Events\Events;
use CodeIgniter\Validation\ValidationInterface;
use InvalidArgumentException;
use Volt\Core\AwesomeBar\Models\AwesomeBarModel;
use Volt\Core\Database\VoltDatabase;
use Volt\Core\Report\Models\ReportModel;

class ReportService
{
    private readonly ReportModel $reportModel;
    private readonly ReportQueryBuilder $queryBuilder;
    private readonly PivotEngine $pivotEngine;
    private readonly ValidationInterface $validation;

    public function __construct(
        ?ReportModel $reportModel = null,
        ?ReportQueryBuilder $queryBuilder = null,
        ?PivotEngine $pivotEngine = null,
        ?ValidationInterface $validation = null,
    ) {
        $this->reportModel = $reportModel ?? new ReportModel();
        $this->queryBuilder = $queryBuilder ?? new ReportQueryBuilder();
        $this->pivotEngine = $pivotEngine ?? new PivotEngine();
        $this->validation = $validation ?? service('validation');
    }

    public function getAll(): array
    {
        return $this->reportModel->getAll();
    }

    public function getByName(string $name): ?array
    {
        return $this->reportModel->getByName($name);
    }

    public function getModules(): array
    {
        $modulesDir = APPPATH . 'Modules';
        $modules = [];

        if (! is_dir($modulesDir)) {
            return $modules;
        }

        $items = scandir($modulesDir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (! is_dir($modulesDir . '/' . $item)) {
                continue;
            }
            $modules[] = ['name' => $item, 'label' => $item];
        }

        usort($modules, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return $modules;
    }

    public function getRoles(): array
    {
        $db = VoltDatabase::connection();

        return $db->table('sys_role')
            ->select('name, label')
            ->orderBy('label', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function getEntities(): array
    {
        $db = VoltDatabase::connection();

        $entities = $db->table('sys_entity')
            ->select('name')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        return array_map(function (array $entity) use ($db): array {
            $entityName = (string) ($entity['name'] ?? '');
            $fields = $db->table('sys_entity_field')
                ->select('fieldname, label, fieldtype, options')
                ->where('parent', $entityName)
                ->orderBy('idx', 'ASC')
                ->orderBy('fieldname', 'ASC')
                ->get()
                ->getResultArray();

            return [
                'name'   => $entityName,
                'label'  => $this->titleize($entityName),
                'fields' => array_merge(
                    $this->systemFields(),
                    array_map(static fn (array $f): array => [
                        'fieldname' => (string) ($f['fieldname'] ?? ''),
                        'label'     => (string) ($f['label'] ?? ''),
                        'fieldtype' => (string) ($f['fieldtype'] ?? 'Data'),
                        'options'   => (string) ($f['options'] ?? ''),
                    ], $fields)
                ),
            ];
        }, $entities);
    }

    public function getEntityFields(string $entityName): array
    {
        return $this->queryBuilder->getEntityFields($entityName);
    }

    public function suggestJoins(array $entities): array
    {
        return $this->queryBuilder->suggestJoins($entities);
    }

    public function save(array $data): array
    {
        $name = $this->normalizeName((string) ($data['name'] ?? ''));
        $label = mb_trim((string) ($data['label'] ?? ''));
        $module = mb_trim((string) ($data['module'] ?? ''));
        $reportType = mb_trim((string) ($data['report_type'] ?? 'query'));
        $description = mb_trim((string) ($data['description'] ?? ''));

        $this->validation->setRules([
            'name'        => 'required|min_length[3]|max_length[140]',
            'module'      => 'required|max_length[50]',
            'report_type' => 'required|in_list[query,pivot,sql]',
        ]);

        $input = [
            'name'        => $name,
            'module'      => $module,
            'report_type' => $reportType,
        ];

        if (! $this->validation->run($input)) {
            throw new InvalidArgumentException(implode(' ', $this->validation->getErrors()));
        }

        if ($label === '') {
            $label = $this->titleize($name);
        }

        $queryJson = $data['query'] ?? [];
        if (is_string($queryJson)) {
            $queryJson = json_decode($queryJson, true) ?? [];
        }

        $columnsJson = $data['columns'] ?? [];
        if (is_string($columnsJson)) {
            $columnsJson = json_decode($columnsJson, true) ?? [];
        }

        $chartsJson = $data['charts'] ?? [];
        if (is_string($chartsJson)) {
            $chartsJson = json_decode($chartsJson, true) ?? [];
        }

        $this->validateQuery($reportType, $queryJson);

        $payload = [
            'name'        => $name,
            'module'      => $module,
            'label'       => $label,
            'description' => $description,
            'report_type' => $reportType,
            'query'       => json_encode($queryJson, JSON_UNESCAPED_UNICODE),
            'columns'     => json_encode($columnsJson, JSON_UNESCAPED_UNICODE),
            'charts'      => json_encode($chartsJson, JSON_UNESCAPED_UNICODE),
            'roles'       => json_encode($data['roles'] ?? [], JSON_UNESCAPED_UNICODE),
            'is_active'   => (int) ($data['is_active'] ?? 1),
        ];

        $this->reportModel->upsert($payload);

        $actor = service('voltAuth')->currentUser();
        Events::trigger('report_saved', $name, $label, $module, $actor?->name ?? 'system');
        (new AwesomeBarModel())->registerEntity(
            'report_' . $name,
            $label . ' (Report)',
            $module,
            $actor?->name ?? 'system'
        );

        return $this->reportModel->getByName($name) ?? $payload;
    }

    public function delete(string $name): void
    {
        $report = $this->reportModel->getByName($name);

        if ($report === null) {
            return;
        }

        $this->reportModel->delete($name);
        Events::trigger('report_deleted', $name);
        (new AwesomeBarModel())->removeEntity('report_' . $name);
    }

    public function run(string $name, array $params = []): array
    {
        $report = $this->reportModel->getByName($name);

        if ($report === null) {
            throw new InvalidArgumentException("Report '{$name}' not found.");
        }

        $query = json_decode($report['query'] ?? '{}', true);
        if (! is_array($query)) {
            $query = [];
        }

        $query['type'] = $report['report_type'];

        if (! empty($params['filters']) && is_array($params['filters'])) {
            $query['filters'] = $params['filters'];
        }

        $limit = (int) ($params['limit'] ?? 0);
        if ($limit > 0) {
            $query['limit'] = $limit;
        }

        if ($report['report_type'] === 'pivot') {
            return $this->pivotEngine->build($query);
        }

        return $this->queryBuilder->build($query);
    }

    public function runQuery(array $query, array $params = []): array
    {
        if (! empty($params['filters']) && is_array($params['filters'])) {
            $query['filters'] = $params['filters'];
        }
        if (! empty($params['limit'])) {
            $query['limit'] = (int) $params['limit'];
        }

        $type = $query['type'] ?? 'query';

        if ($type === 'pivot') {
            return $this->pivotEngine->build($query);
        }

        return $this->queryBuilder->build($query);
    }

    public function export(string $name, string $format, array $params = []): array
    {
        $result = $this->run($name, $params);

        $format = mb_strtolower(mb_trim($format));

        if ($format === 'csv') {
            return [
                'data'         => $this->toCsv($result['columns'], $result['rows']),
                'content_type' => 'text/csv',
                'extension'    => 'csv',
            ];
        }

        throw new InvalidArgumentException("Unsupported export format: {$format}");
    }

    private function toCsv(array $columns, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        $headers = array_map(static fn (array $col): string => $col['label'] ?? $col['field'] ?? '', $columns);
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $col) {
                $key = $col['label'] ?? $col['field'] ?? '';
                $line[] = $row[$key] ?? '';
            }
            fputcsv($handle, $line);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    private function validateQuery(string $reportType, array $query): void
    {
        if ($reportType === 'query') {
            $entities = $query['entities'] ?? [];
            if ($entities === [] || ! isset($entities[0]['entity'])) {
                throw new InvalidArgumentException('At least one entity is required for query reports.');
            }
        }

        if ($reportType === 'pivot') {
            $entities = $query['entities'] ?? [];
            if ($entities === [] || ! isset($entities[0]['entity'])) {
                throw new InvalidArgumentException('At least one entity is required for pivot reports.');
            }
        }

        if ($reportType === 'sql') {
            $sql = mb_trim($query['sql'] ?? '');
            if ($sql === '') {
                throw new InvalidArgumentException('SQL query cannot be empty.');
            }
        }
    }

    private function normalizeName(string $name): string
    {
        $name = mb_strtolower(mb_trim($name));
        $name = preg_replace('/[^a-z0-9_]+/', '_', $name) ?? '';
        $name = preg_replace('/_+/', '_', $name) ?? '';
        $name = mb_trim($name, '_');

        return $name;
    }

    private function systemFields(): array
    {
        return [
            ['fieldname' => 'name', 'label' => 'ID', 'fieldtype' => 'Data', 'options' => ''],
            ['fieldname' => 'owner', 'label' => 'Owner', 'fieldtype' => 'Data', 'options' => ''],
            ['fieldname' => 'creation', 'label' => 'Created On', 'fieldtype' => 'Datetime', 'options' => ''],
            ['fieldname' => 'modified', 'label' => 'Modified', 'fieldtype' => 'Datetime', 'options' => ''],
            ['fieldname' => 'docstatus', 'label' => 'Doc Status', 'fieldtype' => 'Int', 'options' => ''],
        ];
    }

    private function titleize(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }
}
