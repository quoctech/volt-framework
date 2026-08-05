<?php

declare(strict_types=1);

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseResult;
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

        $this->mockWriteDeps($builder);
        $this->auth->method('currentUser')->willReturn(null);

        $result = $this->writer->write(
            AuditTrailWriter::CAT_WORKFLOW,
            'submit',
            'leave',
            'LV-0001',
            ['status' => 'Draft'],
            ['status' => 'Submitted'],
            'admin',
        );

        $this->assertTrue($result);
    }

    public function testWriteNormalizesEntityNameToSnakeCase(): void
    {
        $builder = $this->createMock(BaseBuilder::class);
        $builder->expects($this->once())
            ->method('insert')
            ->with($this->callback(function (array $payload): bool {
                $this->assertSame('employeeeducation', $payload['entity']);

                return true;
            }))
            ->willReturn(true);

        $this->mockWriteDeps($builder);
        $this->auth->method('currentUser')->willReturn(null);

        $result = $this->writer->write(
            AuditTrailWriter::CAT_DATA,
            'create',
            'Employeeeducation',
            'EE-0001',
            [],
            ['name' => 'EE-0001'],
            'admin',
        );

        $this->assertTrue($result);
    }

    public function testWriteKeepsAlreadySnakeCaseEntity(): void
    {
        $builder = $this->createMock(BaseBuilder::class);
        $builder->expects($this->once())
            ->method('insert')
            ->with($this->callback(function (array $payload): bool {
                $this->assertSame('test_wf', $payload['entity']);

                return true;
            }))
            ->willReturn(true);

        $this->mockWriteDeps($builder);
        $this->auth->method('currentUser')->willReturn(null);

        $result = $this->writer->write(
            AuditTrailWriter::CAT_DATA,
            'create',
            'test_wf',
            'REQ-1',
            [],
            ['name' => 'REQ-1'],
            'admin',
        );

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

        $this->mockWriteDeps($builder);
        $this->auth->method('currentUser')->willReturn($user);

        $result = $this->writer->write('data', 'update', 'entity', 'doc1');
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

        $this->mockWriteDeps($builder);
        $this->auth->method('currentUser')->willReturn(null);

        $result = $this->writer->write('data', 'update', 'entity', 'doc1');
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

        $this->mockWriteDeps($builder);
        $this->auth->method('currentUser')->willReturn(null);

        $before = ['a' => 1, 'b' => 2];
        $after = ['a' => 1, 'b' => 2];

        $this->writer->write('data', 'action', 'e', '1', $before, $after);
    }

    public function testInsertReturnsFalseOnFailure(): void
    {
        $builder = $this->createMock(BaseBuilder::class);
        $builder->method('insert')->willReturn(false);

        $this->mockWriteDeps($builder);
        $this->auth->method('currentUser')->willReturn(null);

        $result = $this->writer->write('data', 'action', 'e', '1');
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

        $this->mockWriteDeps($builder);
        $this->auth->method('currentUser')->willReturn(null);

        $this->writer->write(
            'data',
            'submit',
            'e',
            '1',
            ['status' => 'Draft'],
            ['status' => 'Submitted'],
        );
    }

    public function testEmptyActionReturnsFalseWithoutWrite(): void
    {
        $this->dbc->expects($this->never())->method('query');

        $result = $this->writer->write('data', '   ');
        $this->assertFalse($result);
    }

    /**
     * Setup các dependency query/transaction cho write():
     * - transBegin, transComplete, transRollback
     * - query INSERT sys_audit_chain (upsert genesis)
     * - query SELECT last_hash ... FOR UPDATE (trả chain hash)
     * - table('sys_audit_trail') -> builder
     */
    private function mockWriteDeps(MockObject $builder): void
    {
        $chainRow = new class {
            public function getRowArray(): ?array
            {
                return ['last_hash' => 'ab' . str_repeat('0', 62)];
            }
        };

        $this->dbc->method('table')
            ->with('sys_audit_trail')
            ->willReturn($builder);

        $this->dbc->method('query')
            ->willReturnCallback(function (string $sql) use ($chainRow): BaseResult {
                $result = $this->createMock(BaseResult::class);
                if (str_starts_with($sql, 'SELECT last_hash')) {
                    $result->method('getRowArray')->willReturnCallback(
                        fn () => is_array($chainRow->getRowArray()) ? $chainRow->getRowArray() : null,
                    );
                } else {
                    $result->method('getRowArray')->willReturn([]);
                }

                return $result;
            });

        $this->dbc->method('transBegin')->willReturn(true);
        $this->dbc->method('transComplete')->willReturn(true);
        $this->dbc->method('transRollback')->willReturn(true);
    }
}
