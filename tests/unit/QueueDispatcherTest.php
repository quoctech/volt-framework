<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Volt\Core\Engine\QueueDispatcher;
use Volt\Core\Models\QueueJobModel;

/**
 * @internal
 */
final class QueueDispatcherTest extends CIUnitTestCase
{
    private MockObject&QueueJobModel $model;
    private QueueDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->model = $this->createMock(QueueJobModel::class);
        $this->dispatcher = new QueueDispatcher($this->model);
    }

    public function testDispatchCallsModelAndReturnsId(): void
    {
        $this->model->expects($this->once())
            ->method('dispatch')
            ->with(
                'send_email',
                $this->callback(static fn (array $payload): bool => $payload['to'] === 'a@b.c'),
                $this->callback(static fn (array $opts): bool => ($opts['queue'] ?? '') === 'default' && ($opts['timeout'] ?? 0) > 0)
            )
            ->willReturn(7);

        $id = $this->dispatcher->dispatch('send_email', ['to' => 'a@b.c']);

        $this->assertSame(7, $id);
    }

    public function testDispatchAppliesDefaultQueueAndTimeout(): void
    {
        $this->model->expects($this->once())->method('dispatch')->with(
            'report.run',
            ['report' => 'sales'],
            $this->callback(static fn (array $opts): bool => ($opts['queue'] ?? '') === 'default')
        )->willReturn(1);

        $id = $this->dispatcher->dispatch('report.run', ['report' => 'sales']);

        $this->assertSame(1, $id);
    }

    public function testDispatchRejectsInvalidJobType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->dispatcher->dispatch('invalid job type!');
    }
}
