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
        $db = VoltDatabase::connection();

        try {
            $db->query('SELECT 1');
            $healthy = true;
        } catch (\Throwable) {
            $healthy = false;
        }

        $statusCode = $healthy ? 200 : 503;

        return $this->response
            ->setStatusCode($statusCode)
            ->setContentType('application/json')
            ->setBody(json_encode([
                'status' => $healthy ? 'ok' : 'degraded',
                'database' => $healthy ? 'connected' : 'unreachable',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
