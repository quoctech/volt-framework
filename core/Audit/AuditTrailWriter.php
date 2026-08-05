<?php

declare(strict_types=1);

namespace Volt\Core\Audit;

use CodeIgniter\Database\BaseConnection;
use Config\Volt;
use RuntimeException;
use Throwable;
use Volt\Core\Auth\Entities\UserEntity;
use Volt\Core\Auth\Services\AuthService;
use Volt\Core\Database\VoltDatabase;

/**
 * Ghi audit trail (Frappe-style activity log) vào sys_audit_trail.
 *
 * - Append-only: trigger DB chặn UPDATE/DELETE trực tiếp.
 * - Hash-chain: mỗi dòng mang prev_hash + hash để phát hiện giả mạo
 *   (verify qua command `volt:audit-verify`).
 * - Ghi đồng bộ; nếu insert thất bại thì fallback vào sys_error_log
 *   (không throw để không làm hỏng luồng nghiệp vụ).
 */
final class AuditTrailWriter
{
    public const TABLE = 'sys_audit_trail';
    public const CHAIN = 'sys_audit_chain';

    private const GENESIS_HASH = 'e78a2c1b89698ef13a10e82faa0ff73f08f025499aa81922d44222d3fbce5b59';

    // Category taxonomy (xem core/docs/audit.md)
    public const CAT_DATA       = 'data';
    public const CAT_AUTH       = 'auth';
    public const CAT_ROLE       = 'role';
    public const CAT_PERMISSION = 'permission';
    public const CAT_API        = 'api';
    public const CAT_FILE       = 'file';
    public const CAT_EXPORT     = 'export';
    public const CAT_TENANT     = 'tenant';
    public const CAT_METADATA   = 'metadata';
    public const CAT_WORKFLOW   = 'workflow';
    public const CAT_SYSTEM     = 'system';

    private const ALLOWED_CATEGORIES = [
        self::CAT_DATA, self::CAT_AUTH, self::CAT_ROLE, self::CAT_PERMISSION,
        self::CAT_API, self::CAT_FILE, self::CAT_EXPORT, self::CAT_TENANT,
        self::CAT_METADATA, self::CAT_WORKFLOW, self::CAT_SYSTEM,
    ];

    private readonly BaseConnection $db;
    private readonly AuthService $authService;

