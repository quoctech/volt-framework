<?php

declare(strict_types=1);

namespace Volt\Core\System\Services;

use Config\Volt;
use Throwable;

/**
 * Gửi alert qua generic webhook khi có lỗi nghiêm trọng.
 *
 * - Bật bằng cách set `volt.alertWebhookUrl` trong .env.
 * - Ký payload bằng HMAC-SHA256 (nếu set `volt.alertWebhookSecret`).
 * - Không làm chậm/thất bại request chính (fire-and-forget).
 */
final class AlertService
{
    private const LEVEL_ORDER = [
        'debug' => 0,
        'info' => 1,
        'notice' => 2,
        'warning' => 3,
        'error' => 4,
        'critical' => 5,
        'alert' => 6,
        'emergency' => 7,
    ];

    private string $webhookUrl;
    private string $secret;
    private string $minLevel;

    public function __construct(?Volt $volt = null)
    {
        $volt ??= config(Volt::class);

        $this->webhookUrl = mb_trim((string) ($volt->alertWebhookUrl ?? ''));
        $this->secret = (string) ($volt->alertWebhookSecret ?? '');
        $this->minLevel = mb_strtolower(mb_trim((string) ($volt->alertMinLevel ?? 'error'))) ?: 'error';
    }

    public function enabled(): bool
    {
        return $this->webhookUrl !== ''
            && $this->webhookUrl !== 'null'
            && filter_var($this->webhookUrl, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function send(string $level, string $title, string $message, array $payload = []): bool
    {
        if (! $this->enabled() || ! $this->exceedsThreshold($level)) {
            return false;
        }

        $body = [
            'level'   => $level,
            'title'   => $title,
            'message' => $message,
            'host'    => gethostname(),
            'app'     => 'volt',
            'environment' => ENVIRONMENT,
            'time'    => gmdate('c'),
            'payload' => $payload,
        ];

        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        $headers = [
            'Content-Type: application/json',
            'User-Agent: Volt-Alert/1.0',
        ];

        if ($this->secret !== '') {
            $headers[] = 'X-Volt-Signature: ' . hash_hmac('sha256', $json, $this->secret);
        }

        return $this->fireAndForget($this->webhookUrl, $json, $headers);
    }

    private function exceedsThreshold(string $level): bool
    {
        $levelScore = self::LEVEL_ORDER[$level] ?? self::LEVEL_ORDER['error'];
        $minScore = self::LEVEL_ORDER[$this->minLevel] ?? self::LEVEL_ORDER['error'];

        return $levelScore >= $minScore;
    }

    /**
     * Fire-and-forget HTTP POST (không chờ response, không ném exception).
     *
     * @param list<string> $headers
     */
    private function fireAndForget(string $url, string $json, array $headers): bool
    {
        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $json,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 3,
                CURLOPT_CONNECTTIMEOUT => 2,
            ]);

            // Chạy đồng bộ nhưng timeout ngắn để tránh làm chậm request.
            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $response !== false && $httpCode >= 200 && $httpCode < 300;
        } catch (Throwable) {
            return false;
        }
    }
}