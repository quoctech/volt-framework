<?php

declare(strict_types=1);

namespace Volt\Core\Security\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Config\Volt;

/**
 * Rate limiting toàn cục theo IP cho mọi endpoint (trừ health/ping).
 *
 * - Dùng CI4 Throttler (backed by Redis cache config).
 * - Giới hạn mặc định: 300 request / 60s / IP, cấu hình qua app/Config/Volt.php
 *   (`rateLimitGlobalAttempts`, `rateLimitGlobalWindowSeconds`).
 * - Phản hồi HTTP 429 kèm Retry-After; JSON-aware.
 */
final class RateLimitFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $volt = config(Volt::class);

        $attempts = max(1, (int) ($volt->rateLimitGlobalAttempts ?? 300));
        $window   = max(1, (int) ($volt->rateLimitGlobalWindowSeconds ?? 60));

        $throttler = Services::throttler();
        $ip = $request->getIPAddress();
        $key = 'volt_rl_' . ($ip !== '' ? $ip : 'unknown') . '_' . $request->getMethod();

        if (! $throttler->check($key, $attempts, $window)) {
            return $this->limitExceeded($throttler, $request);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    private function limitExceeded($throttler, RequestInterface $request): ResponseInterface
    {
        $retryAfter = max(1, (int) $throttler->getTokenTime());
        $response = Services::response()
            ->setStatusCode(429)
            ->setHeader('Retry-After', (string) $retryAfter);

        $body = [
            'status'  => 'error',
            'message' => 'Too many requests. Please try again in ' . $retryAfter . ' seconds.',
        ];

        if ($this->wantsJson($request)) {
            return $response->setJSON($body);
        }

        return $response->setBody(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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
