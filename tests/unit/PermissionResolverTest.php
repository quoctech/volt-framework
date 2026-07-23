<?php

declare(strict_types=1);

use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseResult;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Volt\Core\Auth\Entities\UserEntity;
use Volt\Core\Auth\Services\AuthService;
use Volt\Core\Security\PermissionResolver;

/**
 * @internal
 */
final class PermissionResolverTest extends CIUnitTestCase
{
    private MockObject $dbc;
    private MockObject $cache;
    private MockObject $auth;
    private PermissionResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dbc = $this->createMock(BaseConnection::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->auth = $this->createMock(AuthService::class);

        // No default stub for get() – tests must configure it explicitly

        $this->resolver = new PermissionResolver($this->dbc, $this->cache, $this->auth);
    }

    public function testCanReturnsFalseWhenNoActor(): void
    {
        $this->auth->method('currentUser')->willReturn(null);

        $this->assertFalse($this->resolver->can('leave', 'read'));
    }

    public function testCanReturnsTrueForAdmin(): void
    {
        $user = new UserEntity();
        $user->roles = ['admin'];

        $this->assertTrue($this->resolver->can('leave', 'read', null, null, $user));
    }

    public function testCanReturnsTrueWhenPermissionExists(): void
    {
        $user = new UserEntity();
        $user->name = 'john';
        $user->roles = ['editor'];

        $this->mockCacheMiss();
        $this->mockDbResult([
            [
                'entity' => 'leave',
                'state' => '*',
                'role' => 'editor',
                'actions' => json_encode(['read' => 1, 'write' => 1, 'submit' => 1]),
                'field_permissions' => '[]',
            ],
        ]);

        $this->assertTrue($this->resolver->can('leave', 'read', null, null, $user));
    }

    public function testCanReturnsFalseWhenPermissionMissing(): void
    {
        $user = new UserEntity();
        $user->name = 'john';
        $user->roles = ['viewer'];

        $this->mockCacheMiss();
        $this->mockDbResult([
            [
                'entity' => 'leave',
                'state' => '*',
                'role' => 'viewer',
                'actions' => json_encode(['read' => 1]),
                'field_permissions' => '[]',
            ],
        ]);

        $this->assertFalse($this->resolver->can('leave', 'write', null, null, $user));
    }

    public function testCanRespectsStateScoping(): void
    {
        $user = new UserEntity();
        $user->name = 'john';
        $user->roles = ['editor'];

        $this->mockCacheMiss();
        $this->mockDbResult([
            [
                'entity' => 'leave',
                'state' => 'Draft',
                'role' => 'editor',
                'actions' => json_encode(['write' => 1]),
                'field_permissions' => '[]',
            ],
        ]);

        $this->assertTrue($this->resolver->can('leave', 'write', 'Draft', null, $user));
        $this->assertFalse($this->resolver->can('leave', 'write', 'Submitted', null, $user));
    }

    public function testCanSavesToCacheOnMiss(): void
    {
        $user = new UserEntity();
        $user->name = 'john';
        $user->roles = ['editor'];

        $this->mockCacheMiss();
        $this->mockDbResult([]);

        // save() should be called after cache miss
        $this->cache->expects($this->atLeastOnce())
            ->method('save')
            ->with(
                $this->stringContains('volt_permission_matrix'),
                $this->isType('array'),
                $this->greaterThan(0),
            );

        $this->resolver->can('leave', 'read', null, null, $user);

        $this->assertTrue(true);
    }

    public function testCanUsesCachedMatrix(): void
    {
        $user = new UserEntity();
        $user->name = 'john';
        $user->roles = ['editor'];

        $versionKey = 'volt_perm_cache_ver';

        $this->cache->method('get')->willReturnCallback(function (string $key) use ($versionKey): mixed {
            if ($key === $versionKey) {
                return null;
            }

            // Return cached matrix for any matrix key
            if (str_starts_with($key, 'volt_permission_matrix_')) {
                return [
                    'leave' => [
                        '*' => [
                            ['role' => 'editor', 'actions' => ['read' => 1, 'write' => 0], 'field_permissions' => []],
                        ],
                    ],
                ];
            }

            return null;
        });

        // DB should NOT be called when cache is hit

        $this->assertTrue($this->resolver->can('leave', 'read', null, null, $user));
        $this->assertFalse($this->resolver->can('leave', 'write', null, null, $user));
    }

