<?php

declare(strict_types=1);

namespace Volt\Core\Auth\Services;

use CodeIgniter\HTTP\IncomingRequest;
use DateInterval;
use DateTimeImmutable;
use Volt\Core\Auth\Entities\AuthEntity;
use Volt\Core\Auth\Entities\UserEntity;
use Volt\Core\Auth\Models\UserModel;
use Volt\Core\Config\Lang\LangService;

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

        $user = $this->userModel->findByName($username);

        if (! $user || ! $user->isActive()) {
            $session->remove([self::SESSION_USER_KEY, self::SESSION_ROLES_KEY, self::SESSION_LOGIN_KEY]);

            return null;
        }

        return $user;
    }

    public function login(string $username, string $password): AuthEntity
    {
        $auth = new AuthEntity([
            'authenticated'  => false,
            'setup_required' => $this->requiresSetup(),
        ]);

        if ($auth->setup_required) {
            $auth->message = LangService::get('auth.no_admin');

            return $auth;
        }

        $user = $this->userModel->findByName($username);

        if (! $user || ! $user->isActive()) {
            $auth->message = LangService::get('auth.invalid_credentials');

            return $auth;
        }

        if ($this->isLocked($user)) {
            $auth->message = LangService::get('auth.account_locked');

            return $auth;
        }

        if (! password_verify($password, $user->password)) {
            $this->registerFailedAttempt($user);
            $auth->message = LangService::get('auth.invalid_credentials');

            return $auth;
        }

        $this->registerSuccessfulLogin($user);
        $this->startSession($user);

        $auth->fill([
            'authenticated'  => true,
            'setup_required' => false,
            'name'           => $user->name,
            'roles'          => $this->normalizeRoles($user->roles),
        ]);

        return $auth;
    }

    public function setupInitialAdmin(string $username, string $password): AuthEntity
    {
        $auth = new AuthEntity([
            'authenticated'  => false,
            'setup_required' => true,
        ]);

        if ($this->hasAdmin()) {
            $auth->message = 'System already has an admin.';

            return $auth;
        }

        if ($this->userModel->findByName($username)) {
            $auth->message = 'Username already exists.';

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
            return ['ok' => false, 'message' => 'Bạn chưa đăng nhập.'];
        }

        if (! password_verify($currentPassword, (string) $user->password)) {
            return ['ok' => false, 'message' => 'Mật khẩu hiện tại không đúng.'];
        }

        if (mb_strlen($newPassword) < 8) {
            return ['ok' => false, 'message' => 'Mật khẩu mới phải có ít nhất 8 ký tự.'];
        }

        if (password_verify($newPassword, (string) $user->password)) {
            return ['ok' => false, 'message' => 'Mật khẩu mới phải khác mật khẩu hiện tại.'];
        }

        $this->userModel->update($user->name, [
            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
        ]);

        return ['ok' => true, 'message' => 'Đã cập nhật mật khẩu.'];
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function confirmCurrentPassword(string $password): array
    {
        $user = $this->currentUser();

        if (! $user instanceof UserEntity) {
            return ['ok' => false, 'message' => 'Bạn chưa đăng nhập.'];
        }

        if (! password_verify($password, (string) $user->password)) {
            return ['ok' => false, 'message' => 'Mật khẩu không đúng.'];
        }

        return ['ok' => true, 'message' => 'Xác nhận thành công.'];
    }

    public function issueApiToken(UserEntity $user): string
    {
        $token = bin2hex(random_bytes(32));
        $metadata = $this->normalizeMetadata($user->user_metadata);
        $metadata['api_token_hash'] = hash('sha256', $token);
        $metadata['api_token_expires_at'] = (new DateTimeImmutable(sprintf('+%d seconds', self::API_TOKEN_TTL_SECONDS)))->format('Y-m-d H:i:s');

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
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

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

    private function startSession(UserEntity $user): void
    {
        $session = session();
        $session->regenerate(true);
        $session->set([
            self::SESSION_USER_KEY  => $user->name,
            self::SESSION_ROLES_KEY => $this->normalizeRoles($user->roles),
            self::SESSION_LOGIN_KEY => true,
        ]);
    }

    private function registerSuccessfulLogin(UserEntity $user): void
    {
        $metadata = $this->normalizeMetadata($user->user_metadata);
        $metadata['failed_login_attempts'] = 0;
        $metadata['locked_until'] = null;
        $metadata['last_login_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $payload = ['user_metadata' => $metadata];

        if ($this->userModel->hasColumn('failed_login_attempts')) {
            $payload['failed_login_attempts'] = 0;
        }

        if ($this->userModel->hasColumn('locked_until')) {
            $payload['locked_until'] = null;
        }

        if ($this->userModel->hasColumn('last_login_at')) {
            $payload['last_login_at'] = $metadata['last_login_at'];
        }

        $this->userModel->update($user->name, $payload);
    }

    private function registerFailedAttempt(UserEntity $user): void
    {
        $attempts = ((int) $user->failed_login_attempts) + 1;
        $lockedUntil = null;

        if ($attempts >= self::LOGIN_ATTEMPT_LIMIT) {
            $lockedUntil = (new DateTimeImmutable())->add(new DateInterval('PT' . (self::LOGIN_LOCK_MINUTES * 60) . 'S'))->format('Y-m-d H:i:s');
            $attempts = self::LOGIN_ATTEMPT_LIMIT;
        }

        $metadata = $this->normalizeMetadata($user->user_metadata);
        $metadata['failed_login_attempts'] = $attempts;
        $metadata['locked_until'] = $lockedUntil;
        $payload = ['user_metadata' => $metadata];

        if ($this->userModel->hasColumn('failed_login_attempts')) {
            $payload['failed_login_attempts'] = $attempts;
        }

        if ($this->userModel->hasColumn('locked_until')) {
            $payload['locked_until'] = $lockedUntil;
        }

        $this->userModel->update($user->name, $payload);
    }

    private function isLocked(UserEntity $user): bool
    {
        $lockedUntil = $user->locked_until;

        if (! is_string($lockedUntil) || $lockedUntil === '') {
            return false;
        }

        return $lockedUntil > (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    private function normalizeRoles(mixed $roles): array
    {
        return array_values(array_filter(
            array_map('strval', $this->userModel->decodeJsonField($roles)),
            static fn (string $role): bool => $role !== '',
        ));
    }

    private function normalizeMetadata(mixed $metadata): array
    {
        return $this->userModel->decodeJsonField($metadata);
    }
}
