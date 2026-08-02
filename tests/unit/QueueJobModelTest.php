<?php

declare(strict_types=1);

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseResult;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Volt\Core\Models\QueueJobModel;

/**
 * @internal
 */
final class QueueJobModelTest extends CIUnitTestCase
{
    private MockObject&BaseConnection $dbc;
    private QueueJobModel $model;

    /** @var list<array{string, array<int, string>}> */
    private array $queries = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->queries = [];
        $this->dbc = $this->createMock(BaseConnection::class);
        $this->model = new QueueJobModel($this->dbc);
    }

    private function captureQueryResults(): void
    {
        $self = $this;
        $this->dbc->method('query')->willReturnCallback(
            function (string $sql, ...$args) use ($self): ?BaseResult {
                $binds = $args[0] ?? [];
                $self->queries[] = [$sql, is_array($binds) ? array_values($binds) : []];

                return null;
            },
        );
    }

    public function testClaimNextJobReturnsNullWhenNoJobs(): void
    {
        $result = $this->createMock(BaseResult::class);
        $result->method('getNumRows')->willReturn(0);

        $this->dbc->method('query')->willReturn($result);

        $this->assertNull($this->model->claimNextJob());
    }

    public function testClaimNextJobBuildsAtomicUpdateReturning(): void
    {
        $this->givenClaimedRow([
            'id'         => 1,
            'job_type'   => 'send_email',
            'payload'    => '{"to":"a@b.c"}',
            'attempts'   => 1,
            'status'     => 'running',
            'queue'      => 'default',
            'available_at' => '2026-08-02 00:00:00',
            'timeout'    => 60,
        ]);

        $job = $this->model->claimNextJob();

        $this->assertIsArray($job);
        $this->assertSame('send_email', $job['job_type']);

        [$sql] = $this->queries[0];
        $this->assertStringContainsString('UPDATE sys_queue_job', $sql);
        $this->assertStringContainsString('RETURNING *', $sql);
        $this->assertStringContainsString("WHERE status = 'queued'", $sql);
        $this->assertStringNotContainsString('queue = ?', $sql);
    }

    public function testClaimNextJobFiltersByQueue(): void
    {
        $this->givenClaimedRow(['id' => 2, 'job_type' => 'send_email', 'payload' => '{}', 'attempts' => 1, 'queue' => 'emails']);

        $this->model->claimNextJob('emails');

        [$sql, $binds] = $this->queries[0];
        $this->assertStringContainsString('queue = ?', $sql);
        $this->assertSame(['emails'], $binds);
    }

    public function testRequeueStaleJobsUsesIntervalAndReturnsAffectedRows(): void
    {
        $this->captureQueryResults();
        $this->dbc->method('affectedRows')->willReturn(3);

        $count = $this->model->requeueStaleJobs(300);

        $this->assertSame(3, $count);
        [$sql, $binds] = $this->queries[0];
        $this->assertStringContainsString('INTERVAL', $sql);
        $this->assertSame([300], $binds);
    }

    public function testPurgeDeadUsesIntervalAndReturnsAffectedRows(): void
    {
        $this->captureQueryResults();
        $this->dbc->method('affectedRows')->willReturn(5);

        $count = $this->model->purgeDead(30);

        $this->assertSame(5, $count);
        [$sql, $binds] = $this->queries[0];
        $this->assertStringContainsString('DELETE FROM sys_queue_job', $sql);
        $this->assertStringContainsString("status = 'dead'", $sql);
        $this->assertSame([30], $binds);
    }

    public function testCountsGroupsByStatus(): void
    {
        $builder = $this->createMock(BaseBuilder::class);
        $builder->method('select')->willReturnSelf();
        $builder->method('groupBy')->willReturnSelf();

        $result = $this->createMock(BaseResult::class);
        $result->method('getResultArray')->willReturn([
            ['status' => 'queued', 'total' => '4'],
            ['status' => 'dead', 'total' => '1'],
        ]);
        $builder->method('get')->willReturn($result);

        $this->dbc->method('table')->willReturn($builder);

        $this->assertSame(['queued' => 4, 'dead' => 1], $this->model->counts());
    }

    private function givenClaimedRow(array $row): void
    {
        $result = $this->createMock(BaseResult::class);
        $result->method('getNumRows')->willReturn(1);
        $result->method('getRowArray')->willReturn($row);

        $self = $this;
        $this->dbc->method('query')->willReturnCallback(
            function (string $sql, ...$args) use ($self, $result): BaseResult {
                $binds = $args[0] ?? [];
                $self->queries[] = [$sql, is_array($binds) ? array_values($binds) : []];

                return $result;
            },
        );
    }
}
