<?php

declare(strict_types=1);

namespace Volt\Core\Report\Controllers;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Controller;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use Volt\Core\Audit\AuditTrailWriter;
use Volt\Core\Report\Services\ReportService;

class ReportController extends Controller
{
    use ResponseTrait;

    private readonly ReportService $reportService;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);
        helper(['url']);
        $this->reportService = service('voltReport');
    }

    public function index(): string
    {
        $actor = service('voltAuth')->currentUser();
        $reports = $this->reportService->getAll();

        $content = view('Volt\\Core\\Report\\Views\\reports\\report_list', [
            'reports' => $reports,
        ]);

        return view('Volt\\Core\\Metadata\\Views\\layouts\\desk', [
            'pageTitle'       => 'Reports · Volt Desk',
            'currentUserName' => $actor?->name ?? '',
            'isAdmin'         => $actor?->isAdmin() ?? false,
            'deskActive'      => 'reports',
            'content'         => $content,
        ]);
    }

    public function create(): string
    {
        return $this->renderForm();
    }

    public function edit(string $name): string
    {
        $report = $this->reportService->getByName($name);

        if ($report === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->renderForm($report);
    }

    public function save(): ResponseInterface
    {
        $data = $this->request->getJSON(true);

        if (! is_array($data)) {
            return $this->fail('Invalid request body.', 400);
        }

        try {
            $report = $this->reportService->save($data);

            return $this->respond([
                'success' => true,
                'report'  => $report,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 400);
        }
    }

    public function delete(string $name): ResponseInterface
    {
        $this->reportService->delete($name);

        return $this->respond(['success' => true]);
    }

    public function run(string $name): ResponseInterface
    {
        $params = $this->request->getJSON(true) ?? [];

        try {
            if ($name === '_test') {
                $query = $params['query'] ?? [];
                if ($query === []) {
                    return $this->fail('Query payload is required for test run.', 400);
                }
                $result = $this->reportService->runQuery($query, $params);
            } else {
                $result = $this->reportService->run($name, $params);
            }

            return $this->respond([
                'success' => true,
                'data'    => $result,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 400);
        }
    }

    public function export(string $name, string $format): ResponseInterface
    {
        $params = $this->request->getJSON(true) ?? [];

        try {
            $export = $this->reportService->export($name, $format, $params);

            service('voltAuditTrailWriter')->write(
                AuditTrailWriter::CAT_EXPORT,
                'report:export',
                'report',
                $name,
                [],
                [
                    'format' => strtolower($format),
                    'row_count' => max(0, substr_count($export['data'], "\n") - 1),
                ],
                service('voltAuth')->currentUser()?->name ?? 'system',
                ['operation' => 'export'],
            );

            return $this->response
                ->setContentType($export['content_type'])
                ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '.' . $export['extension'] . '"')
                ->setBody($export['data']);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 400);
        }
    }

    public function entities(): ResponseInterface
    {
        return $this->respond([
            'success'  => true,
            'entities' => $this->reportService->getEntities(),
        ]);
    }

    public function entityFields(string $entityName): ResponseInterface
    {
        return $this->respond([
            'success' => true,
            'fields'  => $this->reportService->getEntityFields($entityName),
        ]);
    }

    public function suggestJoins(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        $entities = $data['entities'] ?? [];

        return $this->respond([
            'success'     => true,
            'suggestions' => $this->reportService->suggestJoins($entities),
        ]);
    }

    public function dashboard(): string
    {
        $actor = service('voltAuth')->currentUser();
        $reports = $this->reportService->getAll();

        $content = view('Volt\\Core\\Report\\Views\\dashboard', [
            'reports' => $reports,
        ]);

        return view('Volt\\Core\\Metadata\\Views\\layouts\\desk', [
            'pageTitle'       => 'Dashboard · Volt Desk',
            'currentUserName' => $actor?->name ?? '',
            'isAdmin'         => $actor?->isAdmin() ?? false,
            'deskActive'      => 'dashboard',
            'content'         => $content,
            'extraStyles'     => '',
            'extraScripts'    => '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>',
        ]);
    }

    private function renderForm(?array $report = null): string
    {
        $actor = service('voltAuth')->currentUser();
        $modules = $this->reportService->getModules();
        $roles = $this->reportService->getRoles();
        $entities = $this->reportService->getEntities();

        if ($report !== null) {
            if (is_string($report['query'] ?? null)) {
                $report['query'] = json_decode($report['query'], true);
            }
            if (is_string($report['columns'] ?? null)) {
                $report['columns'] = json_decode($report['columns'], true);
            }
            if (is_string($report['charts'] ?? null)) {
                $report['charts'] = json_decode($report['charts'], true);
            }
        }

        $content = view('Volt\\Core\\Report\\Views\\reports\\report_form', [
            'report'   => $report,
            'modules'  => $modules,
            'roles'    => $roles,
            'entities' => $entities,
        ]);

        $title = $report !== null ? 'Edit Report · Volt Desk' : 'Create Report · Volt Desk';

        return view('Volt\\Core\\Metadata\\Views\\layouts\\desk', [
            'pageTitle'       => $title,
            'currentUserName' => $actor?->name ?? '',
            'isAdmin'         => $actor?->isAdmin() ?? false,
            'deskActive'      => 'reports',
            'content'         => $content,
        ]);
    }
}
