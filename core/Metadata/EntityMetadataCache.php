<?php

declare(strict_types=1);

namespace Volt\Core\Metadata;

use Config\Cache;
use Predis\Client as PredisClient;
use Redis;
use Throwable;
use RuntimeException;

final class EntityMetadataCache
{
    private array $config;

    public function __construct(?array $config = null)
    {
        $cacheConfig  = config(Cache::class);
        $this->config = $config ?? $cacheConfig->redis;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function put(string $entityName, array $metadata): void
    {
        $payload = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            throw new RuntimeException('Failed to encode metadata cache payload.');
        }

        try {
            $client = $this->client();

            if ($client instanceof Redis) {
                $client->set($this->key($entityName), $payload);

                return;
            }

            $client->set($this->key($entityName), $payload);
        } catch (Throwable) {
            // Metadata cache is an optimization only; save flow must continue even if Redis is unavailable.
        }
    }

    public function delete(string $entityName): void
    {
        try {
            $client = $this->client();

            if ($client instanceof Redis) {
                $client->del($this->key($entityName));

                return;
            }

            $client->del([$this->key($entityName)]);
        } catch (Throwable) {
            // Metadata cache is an optimization only; delete flow must continue even if Redis is unavailable.
        }
    }

    private function key(string $entityName): string
    {
        return 'volt:metadata:' . strtolower($entityName);
    }

    private function client(): Redis|PredisClient
    {
        if (class_exists(Redis::class)) {
            $client = new Redis();
            $connected = $client->connect(
                (string) ($this->config['host'] ?? '127.0.0.1'),
                (int) ($this->config['port'] ?? 6379),
                max(0.5, (float) ($this->config['timeout'] ?? 0.5))
            );

            if ($connected !== true) {
                throw new RuntimeException('Unable to connect to Redis using ext-redis.');
            }

            $password = $this->config['password'] ?? null;
            if (is_string($password) && $password !== '' && $password !== '<password>') {
                $client->auth($password);
            }

            $database = (int) ($this->config['database'] ?? 0);
            if ($database > 0) {
                $client->select($database);
            }

            return $client;
        }

        if (class_exists(PredisClient::class)) {
            return new PredisClient([
                'scheme'   => 'tcp',
                'host'     => (string) ($this->config['host'] ?? '127.0.0.1'),
                'port'     => (int) ($this->config['port'] ?? 6379),
                'timeout'  => max(0.5, (float) ($this->config['timeout'] ?? 0.5)),
                'password' => $this->sanitizePassword($this->config['password'] ?? null),
                'database' => (int) ($this->config['database'] ?? 0),
            ]);
        }

        throw new RuntimeException('Redis driver is unavailable. Install ext-redis or predis/predis.');
    }

    private function sanitizePassword(mixed $password): ?string
    {
        if (! is_string($password)) {
            return null;
        }

        $password = trim($password);

        if ($password === '' || $password === '<password>') {
            return null;
        }

        return $password;
    }
}
