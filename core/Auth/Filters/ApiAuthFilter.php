<?php

declare(strict_types=1);

namespace Volt\Core\Auth\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class ApiAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $authService = Services::voltAuth();
        $token = $authService->extractBearerToken($request);

        $user = $authService->authenticateApiKeySecret($token)
            ?? $authService->authenticateApiToken($token);

        if ($user !== null) {
            return null;
        }

        return Services::response()
            ->setStatusCode(401)
            ->setJSON([
                'status'  => 'error',
                'message' => 'Unauthorized',
            ]);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
