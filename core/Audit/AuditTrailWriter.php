<?php

declare(strict_types=1);

namespace Volt\Core\Audit;

use CodeIgniter\Database\BaseConnection;
use Config\Services;
use Volt\Core\Auth\Entities\UserEntity;
use Volt\Core\Auth\Services\AuthService;
use Volt\Core\Database\VoltDatabase;

final class AuditTrailWriter
{
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
     */
    public function write(string $entity, string $docId, string $action, array $before = [], array $after = [], ?string $changedBy = null): bool
    {
        $payload = [
            'entity' => $entity,
            'doc_id' => $docId,
            'action' => $action,
            'changed_by' => $changedBy ?? $this->resolveActorName(),
            'delta' => $this->encodeDelta($before, $after),
        ];

        return (bool) $this->db->table('sys_audit_trail')->insert($payload);
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    private function encodeDelta(array $before, array $after): string
    {
        $changes = $this->diff($before, $after);

        return json_encode([
            'before' => $before,
            'after' => $after,
            'changes' => $changes,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
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
                    'after' => $afterValue,
                ];
            }
        }

        return $delta;
    }

    private function resolveActorName(): string
    {
        $actor = $this->authService->currentUser();

        if ($actor instanceof UserEntity) {
            return (string) $actor->name;
        }

        return 'system';
    }
}
