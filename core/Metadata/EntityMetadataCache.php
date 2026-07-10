<?php

declare(strict_types=1);

namespace Volt\Core\Metadata;

use Config\Cache;
use Predis\Client as PredisClient;
use Redis;
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

        $client = $this->client();

        if ($client instanceof Redis) {
            $client->set($this->key($entityName), $payload);

            return;
        }

        $client->set($this->key($entityName), $payload);
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
                (float) ($this->config['timeout'] ?? 0)
            );

            if ($connected !== true) {
                throw new RuntimeException('Unable to connect to Redis using ext-redis.');
            }

            $password = $this->config['password'] ?? null;
            if (is_string($password) && $password !== '') {
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
                'timeout'  => (float) ($this->config['timeout'] ?? 0),
                'password' => $this->config['password'] ?? null,
                'database' => (int) ($this->config['database'] ?? 0),
            ]);
        }

        throw new RuntimeException('Redis driver is unavailable. Install ext-redis or predis/predis.');
    }
}
