<?php

declare(strict_types=1);

namespace Volt\Core\Audit;

use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;

/**
 * Cung cấp ngữ cảnh truy vết (correlation) cho audit & error log.
 *
 * request_id được sinh hoặc đọc từ header X-Request-ID bởi CorrelationFilter,
 * sau đó được tái sử dụng cho toàn bộ log trong cùng một request để truy vết chéo.
 */
final class RequestContext
{
    private const REQUEST_ID_MAX_LENGTH = 64;

    private static ?string $requestId = null;

    public static function setRequestId(?string $requestId): void
    {
        $normalized = self::normalize($requestId);

        if ($normalized !== null) {
            self::$requestId = $normalized;
        }
    }

    public static function requestId(): ?string
    {
        if (self::$requestId === null) {
            self::$requestId = self::generate();
        }

        return self::$requestId;
    }

    /**
     * @internal dùng trong CLI commands
     */
    public static function fresh(): string
    {
        self::$requestId = null;

        return self::requestId();
    }

    public static function reset(): void
    {
        self::$requestId = null;
    }

    /**
     * @param mixed $value
     */
    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        if (mb_strlen($value) === 0) {
            return null;
        }

        if (mb_strlen($value) > self::REQUEST_ID_MAX_LENGTH) {
            $value = substr($value, 0, self::REQUEST_ID_MAX_LENGTH);
        }

        if (! preg_match('/^[a-zA-Z0-9\-_\.:]+$/', $value)) {
            return null;
        }

        return $value;
    }

    public static function ip(): ?string
    {
        $request = self::request();

        if ($request instanceof IncomingRequest) {
            $ip = $request->getIPAddress();

            return is_string($ip) && $ip !== '' ? $ip : null;
        }

        return null;
    }

    public static function userAgent(): ?string
    {
        $request = self::request();

        if ($request instanceof IncomingRequest) {
            $agent = (string) $request->getUserAgent();

            return $agent !== '' ? substr($agent, 0, 255) : null;
        }

        return null;
    }

    private static function request(): ?RequestInterface
    {
        if (! function_exists('service')) {
            return null;
        }

        try {
            $request = service('request');
        } catch (\Throwable) {
            return null;
        }

        return $request instanceof CLIRequest ? null : $request;
    }

    private static function generate(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}