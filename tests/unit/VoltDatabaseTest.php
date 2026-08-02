<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\ReflectionHelper;
use Volt\Core\Database\VoltDatabase;

/**
 * Tests cho VoltDatabase: connection caching, hub/tenant resolution,
 * và tenant session logic.
 *
 * @internal
 */
final class VoltDatabaseTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use ReflectionHelper;

    protected $migrate = false;
    protected $refresh = false;

    protected function tearDown(): void
    {
        VoltDatabase::reset();
        parent::tearDown();
    }

    public function testConnectionReturnsBaseConnection(): void
    {
        $this->assertInstanceOf(\CodeIgniter\Database\BaseConnection::class, VoltDatabase::connection());
    }

    public function testConnectionIsCachedPerGroup(): void
    {
        $first = VoltDatabase::connection();
        $second = VoltDatabase::connection();

        $this->assertSame($first, $second);
    }

    public function testResetClearsInternalInstanceCache(): void
    {
        VoltDatabase::connection();
        VoltDatabase::connection();

        VoltDatabase::reset();

        $instances = self::getPrivateProperty(VoltDatabase::class, 'instances');
        $this->assertSame([], $instances);
    }

    public function testConnectionStillWorksAfterReset(): void
    {
        VoltDatabase::connection();
        VoltDatabase::reset();

        $db = VoltDatabase::connection();
        $this->assertInstanceOf(\CodeIgniter\Database\BaseConnection::class, $db);
        $this->assertNotFalse($db->query('SELECT 1 AS ok'));
    }

    public function testConnectionSupportsQueries(): void
    {
        $db = VoltDatabase::connection();
        $result = $db->query('SELECT 1 AS ok');

        $this->assertNotFalse($result);
        $row = $result->getRowArray();
        $this->assertSame('1', (string) ($row['ok'] ?? ''));
    }

    public function testHubConnectionMatchesDefaultGroup(): void
    {
        $this->assertSame(VoltDatabase::connection(), VoltDatabase::hubConnection());
    }

    public function testResolveTenantReturnsNullWithoutSession(): void
    {
        $this->assertNull(VoltDatabase::resolveTenant(null));
    }

    public function testResolveTenantReturnsExplicitName(): void
    {
        $this->assertSame('acme', VoltDatabase::resolveTenant('acme'));
    }

    public function testResolveTenantReadsSession(): void
    {
        session()->set('tenant', 'session_tenant');

        $this->assertSame('session_tenant', VoltDatabase::resolveTenant(null));

        session()->remove('tenant');
    }

    public function testTenantConnectionThrowsForUnknownTenant(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/not found or inactive|sys_tenant/i');

        VoltDatabase::tenantConnection('no-such-tenant-' . bin2hex(random_bytes(4)));
    }
}