    public function __construct(
        ?BaseConnection $db = null,
        ?AuthService $authService = null,
    ) {
        $this->db = $db ?? VoltDatabase::connection();
        $this->authService = $authService ?? service('voltAuth');
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @param array<string, mixed> $context các key hỗ trợ:
     *                                    operation, status, tenant, ip_address, user_agent, request_id
     */
    public function write(
        string $category,
        string $action,
        string $entity = '',
        string $docId = '',
        array $before = [],
        array $after = [],
        ?string $changedBy = null,
        array $context = [],
        ?BaseConnection $db = null,
    ): bool {
        $db = $db ?? $this->db;
        $entity = mb_trim($entity);
        $docId = mb_trim($docId);
        $action = $this->clamp(mb_trim($action), 64);

        if ($action === '') {
            return false;
        }

        $payload = [
            'category'   => $this->normalizeCategory($category),
            'entity'     => $entity !== '' ? $this->clamp($this->normalizeEntity($entity), 100) : null,
            'doc_id'     => $docId !== '' ? $this->clamp($docId, 100) : null,
            'action'     => $action,
            'operation'  => $this->nullableString($context['operation'] ?? null, 30),
            'status'     => $this->nullableString($context['status'] ?? null, 20),
            'changed_by' => $this->clamp($changedBy ?? $this->resolveActorName(), 100),
            'changed_at' => date('Y-m-d H:i:s'),
            'tenant'     => $this->nullableString($context['tenant'] ?? $this->resolveTenant(), 100),
            'ip_address' => $this->nullableString($context['ip_address'] ?? RequestContext::ip(), 45),
            'user_agent' => $this->nullableString($context['user_agent'] ?? RequestContext::userAgent(), 255),
            'request_id' => $this->nullableString($context['request_id'] ?? RequestContext::requestId(), 64),
            'prev_hash'  => null,
            'hash'       => null,
            'delta'      => $this->encodeDelta($before, $after),
        ];

        $db->transBegin();

        try {
            $db->query(
                'INSERT INTO ' . self::CHAIN . ' (lock_key, last_hash, last_id) VALUES (1, ?, 0) ON CONFLICT (lock_key) DO NOTHING',
                [self::GENESIS_HASH],
            );

            $row = $db->query('SELECT last_hash FROM ' . self::CHAIN . ' WHERE lock_key = 1 FOR UPDATE')
                ->getRowArray();

            $payload['prev_hash'] = (is_array($row) && is_string($row['last_hash'] ?? null) && $row['last_hash'] !== '')
                ? $row['last_hash']
                : self::GENESIS_HASH;

            $inserted = $db->table(self::TABLE)->insert($payload);

            if (! $inserted) {
                $db->transRollback();

                if ($this->strictAudit()) {
                    throw new RuntimeException(
                        "Audit trail write failed (insert) for '{$action}' on {$entity}:{$docId}.",
                    );
                }

                return false;
            }

            $db->transComplete();

            return true;
        } catch (Throwable $throwable) {
            $db->transRollback();

            try {
                service('voltErrorLog')->logException(
                    $throwable,
                    ['entity' => $entity, 'doc_id' => $docId, 'action' => $action, 'category' => $category],
                    'audit',
                    'audit_write_failed',
                );
            } catch (Throwable) {
                // logException thất bại cũng không được làm hỏng luồng nghiệp vụ
            }

            if ($this->strictAudit() && ! ($throwable instanceof RuntimeException
                && str_starts_with($throwable->getMessage(), 'Audit trail write failed'))) {
                throw $throwable;
            }

            return false;
        }
    }

    private function strictAudit(): bool
    {
        try {
            return (bool) config(Volt::class)->strictAudit;
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    private function encodeDelta(array $before, array $after): string
    {
        $payload = [
            'before'  => $before,
            'after'   => $after,
            'changes' => $this->diff($before, $after),
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     *
     * @return array<string, mixed>
     */
    private function diff(array $before, array $after): array
    {
        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
        $delta = [];

        foreach ($keys as $key) {
            $beforeValue = $before[$key] ?? null;
            $afterValue = $after[$key] ?? null;

            if (is_array($beforeValue) && is_array($afterValue)) {
                $nested = $this->diff($beforeValue, $afterValue);
                if ($nested !== []) {
                    $delta[$key] = $nested;
                }

                continue;
            }

            if ($beforeValue !== $afterValue) {
                $delta[$key] = [
                    'before' => $beforeValue,
                    'after'  => $afterValue,
                ];
            }
        }

        return $delta;
    }

    private function normalizeCategory(string $category): string
    {
        $category = mb_strtolower(mb_trim($category));

        return in_array($category, self::ALLOWED_CATEGORIES, true) ? $category : self::CAT_DATA;
    }

    /**
     * Chuẩn hóa tên entity về dạng snake_case để query khớp chính xác
     * (dùng được index (entity, doc_id)) thay vì phải LOWER() khi đọc.
     * Ví dụ: "Employee" / "Employeeeducation" → "employee" / "employeeeducation".
     */
    private function normalizeEntity(string $entity): string
    {
        $entity = mb_trim($entity);

        // Đã snake_case rồi thì giữ nguyên; chỉ hạ camelCase/PascalCase.
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $entity));
    }

    private function resolveActorName(): string
    {
        $actor = $this->authService->currentUser();

        if ($actor instanceof UserEntity) {
            return (string) $actor->name;
        }

        return 'system';
    }

    private function resolveTenant(): ?string
    {
        try {
            $tenant = VoltDatabase::resolveTenant(null);

            return is_string($tenant) && $tenant !== '' ? $tenant : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function clamp(string $value, int $maxLength): string
    {
        return mb_strlen($value) > $maxLength ? substr($value, 0, $maxLength) : $value;
    }

    private function nullableString(mixed $value, int $maxLength): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        if ($value === '') {
            return null;
        }

        return $this->clamp($value, $maxLength);
    }
}
