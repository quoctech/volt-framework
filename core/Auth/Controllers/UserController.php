<?php

declare(strict_types=1);

namespace Volt\Core\Auth\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RedirectResponse;
use Volt\Core\Audit\AuditTrailWriter;
use Volt\Core\Auth\Models\UserModel;
use Volt\Core\Role\Models\RoleModel;

class UserController extends Controller
{
    private readonly UserModel $userModel;
    private readonly RoleModel $roleModel;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);
        helper(['form', 'url']);
        $this->userModel = new UserModel();
        $this->roleModel = new RoleModel();
    }

    public function index(): string
    {
        $users = $this->userModel->orderBy('name', 'ASC')->findAll();

        return $this->renderView('Volt\\Core\\Auth\\Views\\user_list', [
            'pageTitle'  => 'User List · Volt Desk',
            'deskActive' => 'users',
            'users'      => $users,
        ]);
    }

    public function create(): string
    {
        $roles = $this->roleModel->orderBy('name', 'ASC')->findAll();

        return $this->renderView('Volt\\Core\\Auth\\Views\\user_form', [
            'pageTitle'  => 'New User · Volt Desk',
            'deskActive' => 'users',
            'user'       => null,
            'allRoles'   => $roles,
            'errors'     => [],
        ]);
    }

    public function store(): RedirectResponse|string
    {
        $rules = [
            'name'     => 'required|min_length[2]|max_length[100]|is_unique[sys_user.name]',
            'password' => 'required|min_length[8]',
        ];

        if (! $this->validate($rules)) {
            $roles = $this->roleModel->orderBy('name', 'ASC')->findAll();

            return $this->renderView('Volt\\Core\\Auth\\Views\\user_form', [
                'pageTitle'  => 'New User · Volt Desk',
                'deskActive' => 'users',
                'user'       => null,
                'allRoles'   => $roles,
                'errors'     => $this->validator->getErrors(),
            ]);
        }

        $name = mb_trim((string) $this->request->getPost('name'));
        $password = (string) $this->request->getPost('password');
        $isActive = (string) $this->request->getPost('is_active') === '1';
        $selectedRoles = $this->request->getPost('roles');

        $this->userModel->insert([
            'name'       => $name,
            'password'   => password_hash($password, PASSWORD_DEFAULT),
            'roles'      => is_array($selectedRoles) ? array_values($selectedRoles) : [],
            'is_active'  => $isActive ? 1 : 0,
            'user_metadata' => [],
        ]);

        $roles = is_array($selectedRoles) ? array_values($selectedRoles) : [];

        service('voltAuditTrailWriter')->write(
            AuditTrailWriter::CAT_ROLE,
            'user:create',
            'sys_user',
            $name,
            [],
            ['roles' => $roles, 'is_active' => $isActive ? 1 : 0],
            service('voltAuth')->currentUser()?->name ?? 'system',
        );

        return redirect()->to(site_url('desk/users'));
    }

    public function edit(string $name): RedirectResponse|string
    {
        $user = $this->userModel->findByName($name);

        if ($user === null) {
            return redirect()->to(site_url('desk/users'));
        }

        $roles = $this->roleModel->orderBy('name', 'ASC')->findAll();

        return $this->renderView('Volt\\Core\\Auth\\Views\\user_form', [
            'pageTitle'  => 'Edit User · Volt Desk',
            'deskActive' => 'users',
            'user'       => $user,
            'allRoles'   => $roles,
            'errors'     => [],
        ]);
    }

    public function update(string $name): RedirectResponse|string
    {
        $user = $this->userModel->findByName($name);

        if ($user === null) {
            return redirect()->to(site_url('desk/users'));
        }

        $rules = [
            'password' => 'permit_empty|min_length[8]',
        ];

        if (! $this->validate($rules)) {
            $roles = $this->roleModel->orderBy('name', 'ASC')->findAll();

            return $this->renderView('Volt\\Core\\Auth\\Views\\user_form', [
                'pageTitle'  => 'Edit User · Volt Desk',
                'deskActive' => 'users',
                'user'       => $user,
                'allRoles'   => $roles,
                'errors'     => $this->validator->getErrors(),
            ]);
        }

        $password = (string) $this->request->getPost('password');
        $isActive = (string) $this->request->getPost('is_active') === '1';
        $selectedRoles = $this->request->getPost('roles');
        $newRoles = is_array($selectedRoles) ? array_values($selectedRoles) : [];

        $payload = [
            'roles'     => $newRoles,
            'is_active' => $isActive ? 1 : 0,
        ];

        if ($password !== '') {
            $payload['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $this->userModel->update($name, $payload);

        $actor = service('voltAuth')->currentUser()?->name ?? 'system';
        $oldRoles = $this->normalizeRoles($user);

        service('voltAuditTrailWriter')->write(
            AuditTrailWriter::CAT_ROLE,
            'user:update',
            'sys_user',
            $name,
            ['roles' => $oldRoles, 'is_active' => (int) $user->is_active],
            ['roles' => $newRoles, 'is_active' => $isActive ? 1 : 0],
            $actor,
        );

        if ($oldRoles !== $newRoles) {
            service('voltAuditTrailWriter')->write(
                AuditTrailWriter::CAT_ROLE,
                'user:roles',
                'sys_user',
                $name,
                ['roles' => $oldRoles],
                ['roles' => $newRoles],
                $actor,
            );
        }

        return redirect()->to(site_url('desk/users'));
    }

    public function delete(string $name): RedirectResponse
    {
        $user = $this->userModel->findByName($name);

        if ($user === null) {
            return redirect()->to(site_url('desk/users'));
        }

        $this->userModel->delete($name);

        service('voltAuditTrailWriter')->write(
            AuditTrailWriter::CAT_ROLE,
            'user:delete',
            'sys_user',
            $name,
            ['roles' => $this->normalizeRoles($user)],
            [],
            service('voltAuth')->currentUser()?->name ?? 'system',
        );

        return redirect()->to(site_url('desk/users'));
    }

    private function normalizeRoles(mixed $roles): array
    {
        if (is_string($roles) && $roles !== '') {
            $decoded = json_validate($roles) ? json_decode($roles, true) : null;

            if (! is_array($decoded)) {
                $decoded = @unserialize($roles, ['allowed_classes' => false]);
            }

            if (! is_array($decoded)) {
                $decoded = [$roles];
            }

            $roles = $decoded;
        }

        if (! is_array($roles)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $roles), static fn (string $role): bool => $role !== ''));
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
