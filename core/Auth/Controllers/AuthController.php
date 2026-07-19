<?php

declare(strict_types=1);

namespace Volt\Core\Auth\Controllers;

use CodeIgniter\Controller;
use Volt\Core\Auth\Entities\UserEntity;
use Volt\Core\Auth\Services\AuthService;

class AuthController extends Controller
{
    private AuthService $authService;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        helper(['form', 'url']);
        $this->authService = service('voltAuth');
    }

    public function index()
    {
        $user = $this->authService->currentUser();

        if ($user === null) {
            return redirect()->to(site_url('login'));
        }

        return view('auth/dashboard', [
            'user' => $user,
        ]);
    }

    public function login()
    {
        if ($this->authService->currentUser() !== null) {
            return redirect()->to(site_url('/'));
        }

        return view('auth/login', [
            'setupRequired' => $this->authService->requiresSetup(),
            'mode'          => $this->authService->requiresSetup() ? 'setup' : 'login',
            'error'         => session()->getFlashdata('auth_error'),
            'success'       => session()->getFlashdata('auth_success'),
        ]);
    }

    public function authenticate()
    {
        $rules = [
            'name'     => 'required|min_length[3]|max_length[100]',
            'password' => 'required|min_length[8]|max_length[255]',
        ];

        if (! $this->validate($rules)) {
            return view('auth/login', [
                'setupRequired' => $this->authService->requiresSetup(),
                'mode'          => 'login',
                'error'         => implode(' ', $this->validator->getErrors()),
            ]);
        }

        $auth = $this->authService->login(
            trim((string) $this->request->getPost('name')),
            (string) $this->request->getPost('password'),
        );

        if (! $auth->authenticated) {
            return view('auth/login', [
                'setupRequired' => $auth->setup_required,
                'mode'          => $auth->setup_required ? 'setup' : 'login',
                'error'         => $auth->message ?? 'Đăng nhập thất bại.',
            ]);
        }

        return redirect()->to(site_url('/'));
    }

    public function setup()
    {
        if (! $this->authService->requiresSetup()) {
            return redirect()->to(site_url('login'));
        }

        $rules = [
            'name'                  => 'required|min_length[3]|max_length[100]|alpha_numeric_punct',
            'password'              => 'required|min_length[8]|max_length[255]',
            'password_confirmation' => 'required|matches[password]',
        ];

        if (! $this->validate($rules)) {
            return view('auth/login', [
                'setupRequired' => true,
                'mode'          => 'setup',
                'error'         => implode(' ', $this->validator->getErrors()),
            ]);
        }

        $auth = $this->authService->setupInitialAdmin(
            trim((string) $this->request->getPost('name')),
            (string) $this->request->getPost('password'),
        );

        if (! $auth->authenticated) {
            return view('auth/login', [
                'setupRequired' => true,
                'mode'          => 'setup',
                'error'         => $auth->message ?? 'Không thể tạo admin.',
            ]);
        }

        return redirect()->to(site_url('/'));
    }

    public function apiLogin()
    {
        $payload = $this->request->getJSON(true);
        $name = is_array($payload) ? (string) ($payload['name'] ?? '') : (string) $this->request->getPost('name');
        $password = is_array($payload) ? (string) ($payload['password'] ?? '') : (string) $this->request->getPost('password');

        if (mb_strlen(trim($name)) < 3 || mb_strlen($password) < 8) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => 'Invalid credentials payload',
            ]);
        }

        $auth = $this->authService->login(trim($name), $password);

        if (! $auth->authenticated) {
            return $this->response->setStatusCode($auth->setup_required ? 409 : 401)->setJSON([
                'status'  => 'error',
                'message' => $auth->message ?? 'Login failed',
                'setup_required' => $auth->setup_required,
            ]);
        }

        $user = $this->authService->currentUser();
        $token = $user ? $this->authService->issueApiToken($user) : null;

        return $this->response->setJSON([
            'status' => 'ok',
            'token'  => $token,
            'user'   => [
                'name'  => $auth->name,
                'roles' => $auth->roles ?? [],
            ],
        ]);
    }

    public function apiMe()
    {
        $user = $this->authService->currentApiUser($this->request);

        if ($user === null) {
            return $this->response->setStatusCode(401)->setJSON([
                'status'  => 'error',
                'message' => 'Unauthorized',
            ]);
        }

        return $this->response->setJSON([
            'status' => 'ok',
            'user'   => [
                'name'  => $user->name,
                'roles' => $user->roles ?? [],
            ],
        ]);
    }

    public function logout()
    {
        $this->authService->logout();

        return redirect()->to(site_url('login'));
    }

    public function profile()
    {
        $user = $this->authService->currentUser();

        if ($user === null) {
            return redirect()->to(site_url('login'));
        }

        return view('auth/profile', $this->profileViewData($user));
    }

    public function updateProfile()
    {
        $user = $this->authService->currentUser();

        if ($user === null) {
            return redirect()->to(site_url('login'));
        }

        $rules = [
            'current_password'      => 'required|min_length[8]|max_length[255]',
            'password'              => 'required|min_length[8]|max_length[255]',
            'password_confirmation' => 'required|matches[password]',
        ];

        if (! $this->validate($rules)) {
            return view('auth/profile', $this->profileViewData($user, [
                'error' => implode(' ', $this->validator->getErrors()),
            ]));
        }

        $result = $this->authService->changePassword(
            (string) $this->request->getPost('current_password'),
            (string) $this->request->getPost('password'),
        );

        if (! $result['ok']) {
            return view('auth/profile', $this->profileViewData($user, [
                'error' => $result['message'],
            ]));
        }

        return redirect()->to(site_url('desk/profile'))->with('profile_success', $result['message']);
    }

    public function generateApiKey()
    {
        $user = $this->authService->currentUser();

        if ($user === null) {
            return redirect()->to(site_url('login'));
        }

        $keys = $this->authService->generateApiKeySecret($user);

        session()->setFlashdata('new_api_key', $keys['api_key']);
        session()->setFlashdata('new_api_secret', $keys['api_secret']);

        return redirect()->to(site_url('desk/profile'));
    }

    private function profileViewData(UserEntity $user, array $extra = []): array
    {
        $flashKey = session()->getFlashdata('new_api_key');
        $flashSecret = session()->getFlashdata('new_api_secret');

        return array_merge([
            'user'             => $user,
            'isAdmin'          => $user->isAdmin(),
            'currentUserName'  => (string) $user->name,
            'error'            => session()->getFlashdata('profile_error'),
            'success'          => session()->getFlashdata('profile_success'),
            'apiKey'           => $flashKey ?? $user->api_key,
            'newSecret'        => $flashSecret,
        ], $extra);
    }
}
