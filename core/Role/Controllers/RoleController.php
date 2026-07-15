<?php

declare(strict_types=1);

namespace Volt\Core\Role\Controllers;

use CodeIgniter\Controller;
use Config\Services;
use Volt\Core\Auth\Entities\UserEntity;
use Volt\Core\Role\Models\RoleModel;
use Volt\Core\Role\Models\RolePermissionModel;

class RoleController extends Controller
{
    private RoleModel $roleModel;
    private RolePermissionModel $permissionModel;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        helper(['form', 'url']);
        $this->roleModel = new RoleModel();
        $this->permissionModel = new RolePermissionModel();
    }

    public function index()
    {
        $roles = $this->roleModel->orderBy('name', 'ASC')->findAll();

        return $this->renderView('Volt\\Core\\Role\\Views\\role_list', [
            'pageTitle'  => 'Role List · Volt Desk',
            'deskActive' => 'roles',
            'roles'      => $roles,
        ]);
    }

    public function create()
    {
        return $this->renderView('Volt\\Core\\Role\\Views\\role_form', [
            'pageTitle'  => 'New Role · Volt Desk',
            'deskActive' => 'roles',
            'role'       => null,
            'errors'     => [],
        ]);
    }

    public function store()
    {
        $rules = [
            'name'  => 'required|min_length[2]|max_length[100]|is_unique[sys_role.name]',
            'label' => 'required|max_length[255]',
        ];

        if (! $this->validate($rules)) {
            return $this->renderView('Volt\\Core\\Role\\Views\\role_form', [
                'pageTitle'  => 'New Role · Volt Desk',
                'deskActive' => 'roles',
                'role'       => null,
                'errors'     => $this->validator->getErrors(),
            ]);
        }

        $name = trim((string) $this->request->getPost('name'));
        $label = trim((string) $this->request->getPost('label'));
        $description = trim((string) $this->request->getPost('description'));

        $actor = service('voltAuth')->currentUser();

        $this->roleModel->insert([
            'name'        => $name,
            'label'       => $label,
            'description' => $description !== '' ? $description : null,
            'is_system'   => 0,
            'owner'       => $actor instanceof UserEntity ? $actor->name : 'system',
        ]);

        return redirect()->to(site_url('desk/roles'));
    }

    public function edit(string $name)
    {
        $role = $this->roleModel->findByName($name);

        if ($role === null) {
            return redirect()->to(site_url('desk/roles'));
        }

        return $this->renderView('Volt\\Core\\Role\\Views\\role_form', [
            'pageTitle'  => 'Edit Role · Volt Desk',
            'deskActive' => 'roles',
            'role'       => $role,
            'errors'     => [],
        ]);
    }

    public function update(string $name)
    {
        $role = $this->roleModel->findByName($name);

        if ($role === null) {
            return redirect()->to(site_url('desk/roles'));
        }

        $rules = [
            'label' => 'required|max_length[255]',
        ];

        if (! $this->validate($rules)) {
            return $this->renderView('Volt\\Core\\Role\\Views\\role_form', [
                'pageTitle'  => 'Edit Role · Volt Desk',
                'deskActive' => 'roles',
                'role'       => $role,
                'errors'     => $this->validator->getErrors(),
            ]);
        }

        $label = trim((string) $this->request->getPost('label'));
        $description = trim((string) $this->request->getPost('description'));

        $this->roleModel->update($name, [
            'label'       => $label,
            'description' => $description !== '' ? $description : null,
        ]);

        return redirect()->to(site_url('desk/roles'));
    }

    public function delete(string $name)
    {
        $role = $this->roleModel->findByName($name);

        if ($role === null) {
            return redirect()->to(site_url('desk/roles'));
        }

        if ((bool) $role->is_system) {
            return redirect()->to(site_url('desk/roles'));
        }

        $this->permissionModel->deletePermissionsForRole($name);
        $this->roleModel->delete($name);

        return redirect()->to(site_url('desk/roles'));
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
