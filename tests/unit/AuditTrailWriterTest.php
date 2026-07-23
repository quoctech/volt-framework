<?php

declare(strict_types=1);

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Volt\Core\Audit\AuditTrailWriter;
use Volt\Core\Auth\Entities\UserEntity;
use Volt\Core\Auth\Services\AuthService;

/**
 * @internal
 */
final class AuditTrailWriterTest extends CIUnitTestCase
{
    private MockObject $dbc;
    private MockObject $auth;
    private AuditTrailWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dbc = $this->createMock(BaseConnection::class);
        $this->auth = $this->createMock(AuthService::class);
        $this->writer = new AuditTrailWriter($this->dbc, $this->auth);
    }

    public function testWriteInsertsIntoAuditTrail(): void
    {
        $builder = $this->createMock(BaseBuilder::class);
        $builder->expects($this->once())
            ->method('insert')
            ->with($this->callback(function (array $payload): bool {
                $this->assertSame('leave', $payload['entity']);
                $this->assertSame('LV-0001', $payload['doc_id']);
                $this->assertSame('submit', $payload['action']);
                $this->assertSame('admin', $payload['changed_by']);
                $this->assertStringContainsString('before', $payload['delta']);
                $this->assertStringContainsString('after', $payload['delta']);
                $this->assertStringContainsString('changes', $payload['delta']);

                return true;
            }))
            ->willReturn(true);

        $this->dbc->expects($this->once())
            ->method('table')
            ->with('sys_audit_trail')
            ->willReturn($builder);

        $this->auth->method('currentUser')->willReturn(null);

        $result = $this->writer->write('leave', 'LV-0001', 'submit', ['status' => 'Draft'], ['status' => 'Submitted'], 'admin');

        $this->assertTrue($result);
    }

    public function testWriteResolvesActorFromAuthWhenNotPassed(): void
    {
        $user = new UserEntity();
        $user->name = 'john';

        $builder = $this->createMock(BaseBuilder::class);
        $builder->method('insert')
            ->with($this->callback(function (array $p): bool {
                return $p['changed_by'] === 'john';
            }))
            ->willReturn(true);

        $this->dbc->method('table')->with('sys_audit_trail')->willReturn($builder);
        $this->auth->method('currentUser')->willReturn($user);

        $result = $this->writer->write('entity', 'doc1', 'update', [], []);
        $this->assertTrue($result);
    }

    public function testWriteDefaultsToSystemWhenNoActor(): void
    {
        $builder = $this->createMock(BaseBuilder::class);
        $builder->method('insert')
            ->with($this->callback(function (array $p): bool {
                return $p['changed_by'] === 'system';
            }))
            ->willReturn(true);

        $this->dbc->method('table')->with('sys_audit_trail')->willReturn($builder);
        $this->auth->method('currentUser')->willReturn(null);

        $result = $this->writer->write('entity', 'doc1', 'update', [], []);
        $this->assertTrue($result);
    }

    public function testDiffEmptyWhenNoChanges(): void
    {
        $builder = $this->createMock(BaseBuilder::class);
        $builder->expects($this->once())
            ->method('insert')
            ->with($this->callback(function (array $payload): bool {
                $delta = json_decode($payload['delta'], true);
                $this->assertSame([], $delta['changes']);

                return true;
            }))
            ->willReturn(true);
        $this->dbc->method('table')->willReturn($builder);
        $this->auth->method('currentUser')->willReturn(null);

        $before = ['a' => 1, 'b' => 2];
        $after = ['a' => 1, 'b' => 2];

        $this->writer->write('e', '1', 'action', $before, $after);
    }

    public function testInsertReturnsFalseOnFailure(): void
    {
        $builder = $this->createMock(BaseBuilder::class);
        $builder->method('insert')->willReturn(false);
        $this->dbc->method('table')->willReturn($builder);
        $this->auth->method('currentUser')->willReturn(null);

        $result = $this->writer->write('e', '1', 'action');
        $this->assertFalse($result);
    }

    public function testDiffIncludesBeforeAndAfterInDelta(): void
    {
        $builder = $this->createMock(BaseBuilder::class);
        $builder->method('insert')
            ->with($this->callback(function (array $payload): bool {
                $delta = json_decode($payload['delta'], true);
                $this->assertArrayHasKey('before', $delta);
                $this->assertArrayHasKey('after', $delta);
                $this->assertArrayHasKey('changes', $delta);
                $this->assertSame(['status' => 'Draft'], $delta['before']);
                $this->assertSame(['status' => 'Submitted'], $delta['after']);
                $this->assertSame(['status' => ['before' => 'Draft', 'after' => 'Submitted']], $delta['changes']);

                return true;
            }))
            ->willReturn(true);
        $this->dbc->method('table')->willReturn($builder);
        $this->auth->method('currentUser')->willReturn(null);

        $this->writer->write('e', '1', 'submit', ['status' => 'Draft'], ['status' => 'Submitted']);
    }
}
