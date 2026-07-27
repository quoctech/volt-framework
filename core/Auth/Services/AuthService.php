<?php

declare(strict_types=1);

namespace Volt\Core\Auth\Services;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\I18n\Time;
use Volt\Core\Auth\Entities\AuthEntity;
use Volt\Core\Auth\Entities\UserEntity;
use Volt\Core\Auth\Models\UserModel;
use Volt\Core\Config\Lang\LangService;
use Volt\Core\Database\VoltDatabase;

class AuthService
{
    private readonly UserModel $userModel;

    private const SESSION_USER_KEY = 'volt_auth_user';
    private const SESSION_ROLES_KEY = 'volt_auth_roles';
    private const SESSION_LOGIN_KEY = 'volt_auth_login';
    private const LOGIN_ATTEMPT_LIMIT = 5;
    private const LOGIN_LOCK_MINUTES = 15;
    private const API_TOKEN_TTL_SECONDS = 604800;

    public function __construct(?UserModel $userModel = null)
    {
        $this->userModel = $userModel ?? new UserModel();
    }

    public function hasAdmin(): bool
    {
        return $this->userModel->findAdminUsers() !== [];
    }

    public function requiresSetup(): bool
    {
        return ! $this->hasAdmin();
    }

    public function currentUser(): ?UserEntity
    {
        $session = session();
        $username = $session->get(self::SESSION_USER_KEY);

        if (! is_string($username) || $username === '') {
            return null;
        }

        $userModel = $this->resolveUserModel(
            $session->get(VoltDatabase::TENANT_SESSION_KEY),
        );

        $user = $userModel->findByName($username);

        if (! $user || ! $user->isActive()) {
            $session->remove([self::SESSION_USER_KEY, self::SESSION_ROLES_KEY, self::SESSION_LOGIN_KEY]);

            return null;
        }

        return $user;
    }

    public function login(string $username, string $password, ?string $tenantName = null): AuthEntity
    {
        $auth = new AuthEntity([
            'authenticated'  => false,
            'setup_required' => $this->requiresSetup(),
        ]);

        if ($auth->setup_required) {
            $auth->message = LangService::get('auth.no_admin');

            return $auth;
        }

        $userModel = $this->resolveUserModel($tenantName);
        $user = $userModel->findByName($username);

        if (! $user || ! $user->isActive()) {
            $auth->message = LangService::get('auth.invalid_credentials');

            return $auth;
        }

        if ($this->isLocked($user)) {
            $auth->message = LangService::get('auth.account_locked');

            return $auth;
        }

        if (! password_verify($password, $user->password)) {
            $this->registerFailedAttempt($user, $userModel);
            $auth->message = LangService::get('auth.invalid_credentials');

            return $auth;
        }

        $this->registerSuccessfulLogin($user, $userModel);
        $this->startSession($user, $tenantName);

        $auth->fill([
            'authenticated'  => true,
            'setup_required' => false,
            'name'           => $user->name,
            'roles'          => $this->normalizeRoles($user->roles),
        ]);

        return $auth;
    }

    private function resolveUserModel(?string $tenantName): UserModel
    {
        if ($tenantName === null) {
            return $this->userModel;
        }

        $db = VoltDatabase::tenantConnection($tenantName);

        return new UserModel($db);
    }

    public function setupInitialAdmin(string $username, string $password): AuthEntity
    {
        $auth = new AuthEntity([
            'authenticated'  => false,
            'setup_required' => true,
        ]);

        if ($this->hasAdmin()) {
            $auth->message = LangService::get('auth.admin_exists');

            return $auth;
        }

        if ($this->userModel->findByName($username)) {
            $auth->message = LangService::get('auth.username_exists');

            return $auth;
        }

        $user = new UserEntity([
            'name'             => $username,
            'password'         => password_hash($password, PASSWORD_DEFAULT),
            'roles'            => ['admin'],
            'user_metadata'    => ['bootstrap_admin' => true],
            'is_active'        => 1,
            'failed_login_attempts' => 0,
        ]);

        $this->userModel->insert($user);
        $this->startSession($user);

        $auth->fill([
            'authenticated'  => true,
            'setup_required' => false,
            'name'           => $user->name,
            'roles'          => ['admin'],
        ]);

        return $auth;
    }

