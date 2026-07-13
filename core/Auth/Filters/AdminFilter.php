<?php

declare(strict_types=1);

namespace Volt\Core\Auth\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Volt\Core\Auth\Entities\UserEntity;

/**
 * Requires an authenticated admin session.
 * HTML requests redirect/forbidden; API-style requests return JSON.
 */
class AdminFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = Services::voltAuth();
        $user = $auth->currentUser();

        if (! $user instanceof UserEntity) {
            return $this->deny($request, 401, 'Authentication required.');
        }

        if (! $user->isAdmin()) {
            return $this->deny($request, 403, 'Admin permission required.');
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
            ->setBody($this->forbiddenPage($message));
    }

    private function wantsJson(RequestInterface $request): bool
    {
        $uri = trim($request->getUri()->getPath(), '/');

        if (str_starts_with($uri, 'api/') || str_contains($uri, '/api/')) {
            return true;
        }

        $accept = strtolower($request->getHeaderLine('Accept'));
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        $xhr = strtolower($request->getHeaderLine('X-Requested-With'));

        return $xhr === 'xmlhttprequest';
    }

    private function forbiddenPage(string $message): string
    {
        $safeMessage = esc($message);
        $deskUrl = esc(site_url('desk'));

        return <<<HTML
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 Forbidden</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f4f4f5; color: #18181b; margin: 0; padding: 2rem; }
        main { max-width: 32rem; margin: 4rem auto; background: #fff; border: 1px solid #d4d4d8; padding: 1.5rem; }
        h1 { margin: 0 0 .5rem; font-size: 1.25rem; }
        p { margin: 0 0 1rem; color: #52525b; }
        a { color: #18181b; }
    </style>
</head>
<body>
    <main>
        <h1>403 Forbidden</h1>
        <p>{$safeMessage}</p>
        <p><a href="{$deskUrl}">Quay lại Desk</a></p>
    </main>
</body>
</html>
HTML;
    }
}
