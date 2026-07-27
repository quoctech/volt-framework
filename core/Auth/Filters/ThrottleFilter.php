<?php

declare(strict_types=1);

namespace Volt\Core\Auth\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class ThrottleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $throttler = service('throttler');

        $ip = $request->getIPAddress();
        if (! $throttler->check('login_ip_' . $ip, 20, 60)) {
            return $this->limitExceeded($throttler, $request);
        }

        $username = $this->extractUsername($request);
        if ($username !== null && $username !== '') {
            $key = 'login_user_' . md5(mb_strtolower($username));
            if (! $throttler->check($key, 5, 900)) {
                return $this->limitExceeded($throttler, $request);
            }
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    private function extractUsername(RequestInterface $request): ?string
    {
        $username = $request->getPost('name');
        if ($username !== null) {
            return mb_trim($username);
        }

        try {
            $json = $request->getJSON(true);
            if (is_array($json) && isset($json['name'])) {
                return mb_trim((string) $json['name']);
            }
        } catch (\CodeIgniter\HTTP\Exceptions\HTTPException) {
        }

        return null;
    }

    private function limitExceeded($throttler, RequestInterface $request)
    {
        $retryAfter = $throttler->getTokenTime();
        $response = Services::response()
            ->setStatusCode(429)
            ->setHeader('Retry-After', (string) $retryAfter);

        if ($this->wantsJson($request)) {
            return $response->setJSON([
                'status'  => 'error',
                'message' => 'Too many requests. Please try again in ' . $retryAfter . ' seconds.',
            ]);
        }

        return $response->setBody(view('auth/login', [
            'error' => 'Too many attempts. Please try again in ' . $retryAfter . ' seconds.',
        ]));
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
