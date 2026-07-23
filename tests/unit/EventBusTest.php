<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use Volt\Core\Events\Event;
use Volt\Core\Events\EventBus;

/**
 * @internal
 */
final class EventBusTest extends CIUnitTestCase
{
    private EventBus $bus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bus = new EventBus();
    }

    public function testDispatchCallsRegisteredListener(): void
    {
        $called = false;
        $this->bus->listen('user.created', function (Event $e) use (&$called): void {
            $called = true;
            $this->assertSame('user.created', $e->getName());
        });

        $this->bus->dispatch(new Event('user.created'));
        $this->assertTrue($called);
    }

    public function testDispatchPassesPayload(): void
    {
        $result = null;
        $this->bus->listen('entity.saved', function (Event $e) use (&$result): void {
            $result = $e->get('entity_name');
        });

        $this->bus->dispatch(new Event('entity.saved', ['entity_name' => 'leave', 'id' => 42]));
        $this->assertSame('leave', $result);
    }

    public function testDispatchCallsMultipleListeners(): void
    {
        $order = [];
        $this->bus->listen('test', function () use (&$order): void { $order[] = 'a'; });
        $this->bus->listen('test', function () use (&$order): void { $order[] = 'b'; });

        $this->bus->dispatch(new Event('test'));
        $this->assertSame(['a', 'b'], $order);
    }

    public function testStopPropagationPreventsSubsequentListeners(): void
    {
        $order = [];
        $this->bus->listen('test', function (Event $e) use (&$order): void {
            $order[] = 'a';
            $e->stopPropagation();
        });
        $this->bus->listen('test', function () use (&$order): void { $order[] = 'b'; });

        $this->bus->dispatch(new Event('test'));
        $this->assertSame(['a'], $order);
    }

    public function testWildcardListenerReceivesAllEvents(): void
    {
        $events = [];
        $this->bus->listen('*', function (Event $e) use (&$events): void {
            $events[] = $e->getName();
        });
        $this->bus->listen('user.created', function (Event $e) use (&$events): void {
            $events[] = 'specific';
        });

        $this->bus->dispatch(new Event('user.created'));
        $this->bus->dispatch(new Event('user.deleted'));

        $this->assertSame(['specific', 'user.created', 'user.deleted'], $events);
    }

    public function testDispatchWithNoListenersDoesNothing(): void
    {
        $this->bus->dispatch(new Event('ghost'));
        $this->expectNotToPerformAssertions();
    }

    public function testListenWithEmptyNameThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->bus->listen('', function (): void {});
    }

    public function testRemoveListenersRemovesSpecificEvent(): void
    {
        $called = false;
        $this->bus->listen('test', function () use (&$called): void { $called = true; });
        $this->bus->removeListeners('test');

        $this->bus->dispatch(new Event('test'));
        $this->assertFalse($called);
    }

    public function testRemoveListenersRemovesAll(): void
    {
        $called = false;
        $this->bus->listen('a', function () use (&$called): void { $called = true; });
        $this->bus->listen('b', function () use (&$called): void { $called = true; });
        $this->bus->removeListeners();

        $this->bus->dispatch(new Event('a'));
        $this->bus->dispatch(new Event('b'));
        $this->assertFalse($called);
    }

    public function testHasListeners(): void
    {
        $this->assertFalse($this->bus->hasListeners('test'));

        $this->bus->listen('test', function (): void {});
        $this->assertTrue($this->bus->hasListeners('test'));
    }

    public function testGetListeners(): void
    {
        $fn = function (): void {};
        $this->bus->listen('test', $fn);

        $listeners = $this->bus->getListeners('test');
        $this->assertCount(1, $listeners);
        $this->assertSame($fn, $listeners[0]);

        $this->assertCount(0, $this->bus->getListeners('other'));
    }

    public function testGetAllListeners(): void
    {
        $fn = function (): void {};
        $this->bus->listen('a', $fn);
        $this->bus->listen('b', $fn);

        $all = $this->bus->getListeners();
        $this->assertCount(2, $all);
    }
}