    public function logout(): void
    {
        $session = session();
        $session->remove([self::SESSION_USER_KEY, self::SESSION_ROLES_KEY, self::SESSION_LOGIN_KEY]);
        $session->destroy();
    }

    /**
     * Change password for the currently authenticated user.
     *
     * @return array{ok:bool,message:string}
     */
    public function changePassword(string $currentPassword, string $newPassword): array
    {
        $user = $this->currentUser();

        if (! $user instanceof UserEntity) {
            return ['ok' => false, 'message' => LangService::get('auth.not_logged_in')];
        }

        if (! password_verify($currentPassword, (string) $user->password)) {
            return ['ok' => false, 'message' => LangService::get('auth.current_password_wrong')];
        }

        if (mb_strlen($newPassword) < 8) {
            return ['ok' => false, 'message' => LangService::get('auth.new_password_min_length')];
        }

        if (password_verify($newPassword, (string) $user->password)) {
            return ['ok' => false, 'message' => LangService::get('auth.new_password_same_as_old')];
        }

        $this->userModel->update($user->name, [
            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
        ]);

        return ['ok' => true, 'message' => LangService::get('auth.password_updated')];
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function confirmCurrentPassword(string $password): array
    {
        $user = $this->currentUser();

        if (! $user instanceof UserEntity) {
            return ['ok' => false, 'message' => LangService::get('auth.not_logged_in')];
        }

        if (! password_verify($password, (string) $user->password)) {
            return ['ok' => false, 'message' => LangService::get('auth.password_wrong')];
        }

        return ['ok' => true, 'message' => LangService::get('auth.confirm_success')];
    }

    public function issueApiToken(UserEntity $user): string
    {
        $token = bin2hex(random_bytes(32));
        $metadata = $this->normalizeMetadata($user->user_metadata);
        $metadata['api_token_hash'] = hash('sha256', $token);
        $metadata['api_token_expires_at'] = Time::now()->addSeconds(self::API_TOKEN_TTL_SECONDS)->toDateTimeString();

        $payload = ['user_metadata' => $metadata];

        if ($this->userModel->hasColumn('api_token_hash')) {
            $payload['api_token_hash'] = $metadata['api_token_hash'];
        }

        if ($this->userModel->hasColumn('api_token_expires_at')) {
            $payload['api_token_expires_at'] = $metadata['api_token_expires_at'];
        }

        $this->userModel->update($user->name, $payload);

        return $token;
    }

    public function authenticateApiToken(?string $bearerToken): ?UserEntity
    {
        if (! is_string($bearerToken) || $bearerToken === '') {
            return null;
        }

        $hash = hash('sha256', $bearerToken);
        $now = Time::now()->toDateTimeString();

        if ($this->userModel->hasColumn('api_token_hash') && $this->userModel->hasColumn('api_token_expires_at')) {
            $user = $this->userModel
                ->where('api_token_hash', $hash)
                ->where('api_token_expires_at >=', $now)
                ->first();
        } else {
            $user = array_find(
                $this->userModel->findAll(),
                function ($candidate) use ($hash, $now): bool {
                    if (! $candidate instanceof UserEntity) {
                        return false;
                    }

                    $metadata = $this->normalizeMetadata($candidate->user_metadata);

                    if (($metadata['api_token_hash'] ?? null) !== $hash) {
                        return false;
                    }

                    $expiresAt = $metadata['api_token_expires_at'] ?? null;

                    if (! is_string($expiresAt) || $expiresAt < $now) {
                        return false;
                    }

                    return true;
                },
            );
        }

        if (! $user instanceof UserEntity || ! $user->isActive()) {
            return null;
        }

        return $user;
    }

    public function currentApiUser(IncomingRequest $request): ?UserEntity
    {
        return $this->authenticateApiToken($this->extractBearerToken($request));
    }

    public function extractBearerToken(IncomingRequest $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');

        if ($header === '') {
            return null;
        }

        if (! preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return null;
        }

        return mb_trim($matches[1]);
    }

    public function generateApiKeySecret(UserEntity $user): array
    {
        $apiKey = bin2hex(random_bytes(16));
        $apiSecret = bin2hex(random_bytes(32));
        $hash = password_hash($apiSecret, PASSWORD_DEFAULT);

        $this->userModel->update($user->name, [
            'api_key'        => $apiKey,
            'api_secret_hash' => $hash,
        ]);

        return [
            'api_key'    => $apiKey,
            'api_secret' => $apiSecret,
        ];
    }

    public function authenticateApiKeySecret(?string $bearerToken): ?UserEntity
    {
        if (! is_string($bearerToken) || $bearerToken === '') {
            return null;
        }

        $parts = explode(':', $bearerToken, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$apiKey, $apiSecret] = $parts;

        if ($apiKey === '' || $apiSecret === '') {
            return null;
        }

        if (! $this->userModel->hasColumn('api_key') || ! $this->userModel->hasColumn('api_secret_hash')) {
            return null;
        }

        $user = $this->userModel->where('api_key', $apiKey)->first();

        if (! $user instanceof UserEntity || ! $user->isActive()) {
            return null;
        }

        if (! password_verify($apiSecret, (string) $user->api_secret_hash)) {
            return null;
        }

        if (! $user->isAdmin()) {
            return null;
        }

        return $user;
    }

    private function startSession(UserEntity $user, ?string $tenantName = null): void
    {
        $session = session();
        $session->regenerate(true);

        $data = [
            self::SESSION_USER_KEY  => $user->name,
            self::SESSION_ROLES_KEY => $this->normalizeRoles($user->roles),
            self::SESSION_LOGIN_KEY => true,
        ];

        if ($tenantName !== null) {
            $data[VoltDatabase::TENANT_SESSION_KEY] = $tenantName;
        }

        $session->set($data);
    }

    private function registerSuccessfulLogin(UserEntity $user, ?UserModel $userModel = null): void
    {
        $userModel ??= $this->userModel;
        $metadata = $this->normalizeMetadata($user->user_metadata, $userModel);
        $metadata['failed_login_attempts'] = 0;
        $metadata['locked_until'] = null;
        $metadata['last_login_at'] = Time::now()->toDateTimeString();
        $payload = ['user_metadata' => $metadata];

        if ($userModel->hasColumn('failed_login_attempts')) {
            $payload['failed_login_attempts'] = 0;
        }

        if ($userModel->hasColumn('locked_until')) {
            $payload['locked_until'] = null;
        }

        if ($userModel->hasColumn('last_login_at')) {
            $payload['last_login_at'] = $metadata['last_login_at'];
        }

        $userModel->update($user->name, $payload);
    }

    private function registerFailedAttempt(UserEntity $user, ?UserModel $userModel = null): void
    {
        $userModel ??= $this->userModel;
        $attempts = ((int) $user->failed_login_attempts) + 1;
        $lockedUntil = null;

        if ($attempts >= self::LOGIN_ATTEMPT_LIMIT) {
            $lockedUntil = Time::now()->addSeconds(self::LOGIN_LOCK_MINUTES * 60)->toDateTimeString();
            $attempts = self::LOGIN_ATTEMPT_LIMIT;
        }

        $metadata = $this->normalizeMetadata($user->user_metadata, $userModel);
        $metadata['failed_login_attempts'] = $attempts;
        $metadata['locked_until'] = $lockedUntil;
        $payload = ['user_metadata' => $metadata];

        if ($userModel->hasColumn('failed_login_attempts')) {
            $payload['failed_login_attempts'] = $attempts;
        }

        if ($userModel->hasColumn('locked_until')) {
            $payload['locked_until'] = $lockedUntil;
        }

        $userModel->update($user->name, $payload);
    }

    private function isLocked(UserEntity $user): bool
    {
        $lockedUntil = $user->locked_until;

        if (! is_string($lockedUntil) || $lockedUntil === '') {
            return false;
        }

        return $lockedUntil > Time::now()->toDateTimeString();
    }

    private function normalizeRoles(mixed $roles): array
    {
        return array_values(array_filter(
            array_map('strval', $this->userModel->decodeJsonField($roles)),
            static fn (string $role): bool => $role !== '',
        ));
    }

    private function normalizeMetadata(mixed $metadata, ?UserModel $userModel = null): array
    {
        $userModel ??= $this->userModel;
        return $userModel->decodeJsonField($metadata);
    }
}
