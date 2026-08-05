<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Session\Handlers\RedisHandler;

class Session extends BaseConfig
{
    public string $driver = RedisHandler::class;

    public string $cookieName = 'volt_session';

    public int $expiration = 7200;

    public string $savePath = '';

    public bool $matchIP = false;

    public int $timeToUpdate = 300;

    public bool $regenerateDestroy = true;

    public ?string $DBGroup = null;

    public int $lockRetryInterval = 100_000;

    public int $lockMaxRetries = 300;

    public function __construct()
    {
        parent::__construct();

        $host = (string) env('session.redis.host', (string) env('cache.redis.host', '127.0.0.1'));
        $port = (string) env('session.redis.port', (string) env('cache.redis.port', '6379'));
        $password = (string) env('session.redis.password', (string) env('cache.redis.password', ''));
        $database = (string) env('session.redis.database', '2');

        $dsn = "tcp://{$host}:{$port}";
        $params = [];
        if ($password !== '') {
            $params[] = 'auth=' . rawurlencode($password);
        }
        $params[] = 'database=' . ((int) $database);
        $this->savePath = $dsn . '?' . implode('&', $params);
    }
}
