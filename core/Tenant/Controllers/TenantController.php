<?php

declare(strict_types=1);

namespace Volt\Core\Tenant\Controllers;

use CodeIgniter\Controller;
use Volt\Core\Tenant\Services\TenantService;

class TenantController extends Controller
{
    private readonly TenantService $tenantService;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);
        helper(['form', 'url']);
        $this->tenantService = new TenantService();
    }

    public function index()
    {
        $tenants = $this->tenantService->getAll();

        return $this->renderView('Volt\\Core\\Tenant\\Views\\tenant_list', [
            'pageTitle'  => 'Tenant List · Volt Desk',
            'deskActive' => 'tenants',
            'tenants'    => $tenants,
        ]);
    }

    public function create()
    {
        return $this->renderView('Volt\\Core\\Tenant\\Views\\tenant_form', [
            'pageTitle'  => 'New Tenant · Volt Desk',
            'deskActive' => 'tenants',
            'tenant'     => null,
            'errors'     => [],
        ]);
    }

    public function store()
    {
        $rules = [
            'name'      => 'required|min_length[2]|max_length[100]|is_unique[sys_tenant.name]',
            'label'     => 'permit_empty|max_length[255]',
            'domain'    => 'permit_empty|max_length[255]',
            'db_host'   => 'permit_empty|max_length[255]',
            'db_port'   => 'permit_empty|numeric|max_length[10]',
            'db_name'   => 'required|max_length[255]',
            'db_username' => 'permit_empty|max_length[255]',
            'db_password' => 'permit_empty|max_length[255]',
            'is_active' => 'permit_empty|in_list[0,1]',
        ];

        if (! $this->validate($rules)) {
            return $this->renderView('Volt\\Core\\Tenant\\Views\\tenant_form', [
                'pageTitle'  => 'New Tenant · Volt Desk',
                'deskActive' => 'tenants',
                'tenant'     => null,
                'errors'     => $this->validator->getErrors(),
            ]);
        }

        try {
            $this->tenantService->save($this->request->getPost());
        } catch (\InvalidArgumentException $e) {
            return $this->renderView('Volt\\Core\\Tenant\\Views\\tenant_form', [
                'pageTitle'  => 'New Tenant · Volt Desk',
                'deskActive' => 'tenants',
                'tenant'     => null,
                'errors'     => ['name' => $e->getMessage()],
            ]);
        }

        return redirect()->to(site_url('desk/tenants'));
    }

    public function edit(string $name)
    {
        $tenant = $this->tenantService->getByName($name);

        if ($tenant === null) {
            return redirect()->to(site_url('desk/tenants'));
        }

        return $this->renderView('Volt\\Core\\Tenant\\Views\\tenant_form', [
            'pageTitle'  => 'Edit Tenant · Volt Desk',
            'deskActive' => 'tenants',
            'tenant'     => $tenant,
            'errors'     => [],
        ]);
    }

    public function update(string $name)
    {
        $tenant = $this->tenantService->getByName($name);

        if ($tenant === null) {
            return redirect()->to(site_url('desk/tenants'));
        }

        $rules = [
            'label'     => 'permit_empty|max_length[255]',
            'domain'    => 'permit_empty|max_length[255]',
            'db_host'   => 'permit_empty|max_length[255]',
            'db_port'   => 'permit_empty|numeric|max_length[10]',
            'db_name'   => 'required|max_length[255]',
            'db_username' => 'permit_empty|max_length[255]',
            'db_password' => 'permit_empty|max_length[255]',
            'is_active' => 'permit_empty|in_list[0,1]',
        ];

        if (! $this->validate($rules)) {
            return $this->renderView('Volt\\Core\\Tenant\\Views\\tenant_form', [
                'pageTitle'  => 'Edit Tenant · Volt Desk',
                'deskActive' => 'tenants',
                'tenant'     => $tenant,
                'errors'     => $this->validator->getErrors(),
            ]);
        }

        $post = $this->request->getPost();
        $post['name'] = $name;

        try {
            $this->tenantService->save($post);
        } catch (\InvalidArgumentException $e) {
            return $this->renderView('Volt\\Core\\Tenant\\Views\\tenant_form', [
                'pageTitle'  => 'Edit Tenant · Volt Desk',
                'deskActive' => 'tenants',
                'tenant'     => $tenant,
                'errors'     => ['name' => $e->getMessage()],
            ]);
        }

        return redirect()->to(site_url('desk/tenants'));
    }

    public function delete(string $name)
    {
        if (! $this->tenantService->exists($name)) {
            return redirect()->to(site_url('desk/tenants'));
        }

        $this->tenantService->delete($name);

        return redirect()->to(site_url('desk/tenants'));
    }

    private function renderView(string $view, array $data = []): string
    {
        $actor = service('voltAuth')->currentUser();

        $data['currentUserName'] ??= $actor?->name ?? '';
        $data['isAdmin'] ??= $actor?->isAdmin() ?? false;

        $content = view($view, $data);

        return view('Volt\\Core\\Metadata\\Views\\layouts\\desk', [
            'pageTitle'      => $data['pageTitle'] ?? 'Volt Desk',
            'currentUserName' => $data['currentUserName'],
            'isAdmin'        => $data['isAdmin'],
            'deskActive'     => $data['deskActive'] ?? 'desk',
            'content'        => $content,
        ]);
    }
}
