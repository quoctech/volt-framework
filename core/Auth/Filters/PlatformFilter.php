<?php

declare(strict_types=1);

namespace Volt\Core\Auth\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Volt\Core\Auth\Entities\UserEntity;

/**
 * Yêu cầu user có quyền nền tảng (platform_developer hoặc admin)
 * để chỉnh sửa các cấu hình hệ thống như Custom Pages JS.
 */
class PlatformFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = Services::voltAuth();
        $user = $auth->currentUser();

        if (! $user instanceof UserEntity) {
            return $this->deny($request, 401, 'Authentication required.');
        }

        if (! $user->isPlatformDeveloper()) {
            return $this->deny($request, 403, 'Platform developer permission required.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    private function deny(RequestInterface $request, int $status, string $message)
    {
        if ($this->wantsJson($request)) {
            return Services::response()
                ->setStatusCode($status)
                ->setJSON([
                    'status'  => 'error',
                    'message' => $message,
                ]);
        }

        if ($status === 401) {
            return redirect()->to(site_url('login'));
        }

        return Services::response()
            ->setStatusCode(403)
            ->setJSON([
                'status'  => 'error',
                'message' => $message,
            ]);
    }

    private function wantsJson(RequestInterface $request): bool
    {
        $uri = mb_trim($request->getUri()->getPath(), '/');

        if (str_starts_with($uri, 'api/') || str_contains($uri, '/api/')) {
            return true;
        }

        $accept = strtolower($request->getHeaderLine('Accept'));
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        return strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
    }
}
