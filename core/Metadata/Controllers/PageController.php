<?php

declare(strict_types=1);

namespace Volt\Core\Metadata\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use Volt\Core\Metadata\Services\PageService;

class PageController extends Controller
{
    private readonly PageService $pageService;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);
        helper(['url']);
        $this->pageService = service('voltPage');
    }

    public function index(): string
    {
        $actor = service('voltAuth')->currentUser();
        $pages = $this->pageService->getAll();

        $content = view('Volt\\Core\\Metadata\\Views\\pages\\page_list', [
            'pages' => $pages,
        ]);

        return view('Volt\\Core\\Metadata\\Views\\layouts\\desk', [
            'pageTitle'       => 'Pages · Volt Desk',
            'currentUserName' => $actor?->name ?? '',
            'isAdmin'         => $actor?->isAdmin() ?? false,
            'deskActive'      => 'pages',
            'content'         => $content,
        ]);
    }

    public function create(): string
    {
        return $this->renderForm();
    }

    public function edit(string $name): string
    {
        $page = $this->pageService->getByName($name);

        if ($page === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->renderForm($page);
    }

    public function save(): ResponseInterface
    {
        $this->validateRequest();

        $data = $this->request->getJSON(true);

        if (! is_array($data)) {
            return $this->fail('Invalid request body.');
        }

        try {
            $data['original_name'] = $data['original_name'] ?? null;
            $page = $this->pageService->save($data);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage());
        }

        return $this->response
            ->setContentType('application/json')
            ->setBody(json_encode([
                'success' => true,
                'page'    => $page,
            ]));
    }

    public function delete(string $name): ResponseInterface
    {
        $this->pageService->delete($name);

        return $this->response
            ->setContentType('application/json')
            ->setBody(json_encode(['success' => true]));
    }

    public function serve(string $route): ResponseInterface|string
    {
        $page = $this->pageService->getByRoute($route);

        if ($page === null || ! (int) ($page['is_active'] ?? 0)) {
            throw PageNotFoundException::forPageNotFound();
        }

        $actor = service('voltAuth')->currentUser();

        if ($actor === null) {
            return redirect()->to('/login');
        }

        $allowedRoles = json_decode($page['roles'] ?? '[]', true);

        if ($allowedRoles !== [] && ! $this->userHasAnyRole($actor, $allowedRoles)) {
            return redirect()->back()->with('error', 'You do not have access to this page.');
        }

        $cssContent = $page['css_content'] ?? '';
        $jsContent = $page['js_content'] ?? '';

        return view('Volt\\Core\\Metadata\\Views\\layouts\\desk', [
            'pageTitle'       => ($page['label'] ?? 'Page') . ' · Volt Desk',
            'currentUserName' => $actor?->name ?? '',
            'isAdmin'         => $actor?->isAdmin() ?? false,
            'deskActive'      => '',
            'content'         => $page['html_content'] ?? '',
            'extraStyles'     => $cssContent !== '' ? $cssContent : '',
            'extraScripts'    => $jsContent !== '' ? '<script>' . $jsContent . '</script>' : '',
        ]);
    }

    private function renderForm(?array $page = null): string
    {
        $actor = service('voltAuth')->currentUser();
        $modules = $this->pageService->getModules();
        $roles = $this->pageService->getRoles();

        $content = view('Volt\\Core\\Metadata\\Views\\pages\\page_form', [
            'page'    => $page,
            'modules' => $modules,
            'roles'   => $roles,
        ]);

        $title = $page !== null ? 'Edit Page · Volt Desk' : 'Create Page · Volt Desk';

        return view('Volt\\Core\\Metadata\\Views\\layouts\\desk', [
            'pageTitle'       => $title,
            'currentUserName' => $actor?->name ?? '',
            'isAdmin'         => $actor?->isAdmin() ?? false,
            'deskActive'      => 'pages',
            'content'         => $content,
        ]);
    }

    private function validateRequest(): void
    {
        if ($this->request->getMethod() !== 'post') {
            $this->fail('Method not allowed.', 405);
        }
    }

    private function fail(string $message, int $code = 400): ResponseInterface
    {
        return $this->response
            ->setStatusCode($code)
            ->setContentType('application/json')
            ->setBody(json_encode(['success' => false, 'error' => $message]));
    }

    private function userHasAnyRole(object $user, array $roles): bool
    {
        if ($roles === []) {
            return true;
        }

        if ($user->isAdmin()) {
            return true;
        }

        foreach ($roles as $role) {
            if (method_exists($user, 'hasRole') && $user->hasRole($role)) {
                return true;
            }
        }

        return false;
    }
}
