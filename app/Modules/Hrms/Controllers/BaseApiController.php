<?php

declare(strict_types=1);

namespace App\Modules\Hrms\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

abstract class BaseApiController extends Controller
{
    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
    }

    protected function respondSuccess(mixed $data, int $code = 200): ResponseInterface
    {
        return $this->response->setStatusCode($code)->setJSON([
            'data' => $data,
        ]);
    }

    protected function respondError(string $message, int $code = 400): ResponseInterface
    {
        return $this->response->setStatusCode($code)->setJSON([
            'status' => 'error',
            'message' => $message,
        ]);
    }

    protected function respondNotFound(string $message = 'Record not found.'): ResponseInterface
    {
        return $this->respondError($message, 404);
    }

    protected function respondValidationError(array $errors): ResponseInterface
    {
        return $this->response->setStatusCode(422)->setJSON([
            'status' => 'error',
            'message' => 'Validation failed.',
            'errors' => $errors,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function extractPayload(): ?array
    {
        if ($this->request->is('json')) {
            try {
                $payload = $this->request->getJSON(true);
                return is_array($payload) ? $payload : null;
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $allowedFields
     * @return array<string, mixed>
     */
    protected function filterAllowedFields(array $payload, array $allowedFields): array
    {
        return array_intersect_key($payload, array_flip($allowedFields));
    }
}