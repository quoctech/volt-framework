<?php

declare(strict_types=1);

namespace Volt\Core\Tenant\Controllers;

use CodeIgniter\Controller;
use Volt\Core\Database\VoltDatabase;
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
            $tenant = $this->tenantService->save($this->request->getPost());
        } catch (\InvalidArgumentException $e) {
            return $this->renderView('Volt\\Core\\Tenant\\Views\\tenant_form', [
                'pageTitle'  => 'New Tenant · Volt Desk',
                'deskActive' => 'tenants',
                'tenant'     => null,
                'errors'     => ['name' => $e->getMessage()],
            ]);
        }

        try {
            VoltDatabase::createTenantDatabase(
                $tenant['db_name'],
                $tenant['db_host'],
                (int) $tenant['db_port'],
            );

            VoltDatabase::migrateTenantDatabase(
                $tenant['db_name'],
                $tenant['db_host'],
                (int) $tenant['db_port'],
                $tenant['db_username'],
                $tenant['db_password'],
            );
        } catch (\Throwable $e) {
            try {
                VoltDatabase::dropTenantDatabase(
                    $tenant['db_name'],
                    $tenant['db_host'],
                    (int) $tenant['db_port'],
                );
            } catch (\Throwable) {
            }

            $this->tenantService->delete($tenant['name']);

            return $this->renderView('Volt\\Core\\Tenant\\Views\\tenant_form', [
                'pageTitle'  => 'New Tenant · Volt Desk',
                'deskActive' => 'tenants',
                'tenant'     => null,
                'errors'     => ['name' => 'Không thể tạo database: ' . $e->getMessage()],
            ]);
        }

        session()->setFlashdata('auth_success', 'Tenant "' . $tenant['label'] . '" đã được tạo với database "' . $tenant['db_name'] . '".');

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

        $tenant = $this->tenantService->getByName($name);

        if ($tenant === null) {
            return redirect()->to(site_url('desk/tenants'));
        }

        try {
            $this->tenantService->softDelete($name);
        } catch (\Throwable $e) {
            session()->setFlashdata('auth_error', 'Không thể xoá tenant: ' . $e->getMessage());

            return redirect()->to(site_url('desk/tenants'));
        }

        $currentTenant = session()->get(VoltDatabase::TENANT_SESSION_KEY);

        if ($currentTenant === $name) {
            session()->remove([VoltDatabase::TENANT_SESSION_KEY]);
        }

        $graceDays = config(\Config\Volt::class)->tenantDeleteGraceDays;

        session()->setFlashdata('auth_success', 'Tenant "' . $tenant['label'] . '" đã được chuyển vào thùng rác. Database sẽ bị xóa sau ' . $graceDays . ' ngày.');

        return redirect()->to(site_url('desk/tenants'));
    }

    public function trash()
    {
        $tenants = $this->tenantService->getTrashed();

        return $this->renderView('Volt\\Core\\Tenant\\Views\\tenant_trash', [
            'pageTitle'  => 'Tenant Trash · Volt Desk',
            'deskActive' => 'tenants',
            'tenants'    => $tenants,
        ]);
    }

    public function restore(string $name)
    {
        try {
            $this->tenantService->restore($name);
            session()->setFlashdata('auth_success', 'Tenant "' . $name . '" đã được khôi phục.');
        } catch (\Throwable $e) {
            session()->setFlashdata('auth_error', $e->getMessage());
        }

        return redirect()->to(site_url('desk/tenants/trash'));
    }

    public function purge(string $name)
    {
        try {
            $backup = $this->tenantService->purge($name, (bool) $this->request->getPost('force'));
            session()->setFlashdata(
                'auth_success',
                $backup !== ''
                    ? 'Tenant "' . $name . '" đã bị xóa vĩnh viễn. Backup: ' . $backup
                    : 'Tenant "' . $name . '" đã bị xóa vĩnh viễn.',
            );
        } catch (\Throwable $e) {
            session()->setFlashdata('auth_error', $e->getMessage());
        }

        return redirect()->to(site_url('desk/tenants/trash'));
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