    public function testCanWithFieldLevelPermission(): void
    {
        $user = new UserEntity();
        $user->name = 'john';
        $user->roles = ['hr'];

        $this->mockCacheMiss();
        $this->mockDbResult([
            [
                'entity' => 'leave',
                'state' => '*',
                'role' => 'hr',
                'actions' => json_encode(['read' => 1]),
                'field_permissions' => json_encode([
                    'salary' => ['read' => 0, 'write' => 0],
                ]),
            ],
        ]);

        $this->assertTrue($this->resolver->can('leave', 'read', null, null, $user));
        $this->assertFalse($this->resolver->can('leave', 'read', null, 'salary', $user));
    }

    public function testHasEntityPermissionReturnsTrueForAdmin(): void
    {
        $user = new UserEntity();
        $user->roles = ['admin'];

        $this->assertTrue($this->resolver->hasEntityPermission('leave', $user));
    }

    public function testHasEntityPermissionReturnsFalseForUnknownEntity(): void
    {
        $user = new UserEntity();
        $user->name = 'john';
        $user->roles = ['editor'];

        $this->mockCacheMiss();
        $this->mockDbResult([
            [
                'entity' => 'leave',
                'state' => '*',
                'role' => 'editor',
                'actions' => json_encode(['read' => 1]),
                'field_permissions' => '[]',
            ],
        ]);

        $this->assertFalse($this->resolver->hasEntityPermission('payroll', $user));
    }

    public function testClearAllCacheInvalidates(): void
    {
        $this->cache->expects($this->once())
            ->method('save')
            ->with($this->stringContains('volt_perm_cache_ver'), $this->isType('float'));

        $this->resolver->clearAllCache();
    }

    public function testNormalizeEntityNameIsCaseInsensitive(): void
    {
        $user = new UserEntity();
        $user->roles = ['admin'];

        $this->assertTrue($this->resolver->can('LEAVE', 'read', null, null, $user));
        $this->assertTrue($this->resolver->can('  Leave  ', 'read', null, null, $user));
    }

    public function testEmptyEntityOrActionReturnsFalse(): void
    {
        $user = new UserEntity();
        $user->roles = ['admin'];

        $this->assertFalse($this->resolver->can('', 'read', null, null, $user));
    }

    public function testMatrixForUserReturnsEmptyWhenNoRoles(): void
    {
        $user = new UserEntity();
        $user->name = 'john';
        $user->roles = [];

        $this->mockCacheMiss();
        $this->assertSame([], $this->resolver->matrixForUser($user));
    }

    public function testFallbackStateMatchesWildcard(): void
    {
        $user = new UserEntity();
        $user->name = 'john';
        $user->roles = ['editor'];

        $this->mockCacheMiss();
        $this->mockDbResult([
            [
                'entity' => 'leave',
                'state' => '*',
                'role' => 'editor',
                'actions' => json_encode(['submit' => 1]),
                'field_permissions' => '[]',
            ],
        ]);

        // Wildcard state should match any specific state
        $this->assertTrue($this->resolver->can('leave', 'submit', 'Draft', null, $user));
        $this->assertTrue($this->resolver->can('leave', 'submit', 'Submitted', null, $user));
    }

    /** Set up cache to always miss (return null). */
    private function mockCacheMiss(): void
    {
        $this->cache->method('get')->willReturn(null);
    }

    /** Set up DB to return given permission rows. */
    private function mockDbResult(array $rows): void
    {
        $result = $this->createMock(BaseResult::class);
        $result->method('getResultArray')->willReturn($rows);

        $builder = $this->createMock(BaseBuilder::class);
        $builder->method('whereIn')->willReturnSelf();
        $builder->method('orderBy')->willReturnSelf();
        $builder->method('get')->willReturn($result);

        $this->dbc->method('table')->with('sys_permission')->willReturn($builder);
    }
}
