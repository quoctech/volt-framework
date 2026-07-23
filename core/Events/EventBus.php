<?php

declare(strict_types=1);

namespace Volt\Core\Events;

use Closure;
use InvalidArgumentException;

final class EventBus
{
    private const WILDCARD = '*';

    /** @var array<string, list<callable>> */
    private array $listeners = [];

    public function listen(string $event, callable $listener): void
    {
        if ($event === '') {
            throw new InvalidArgumentException('Event name must not be empty.');
        }

        $this->listeners[$event][] = $listener;
    }

    public function dispatch(Event $event): void
    {
        $name = $event->getName();

        foreach ($this->listenersFor($name) as $listener) {
            if ($event->isPropagationStopped()) {
                break;
            }

            $listener($event);
        }
    }

    public function removeListeners(?string $event = null): void
    {
        if ($event === null) {
            $this->listeners = [];

            return;
        }

        unset($this->listeners[$event]);
    }

    /** @return list<callable> */
    public function getListeners(?string $event = null): array
    {
        if ($event === null) {
            $all = [];

            foreach ($this->listeners as $name => $listeners) {
                foreach ($listeners as $listener) {
                    $all[] = ['event' => $name, 'listener' => $listener];
                }
            }

            return $all;
        }

        return $this->listeners[$event] ?? [];
    }

    public function hasListeners(string $event): bool
    {
        return isset($this->listeners[$event][0]);
    }

    /** @return list<callable> */
    private function listenersFor(string $name): array
    {
        $direct = $this->listeners[$name] ?? [];
        $wildcard = $this->listeners[self::WILDCARD] ?? [];

        if ($direct === [] && $wildcard === []) {
            return [];
        }

        return [...$direct, ...$wildcard];
    }
}
