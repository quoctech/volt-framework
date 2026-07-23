<?php

declare(strict_types=1);

namespace Volt\Core\System\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\Exceptions\PageForbiddenException;
use Psr\Log\LoggerInterface;
use Throwable;
use Volt\Core\System\Services\ErrorLogService;

final class ErrorLogController extends Controller
{
    private readonly ErrorLogService $errorLogService;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);
        helper(['url']);
        $this->errorLogService = service('voltErrorLog');
    }

    public function index(): string
    {
        $actor = service('voltAuth')->currentUser();
        $resolver = service('voltPermissionResolver');

        if ($actor === null || (! $actor->isAdmin() && ! $resolver->can('error_logs', 'read', null, null, $actor))) {
            throw PageForbiddenException::forPageForbidden();
        }

        try {
            $logs = $this->errorLogService->listLogs([
                'page' => $this->request->getGet('page'),
                'per_page' => $this->request->getGet('per_page'),
                'level' => $this->request->getGet('level'),
                'channel' => $this->request->getGet('channel'),
                'q' => $this->request->getGet('q'),
            ]);
            $channels = $this->errorLogService->listChannels();
        } catch (Throwable $throwable) {
            service('voltErrorLog')->logException($throwable, [], 'error_logs', 'error_logs_list_failed');
            $logs = [
                'rows' => [],
                'meta' => ['page' => 1, 'per_page' => 50, 'total' => 0, 'total_pages' => 1, 'per_page_options' => [20, 50, 100, 200]],
                'filters' => ['level' => '', 'channel' => '', 'q' => ''],
                'summary' => ['total' => 0, 'error' => 0, 'warning' => 0, 'info' => 0],
            ];
            $channels = [];
        }

        $content = view('Volt\\Core\\System\\Views\\error_logs', [
            'logs' => $logs,
            'channels' => $channels,
        ]);

        return view('Volt\\Core\\Metadata\\Views\\layouts\\desk', [
            'pageTitle' => 'Error Logs · Volt Desk',
            'currentUserName' => $actor?->name ?? '',
            'isAdmin' => $actor?->isAdmin() ?? false,
            'deskActive' => 'error-logs',
            'content' => $content,
        ]);
    }
}
