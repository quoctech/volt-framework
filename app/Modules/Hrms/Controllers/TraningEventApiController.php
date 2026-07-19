<?php

declare(strict_types=1);

namespace App\Modules\Hrms\Controllers;

use App\Modules\Hrms\Models\TraningEventModel;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final class TraningEventApiController extends BaseApiController
{
    private TraningEventModel $model;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->model = new TraningEventModel();
    }

    public function index(): ResponseInterface
    {
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->getGet('per_page') ?? 50)));
        $query = trim((string) ($this->request->getGet('q') ?? ''));

        $builder = $this->model->builder();

        if ($query !== '') {
            $pk = $this->model->primaryKey;
            $builder->groupStart();
            $builder->like($pk, $query);
            foreach ($this->model->allowedFields as $field) {
                if ($field === $pk) {
                    continue;
                }
                $builder->orLike($field, $query);
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
            'data' => $rows,
            'meta' => [
                'page'       => $page,
                'per_page'   => $perPage,
                'total'      => $total,
                'total_pages' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    public function show(string $id): ResponseInterface
    {
        $row = $this->model->find($id);
        if (! is_array($row)) {
            return $this->respondNotFound();
        }

        return $this->respondSuccess($row);
    }

    public function store(): ResponseInterface
    {
        $payload = $this->extractPayload();
        if (! is_array($payload)) {
            return $this->respondError('Invalid JSON payload.', 400);
        }

        $allowedFields = $this->model->allowedFields;
        if ($allowedFields !== []) {
            $payload = $this->filterAllowedFields($payload, $allowedFields);
        }

        try {
            $id = $this->model->insert($payload);
            if ($id === false) {
                $errors = $this->model->errors();
                if (! empty($errors)) {
                    return $this->respondValidationError($errors);
                }

                return $this->respondError('Unable to create record.', 422);
            }

            $record = $this->model->find($id);

            return $this->respondSuccess($record, 201);
        } catch (Throwable $throwable) {
            return $this->respondError($throwable->getMessage(), 422);
        }
    }

    public function update(string $id): ResponseInterface
    {
        $existing = $this->model->find($id);
        if (! is_array($existing)) {
            return $this->respondNotFound();
        }

        $payload = $this->extractPayload();
        if (! is_array($payload)) {
            return $this->respondError('Invalid JSON payload.', 400);
        }

        $allowedFields = $this->model->allowedFields;
        if ($allowedFields !== []) {
            $payload = $this->filterAllowedFields($payload, $allowedFields);
        }
        unset($payload[$this->model->primaryKey]);

        try {
            if (! $this->model->update($id, $payload)) {
                $errors = $this->model->errors();
                if (! empty($errors)) {
                    return $this->respondValidationError($errors);
                }

                return $this->respondError('Unable to update record.', 422);
            }

            $record = $this->model->find($id);

            return $this->respondSuccess($record);
        } catch (Throwable $throwable) {
            return $this->respondError($throwable->getMessage(), 422);
        }
    }

    public function destroy(string $id): ResponseInterface
    {
        $existing = $this->model->find($id);
        if (! is_array($existing)) {
            return $this->respondNotFound();
        }

        try {
            $this->model->delete($id);

            return $this->response->setStatusCode(200)->setJSON([
                'status' => 'ok',
            ]);
        } catch (Throwable $throwable) {
            return $this->respondError($throwable->getMessage(), 422);
        }
    }
}