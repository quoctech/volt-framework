<?php

declare(strict_types=1);

namespace Volt\Core\System\Controllers;

use CodeIgniter\Controller;
use Volt\Core\System\Services\SystemStatusService;

class SystemStatusController extends Controller
{
    private readonly SystemStatusService $systemStatusService;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);
        helper(['url']);
        $this->systemStatusService = service('voltSystemStatus');
    }

    public function index(): string
    {
        $actor = service('voltAuth')->currentUser();
        $report = $this->systemStatusService->getStatusReport();

        $content = view('Volt\\Core\\System\\Views\\system_status', [
            'report' => $report,
        ]);

        return view('Volt\\Core\\Metadata\\Views\\layouts\\desk', [
            'pageTitle'       => 'System Status · Volt Desk',
            'currentUserName' => $actor?->name ?? '',
            'isAdmin'         => $actor?->isAdmin() ?? false,
            'deskActive'      => 'system-status',
            'content'         => $content,
        ]);
    }
}
