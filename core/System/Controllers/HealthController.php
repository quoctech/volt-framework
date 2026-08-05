<?php

declare(strict_types=1);

namespace Volt\Core\System\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use Volt\Core\Database\VoltDatabase;

final class HealthController extends Controller
{
    public function index(): ResponseInterface
    {
        return $this->report(false);
    }

    /**
     * Chi tiết các dependency (PG + Redis + ổ đĩa). Dùng cho monitoring nội bộ.
     */
    public function detail(): ResponseInterface
    {
        return $this->report(true);
    }

    private function report(bool $includeDetail): ResponseInterface
    {
        $dbHealthy = $this->checkDatabase();
        $redisHealthy = $this->checkRedis();
        $diskHealthy = $this->checkDisk();

        $healthy = $dbHealthy && $redisHealthy && $diskHealthy;

        $payload = [
            'status'   => $healthy ? 'ok' : 'degraded',
            'checks'   => [
                'database' => $dbHealthy ? 'connected' : 'unreachable',
                'cache'    => $redisHealthy ? 'connected' : 'unreachable',
                'disk'     => $diskHealthy ? 'writable' : 'unwritable',
            ],
            'timestamp' => gmdate('c'),
        ];

        if ($includeDetail) {
            $payload['dependencies'] = [
                'php'        => PHP_VERSION,
                'environment' => ENVIRONMENT,
            ];
        }

        return $this->response
            ->setStatusCode($healthy ? 200 : 503)
            ->setJSON($payload, false);
    }

    private function checkDatabase(): bool
    {
        try {
            VoltDatabase::connection()->query('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkRedis(): bool
    {
        try {
            $cache = service('cache');
            $key = '__health_' . bin2hex(random_bytes(4));
            $cache->save($key, '1', 5);
            $ok = $cache->get($key) === '1';
            $cache->delete($key);

            return $ok;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkDisk(): bool
    {
        $path = WRITEPATH . 'health';
        if (! is_dir($path) && ! @mkdir($path, 0775, true) && ! is_dir($path)) {
            return false;
        }

        $probe = $path . '/.probe';

        return @file_put_contents($probe, (string) time()) !== false
            && @unlink($probe);
    }
}
