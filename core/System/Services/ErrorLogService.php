<?php

declare(strict_types=1);

namespace Volt\Core\System\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Volt\Core\Auth\Entities\UserEntity;
use Volt\Core\Auth\Services\AuthService;
use Volt\Core\Database\VoltDatabase;

final class ErrorLogService
{
    private const TABLE = 'sys_error_log';
    private const DEFAULT_PER_PAGE = 50;
    private const ALLOWED_PER_PAGE = [20, 50, 100, 200];

    private readonly BaseConnection $db;
    private readonly AuthService $authService;
    private readonly RequestInterface $request;
    private readonly LoggerInterface $logger;

    public function __construct(
        ?BaseConnection $db = null,
        ?AuthService $authService = null,
        ?RequestInterface $request = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->db = $db ?? VoltDatabase::connection();
        $this->authService = $authService ?? service('voltAuth');
        $this->request = $request ?? service('request');
        $this->logger = $logger ?? service('logger');
    }

    /**
     * @param array<string, mixed> $context
     */
    public function write(string $level, string $message, array $context = [], ?string $channel = 'system', ?string $code = null): bool
    {
        $payload = [
            'level' => $this->normalizeLevel($level),
            'channel' => $this->normalizeChannel($channel),
            'code' => $this->normalizeNullableString($code, 100),
            'message' => mb_trim($message) !== '' ? mb_trim($message) : 'Unknown system error',
            'context' => $this->encodeContext($context),
            'file' => $this->normalizeNullableText($context['file'] ?? null),
            'line' => $this->normalizeNullableInt($context['line'] ?? null),
            'trace' => $this->normalizeNullableText($context['trace'] ?? null),
            'request_uri' => $this->resolveRequestUri(),
            'request_method' => $this->resolveRequestMethod(),
            'ip_address' => $this->resolveIpAddress(),
            'user_agent' => $this->resolveUserAgent(),
            'actor' => $this->resolveActor(),
        ];

        try {
            return (bool) $this->db->table(self::TABLE)->insert($payload);
        } catch (Throwable $throwable) {
            $this->logger->error('Failed to persist sys_error_log entry: {message}', [
                'message' => $throwable->getMessage(),
                'original_level' => $level,
                'original_channel' => $channel,
            ]);

            return false;
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    public function logException(Throwable $throwable, array $context = [], ?string $channel = 'system', ?string $code = null): bool
    {
        $context['exception_class'] = $throwable::class;
        $context['file'] = $throwable->getFile();
        $context['line'] = $throwable->getLine();
        $context['trace'] = $throwable->getTraceAsString();

        return $this->write(
            level: 'error',
            message: $throwable->getMessage(),
            context: $context,
            channel: $channel,
            code: $code ?? (string) $throwable->getCode()
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{
     *   rows:list<array<string, mixed>>,
     *   meta:array<string, mixed>,
     *   filters:array<string, mixed>,
     *   summary:array<string, int>
     * }
     */
    public function listLogs(array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = (int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE);
        $perPage = in_array($perPage, self::ALLOWED_PER_PAGE, true) ? $perPage : self::DEFAULT_PER_PAGE;
        $level = $this->normalizeFilterString($filters['level'] ?? '');
        $channel = $this->normalizeFilterString($filters['channel'] ?? '');
        $query = mb_trim((string) ($filters['q'] ?? ''));

        $builder = $this->db->table(self::TABLE)
            ->select('id, level, channel, code, message, context, file, line, trace, request_uri, request_method, ip_address, user_agent, actor, created_at')
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC');

        if ($level !== '') {
            $builder->where('level', $level);
        }

        if ($channel !== '') {
            $builder->where('channel', $channel);
        }

        if ($query !== '') {
            $builder->groupStart()
                ->like('message', $query)
                ->orLike('channel', $query)
                ->orLike('code', $query)
                ->orLike('actor', $query)
                ->orLike('request_uri', $query)
                ->groupEnd();
        }

        $countBuilder = clone $builder;
        $total = (int) $countBuilder->countAllResults();
        $rows = $builder
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        return [
            'rows' => array_map(fn (array $row): array => $this->hydrateRow($row), $rows),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => max(1, (int) ceil($total / $perPage)),
                'per_page_options' => self::ALLOWED_PER_PAGE,
            ],
            'filters' => [
                'level' => $level,
                'channel' => $channel,
                'q' => $query,
            ],
            'summary' => $this->summarizeLevels(),
        ];
    }

    /**
     * @return list<string>
     */
    public function listChannels(): array
    {
        $rows = $this->db->table(self::TABLE)
            ->select('channel')
            ->groupBy('channel')
            ->orderBy('channel', 'ASC')
            ->get()
            ->getResultArray();

        return array_values(array_filter(array_map(
            static fn (array $row): string => mb_trim((string) ($row['channel'] ?? '')),
            $rows
        )));
    }

    private function normalizeLevel(string $level): string
    {
        $level = mb_strtolower(mb_trim($level));

        return in_array($level, ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'], true)
            ? $level
            : 'error';
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrateRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'level' => (string) ($row['level'] ?? 'error'),
            'channel' => (string) ($row['channel'] ?? 'system'),
            'code' => (string) ($row['code'] ?? ''),
            'message' => (string) ($row['message'] ?? ''),
            'context' => $this->decodeJsonObject($row['context'] ?? '{}'),
            'context_text' => $this->prettyJson($this->decodeJsonObject($row['context'] ?? '{}')),
            'file' => (string) ($row['file'] ?? ''),
            'line' => isset($row['line']) ? (int) $row['line'] : null,
            'trace' => (string) ($row['trace'] ?? ''),
            'request_uri' => (string) ($row['request_uri'] ?? ''),
            'request_method' => (string) ($row['request_method'] ?? ''),
            'ip_address' => (string) ($row['ip_address'] ?? ''),
            'user_agent' => (string) ($row['user_agent'] ?? ''),
            'actor' => (string) ($row['actor'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function summarizeLevels(): array
    {
        $rows = $this->db->table(self::TABLE)
            ->select('level, COUNT(*) AS total')
            ->groupBy('level')
            ->get()
            ->getResultArray();

        $summary = [
            'total' => 0,
            'error' => 0,
            'warning' => 0,
            'info' => 0,
        ];

        foreach ($rows as $row) {
            $level = (string) ($row['level'] ?? '');
            $count = (int) ($row['total'] ?? 0);
            $summary['total'] += $count;
            if (isset($summary[$level])) {
                $summary[$level] += $count;
            }
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(mixed $value): array
    {
        if (is_array($value)) {
            return array_is_list($value) ? [] : $value;
        }

        if (! is_string($value) || mb_trim($value) === '') {
            return [];
        }

        $decoded = json_validate($value) ? json_decode($value, true) : null;

        return is_array($decoded) && ! array_is_list($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $value
     */
    private function prettyJson(array $value): string
    {
        $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded !== false ? $encoded : '{}';
    }

    private function normalizeFilterString(mixed $value): string
    {
        return mb_strtolower(mb_trim((string) $value));
    }

    private function normalizeChannel(?string $channel): string
    {
        $channel = mb_strtolower(mb_trim((string) $channel));
        $channel = preg_replace('/[^a-z0-9_\-\.]+/', '_', $channel) ?? '';

        return $channel !== '' ? substr($channel, 0, 100) : 'system';
    }

    /**
     * @param array<string, mixed> $context
     */
    private function encodeContext(array $context): string
    {
        unset($context['file'], $context['line'], $context['trace']);

        $payload = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $payload !== false ? $payload : '{}';
    }

    private function resolveRequestUri(): ?string
    {
        if ($this->request instanceof IncomingRequest) {
            return method_exists($this->request, 'getUri')
                ? $this->normalizeNullableText((string) $this->request->getUri())
                : $this->normalizeNullableText($this->request->getPath());
        }

        if ($this->request instanceof CLIRequest) {
            return 'cli://' . implode(' ', $_SERVER['argv'] ?? []);
        }

        return null;
    }

    private function resolveRequestMethod(): ?string
    {
        if ($this->request instanceof CLIRequest) {
            return 'CLI';
        }

        return method_exists($this->request, 'getMethod')
            ? strtoupper((string) $this->request->getMethod())
            : null;
    }

    private function resolveIpAddress(): ?string
    {
        if ($this->request instanceof IncomingRequest) {
            return $this->normalizeNullableString($this->request->getIPAddress(), 64);
        }

        return null;
    }

    private function resolveUserAgent(): ?string
    {
        if ($this->request instanceof IncomingRequest) {
            return $this->normalizeNullableText((string) $this->request->getUserAgent());
        }

        return null;
    }

    private function resolveActor(): ?string
    {
        $actor = $this->authService->currentUser();

        if ($actor instanceof UserEntity) {
            return $this->normalizeNullableString((string) $actor->name, 100);
        }

        return $this->request instanceof CLIRequest ? 'cli' : 'system';
    }

    private function normalizeNullableString(?string $value, int $maxLength): ?string
    {
        $value = mb_trim((string) $value);

        return $value !== '' ? substr($value, 0, $maxLength) : null;
    }

    private function normalizeNullableText(mixed $value): ?string
    {
        $value = mb_trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
