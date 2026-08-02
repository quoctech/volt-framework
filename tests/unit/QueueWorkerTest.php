<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Volt\Core\Engine\QueueWorker;
use Volt\Core\Models\QueueJobModel;

/**
 * @internal
 */
final class QueueWorkerTest extends CIUnitTestCase
{
    private MockObject&QueueJobModel $model;
    private QueueWorker $worker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->model = $this->createMock(QueueJobModel::class);
        $this->worker = new QueueWorker($this->model);
    }

    public function testProcessNextReturnsFalseWhenNoJobClaimed(): void
    {
        $this->model->method('claimNextJob')->willReturn(null);

        $this->assertFalse($this->worker->processNext());
    }

    public function testProcessNextRunsHandlerAndMarksCompleted(): void
    {
        $job = $this->job(['id' => 1, 'job_type' => 'greet', 'payload' => '{"name":"A"}']);
        $this->model->method('claimNextJob')->willReturn($job);

        $ran = '';
        $this->worker->registerHandler('greet', static function (array $payload) use (&$ran): void {
            $ran = (string) $payload['name'];
        });

        $this->model->expects($this->once())->method('markCompleted')->with(1);

        $this->assertTrue($this->worker->processNext());
        $this->assertSame('A', $ran);
    }

    public function testProcessNextRetriesUntilMaxAttemptsThenDead(): void
    {
        $maxAttempts = 3;

        foreach ([1, 2, 3] as $attempt) {
            $model = $this->createMock(QueueJobModel::class);
            $worker = new QueueWorker($model);

            $model->method('claimNextJob')->willReturn($this->job([
                'id' => 10,
                'job_type' => 'flaky',
                'payload' => '{}',
                'attempts' => $attempt,
            ]));

            $worker->registerHandler('flaky', static function (): void {
                throw new RuntimeException('boom');
            });

            if ($attempt < $maxAttempts) {
                $model->expects($this->once())
                    ->method('scheduleRetry')
                    ->with(10, 'boom', $this->matchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/'));
                $model->expects($this->never())->method('markDead');
            } else {
                $model->expects($this->once())->method('markDead')->with(10, 'boom');
                $model->expects($this->never())->method('scheduleRetry');
            }

            $this->assertTrue($worker->processNext());
        }
    }

    public function testProcessNextTreatsMissingHandlerAsFailure(): void
    {
        $job = $this->job(['id' => 5, 'job_type' => 'unknown', 'payload' => '{}', 'attempts' => 1]);
        $this->model->method('claimNextJob')->willReturn($job);

        $this->model->expects($this->once())->method('scheduleRetry');

        $this->assertTrue($this->worker->processNext());
    }

    public function testProcessAllStopsAfterMaxJobs(): void
    {
        $job = $this->job(['id' => 1, 'job_type' => 'ok', 'payload' => '{}']);
        $this->model->method('claimNextJob')->willReturn($job);
        $this->worker->registerHandler('ok', static function (): void {
        });

        $count = $this->worker->processAll(maxJobs: 2);

        $this->assertSame(2, $count);
    }

    public function testBackoffDelayGrowsExponentially(): void
    {
        // attempts=1 -> delay = base * 2^0 = base (default config base 5 -> 5s)
        $this->model->method('claimNextJob')->willReturn($this->job(['id' => 1, 'job_type' => 'flaky', 'payload' => '{}', 'attempts' => 1]));
        $this->worker->registerHandler('flaky', static function (): void {
            throw new RuntimeException('boom');
        });

        $expected = date('Y-m-d H:i:s', time() + 5);
        $this->model->expects($this->once())->method('scheduleRetry')->with(1, 'boom', $this->callback(
            static fn (string $availableAt): bool => abs(strtotime($availableAt) - strtotime($expected)) <= 2
        ));

        $this->worker->processNext();
    }

    /** @param array<string, mixed> $overrides */
    private function job(array $overrides = []): array
    {
        return array_merge([
            'id'         => 1,
            'job_type'   => 'ok',
            'payload'    => '{}',
            'status'     => 'running',
            'attempts'   => 1,
            'queue'      => 'default',
            'available_at' => '2026-08-02 00:00:00',
            'timeout'    => 60,
        ], $overrides);
    }
}
