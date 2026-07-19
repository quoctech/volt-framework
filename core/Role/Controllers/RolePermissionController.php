<?php

declare(strict_types=1);

namespace Volt\Core\Role\Controllers;

use CodeIgniter\Controller;
use Volt\Core\Role\Models\RoleModel;
use Volt\Core\Role\Models\RolePermissionModel;

class RolePermissionController extends Controller
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

    public function index(string $role)
    {
        $roleEntity = $this->roleModel->findByName($role);

        if ($roleEntity === null) {
            return redirect()->to(site_url('desk/roles'));
        }

        $permissions = $this->permissionModel->getPermissionsForRole($role);
        $entityNames = $this->permissionModel->getAllEntityNames();

        return $this->renderView('Volt\\Core\\Role\\Views\\role_permission', [
            'pageTitle'   => 'Role Permissions · Volt Desk',
            'deskActive'  => 'roles',
            'role'        => $roleEntity,
            'permissions' => $permissions,
            'entityNames' => $entityNames,
        ]);
    }

    public function update(string $role)
    {
        $roleEntity = $this->roleModel->findByName($role);

        if ($roleEntity === null) {
            return redirect()->to(site_url('desk/roles'));
        }

        $entities = $this->request->getPost('entities');

        if (! is_array($entities)) {
            return redirect()->to(site_url("desk/roles/permissions/{$role}"));
        }

        foreach ($entities as $entity => $actions) {
            if (! is_array($actions)) {
                continue;
            }

            $this->permissionModel->savePermissions($role, (string) $entity, $actions);
        }

        service('voltPermissionResolver')->clearAllCache();

        return redirect()->to(site_url("desk/roles/permissions/{$role}"));
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
