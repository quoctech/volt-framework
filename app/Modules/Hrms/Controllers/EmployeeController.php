<?php

declare(strict_types=1);

namespace App\Modules\Hrms\Controllers;

use App\Modules\Hrms\Models\EmployeeModel;
use CodeIgniter\Controller;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Volt\Core\Database\VoltDatabase;

final class EmployeeController extends Controller
{
    private const PER_PAGE_OPTIONS = [50, 100, 200, 500, 1000, 2500];
    private const AUTONAME_PATTERN = 'Eoo-.YYYY.-.#####';

    /** @var array<int, array<string, mixed>> */
    private array $fields = [];
    /** @var array<int, array<string, mixed>> */
    private array $sessions = [];
    private EmployeeModel $model;
    private BaseConnection $db;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        helper(['url']);
        $this->model = new EmployeeModel();
        $this->db = VoltDatabase::connection();
        $this->fields = json_decode('[{"fieldname":"employee_name","label":"Tên Nhân Viên","fieldtype":"Data","options":"","default_value":"","placeholder":"","is_required":false,"read_only":false,"session_uid":"f99a39a2-08fd-43b9-bb16-9ffd0ad9636a","column":1,"custom_meta":{"column":1,"placeholder":"","session_uid":"f99a39a2-08fd-43b9-bb16-9ffd0ad9636a","in_list_view":true,"default_value":""}},{"fieldname":"employee_age","label":"Tuổi Nhân Viên","fieldtype":"Int","options":"","default_value":"","placeholder":"","is_required":false,"read_only":false,"session_uid":"f99a39a2-08fd-43b9-bb16-9ffd0ad9636a","column":2,"custom_meta":{"column":2,"session_uid":"f99a39a2-08fd-43b9-bb16-9ffd0ad9636a","in_list_view":true}},{"fieldname":"input_3","label":"Input 3","fieldtype":"Input","options":"","default_value":"","placeholder":"","is_required":false,"read_only":true,"session_uid":"f99a39a2-08fd-43b9-bb16-9ffd0ad9636a","column":1,"custom_meta":{"column":1,"placeholder":"","session_uid":"f99a39a2-08fd-43b9-bb16-9ffd0ad9636a","in_list_view":true,"default_value":""}},{"fieldname":"check_4","label":"Check 4","fieldtype":"Check","options":"","default_value":"","placeholder":"","is_required":true,"read_only":false,"session_uid":"f99a39a2-08fd-43b9-bb16-9ffd0ad9636a","column":1,"custom_meta":{"column":1,"session_uid":"f99a39a2-08fd-43b9-bb16-9ffd0ad9636a","in_list_view":true,"default_value":""}}]', true) ?: [];
        $this->sessions = json_decode('[{"uid":"f99a39a2-08fd-43b9-bb16-9ffd0ad9636a","title":"Primary","description":"Main fields","column_count":2}]', true) ?: [];
    }

    public function index(): string
    {
        return view('App\Modules\Hrms\Views\employee_list', [
            'title' => 'Employee List',
            'dataUrl' => site_url('hrms/api/employee'),
            'createUrl' => site_url('hrms/employee/create'),
            'editUrlBase' => site_url('hrms/employee/edit'),
            'builderUrl' => site_url('desk/entity-builder?entity=employee'),
        ]);
    }

    public function create(): string
    {
        return view('App\Modules\Hrms\Views\employee_form', [
            'title' => 'New Employee',
            'listUrl' => site_url('hrms/employee'),
            'saveUrl' => site_url('hrms/api/employee/save'),
            'loadUrlBase' => site_url('hrms/api/employee/load'),
            'fields' => $this->fields,
            'sessions' => $this->sessions,
            'recordName' => '',
        ]);
    }

    public function edit(string $name): string
    {
        return view('App\Modules\Hrms\Views\employee_form', [
            'title' => 'Edit Employee',
            'listUrl' => site_url('hrms/employee'),
            'saveUrl' => site_url('hrms/api/employee/save'),
            'loadUrlBase' => site_url('hrms/api/employee/load'),
            'fields' => $this->fields,
            'sessions' => $this->sessions,
            'recordName' => $name,
        ]);
    }

    public function data(): ResponseInterface
    {
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = (int) ($this->request->getGet('per_page') ?? 50);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 50;
        }

        $query = trim((string) ($this->request->getGet('q') ?? ''));
        $builder = $this->model->builder();

        if ($query !== '') {
            $builder->groupStart();
            $builder->like('name', $query);
            foreach ($this->fields as $field) {
                $fieldname = (string) ($field['fieldname'] ?? '');
                if ($fieldname === '' || $fieldname === 'name') {
                    continue;
                }

                $builder->orLike($fieldname, $query);
            }
            $builder->groupEnd();
        }

        $countBuilder = clone $builder;
        $total = (int) $countBuilder->countAllResults(false);
        $rows = $builder
            ->orderBy('modified', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        return $this->response->setJSON([
            'status' => 'ok',
            'rows' => $rows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => max(1, (int) ceil($total / $perPage)),
                'options' => self::PER_PAGE_OPTIONS,
            ],
        ]);
    }

    public function load(string $name): ResponseInterface
    {
        $row = $this->model->find($name);
        if (! is_array($row)) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Record not found.',
            ]);
        }

        return $this->response->setJSON([
            'status' => 'ok',
            'data' => $row,
        ]);
    }

    public function save(): ResponseInterface
    {
        $payload = $this->extractPayload();

        if (! is_array($payload)) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => 'Invalid payload.',
            ]);
        }

        $row = $this->normalizePayload($payload);
        $name = trim((string) ($row['name'] ?? ''));

        try {
            $exists = $name !== '' && is_array($this->model->find($name));
            if (! $exists && $name === '') {
                $name = $this->generateDocumentName();
                $row['name'] = $name;
            }

            $row = $this->applyReadOnlyFields($row, $exists ? $name : null);
            $this->assertRequiredFields($row, $exists ? $name : null);

            if ($exists) {
                $this->model->update($name, $row);
            } else {
                $this->model->insert($row);
            }

            return $this->response->setJSON([
                'status' => 'ok',
                'message' => $exists ? 'Record updated.' : 'Record created.',
                'data' => [
                    'name' => $name,
                ],
            ]);
        } catch (Throwable $throwable) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractPayload(): ?array
    {
        if ($this->request->is('json')) {
            $payload = $this->request->getJSON(true);
            return is_array($payload) ? $payload : null;
        }

        $payload = $this->request->getPost();

        return is_array($payload) ? $payload : null;
    }

    private function generateDocumentName(): string
    {
        $pattern = trim(self::AUTONAME_PATTERN);
        if ($pattern === '' || $pattern === 'HASH') {
            return bin2hex(random_bytes(16));
        }

        $resolved = strtr($pattern, [
            '.YYYY.' => gmdate('Y'),
            '.YY.' => gmdate('y'),
            '.MM.' => gmdate('m'),
            '.DD.' => gmdate('d'),
        ]);
        $resolved = preg_replace('/([\-\/])\.(#+)/', '$1$2', $resolved) ?? $resolved;

        if (! preg_match('/#+/', $resolved, $matches)) {
            return $resolved;
        }

        $token = $matches[0];
        $sequence = $this->nextSequenceValue(strtolower('employee:' . $resolved));
        $serial = str_pad((string) $sequence, strlen($token), '0', STR_PAD_LEFT);

        return preg_replace('/#+/', $serial, $resolved, 1) ?? $resolved;
    }

    private function nextSequenceValue(string $key): int
    {
        $this->db->transStart();

        $row = $this->db->table('sys_sequence')
            ->where('key', $key)
            ->get()
            ->getRowArray();

        $current = is_array($row) ? (int) ($row['current_value'] ?? 0) : 0;
        $next = $current + 1;

        if (is_array($row)) {
            $this->db->table('sys_sequence')
                ->where('key', $key)
                ->update(['current_value' => $next]);
        } else {
            $this->db->table('sys_sequence')->insert([
                'key' => $key,
                'current_value' => $next,
            ]);
        }

        $this->db->transComplete();

        return $next;
    }

    public function delete(string $name): ResponseInterface
    {
        try {
            $this->model->delete($name);

            return $this->response->setJSON([
                'status' => 'ok',
                'message' => 'Record deleted.',
            ]);
        } catch (Throwable $throwable) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        $row = [];
        foreach ($this->fields as $field) {
            $fieldname = (string) ($field['fieldname'] ?? '');
            if ($fieldname === '') {
                continue;
            }

            $fieldtype = (string) ($field['fieldtype'] ?? 'Input');
            $value = $payload[$fieldname] ?? null;

            if ($fieldtype === 'Check') {
                $row[$fieldname] = in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes'], true) ? 1 : 0;
                continue;
            }

            if (in_array($fieldtype, ['Int', 'Float'], true)) {
                $row[$fieldname] = $value === '' || $value === null ? null : $value;
                continue;
            }

            $row[$fieldname] = is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function applyReadOnlyFields(array $row, ?string $existingName = null): array
    {
        if ($existingName === null || $existingName === '') {
            return $row;
        }

        $existing = $this->model->find($existingName);
        if (! is_array($existing)) {
            return $row;
        }

        foreach ($this->fields as $field) {
            if ((bool) ($field['read_only'] ?? false) !== true) {
                continue;
            }

            $fieldname = (string) ($field['fieldname'] ?? '');
            if ($fieldname === '' || ! array_key_exists($fieldname, $existing)) {
                continue;
            }

            $row[$fieldname] = $existing[$fieldname];
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function assertRequiredFields(array $row, ?string $existingName = null): void
    {
        $existing = null;
        if ($existingName !== null && $existingName !== '') {
            $existingRecord = $this->model->find($existingName);
            $existing = is_array($existingRecord) ? $existingRecord : null;
        }

        foreach ($this->fields as $field) {
            if ((bool) ($field['is_required'] ?? false) !== true) {
                continue;
            }

            $fieldname = (string) ($field['fieldname'] ?? '');
            if ($fieldname === '') {
                continue;
            }

            $value = $row[$fieldname] ?? ($existing[$fieldname] ?? null);
            if (! $this->hasFieldValue($field, $value)) {
                $label = (string) ($field['label'] ?? $fieldname);
                throw new \InvalidArgumentException($label . ' is required.');
            }
        }
    }

    private function hasFieldValue(array $field, mixed $value): bool
    {
        if ((string) ($field['fieldtype'] ?? '') === 'Check') {
            return $value !== null;
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return trim((string) ($value ?? '')) !== '';
    }
}