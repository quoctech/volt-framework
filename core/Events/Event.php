<?php

declare(strict_types=1);

namespace Volt\Core\Events;

final class Event
{
    private bool $propagationStopped = false;

    public function __construct(
        private readonly string $name,
        private readonly array $payload = [],
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->payload[$key] ?? $default;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}
