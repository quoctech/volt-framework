<?php

declare(strict_types=1);

namespace App\Modules\Hrms\Controllers;

use App\Modules\Hrms\Models\EmployeeModel;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final class EmployeeController extends Controller
{
    private const PER_PAGE_OPTIONS = [50, 100, 200, 500, 1000, 2500];

    /** @var array<int, array<string, mixed>> */
    private array $fields = [];
    private EmployeeModel $model;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        helper(['url']);
        $this->model = new EmployeeModel();
        $this->fields = json_decode('[{"fieldname":"name","label":"Name","fieldtype":"Input","options":"","default_value":"","placeholder":"","is_required":true},{"fieldname":"employee_name","label":"Tên Nhân Viên","fieldtype":"Data","options":"","default_value":"","placeholder":"","is_required":false},{"fieldname":"employee_age","label":"Tuổi Nhân Viên","fieldtype":"Int","options":"","default_value":"","placeholder":"","is_required":false}]', true) ?: [];
    }

    public function index(): string
    {
        return view('App\Modules\Hrms\Views\employee_list', [
            'title' => 'Employee List',
            'dataUrl' => site_url('hrms/api/employee'),
            'createUrl' => site_url('hrms/employee/create'),
            'editUrlBase' => site_url('hrms/employee/edit'),
            'builderUrl' => site_url('desk/entity-builder?entity=employee'),
            'csrfTokenName' => csrf_token(),
            'csrfHash' => csrf_hash(),
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
            'recordName' => '',
            'csrfTokenName' => csrf_token(),
            'csrfHash' => csrf_hash(),
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
            'recordName' => $name,
            'csrfTokenName' => csrf_token(),
            'csrfHash' => csrf_hash(),
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
        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            $payload = $this->request->getPost();
        }

        if (! is_array($payload)) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => 'Invalid payload.',
            ]);
        }

        $row = $this->normalizePayload($payload);
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => 'Name is required.',
            ]);
        }

        try {
            $exists = is_array($this->model->find($name));
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
}
