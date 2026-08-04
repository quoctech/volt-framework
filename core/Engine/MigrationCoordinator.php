<?php

declare(strict_types=1);

namespace Volt\Core\Engine;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\RawSql;
use Throwable;
use Volt\Core\Database\VoltDatabase;

/**
 * Điều phối luồng migration: plan → preview → approval → apply → rollback.
 *
 * Vai trò chính: ngăn Entity Builder (hoặc tool khác) tự ý thay đổi schema
 * production mà không có plan/preview/approval.
 *
 * - Operation "an toàn" (add column, create table, widen): apply ngay.
 * - Operation "phá vỡ" (đổi kiểu, xóa cột, drop index/constraint): tạo migration
 *   request ở trạng thái pending_approval, chờ approve rồi mới apply.
 * - Mỗi request lưu toàn bộ plan (ops + opts) trong sys_migration_request để
 *   apply/rollback dùng lại; sys_schema_migration là nhật ký audit từng op.
 */
final class MigrationCoordinator
{
    private const TABLE_REQUEST = 'sys_migration_request';
    private const TABLE_LOG = 'sys_schema_migration';

    private readonly BaseConnection $db;
    private readonly SchemaSync $sync;

    public function __construct(?BaseConnection $db = null, ?SchemaSync $sync = null)
    {
        $this->db = $db ?? VoltDatabase::connection();
        $this->sync = $sync ?? new SchemaSync($this->db);
    }

    /**
     * Tính plan (dry-run) cho entity — dùng cho preview trước khi commit thay đổi.
     *
     * @param array<string, mixed> $opts
     *
     * @return array<string, mixed>
     */
    public function preview(string $entityName, array $opts = []): array
    {
        $plan = $this->sync->planEntity($entityName, $opts);
        $plan['summary'] = $this->summarize($plan['plan'] ?? []);

        return $plan;
    }

    /**
     * Tạo migration request từ metadata hiện tại của entity.
     *
     * Safe ops được apply ngay; breaking ops được giữ lại chờ approval (nếu
     * cấu hình yêu cầu).
     *
     * @param array<string, mixed> $opts
     *
     * @return array<string, mixed>
     */
    public function request(string $entityName, string $requestedBy = 'system', array $opts = []): array
    {
        $plan = $this->sync->planEntity($entityName, $opts);
        if (($plan['status'] ?? '') !== 'success') {
            return $plan;
        }

        $ops = $plan['plan'] ?? [];
        if ($ops === []) {
            return [
                'status'         => 'success',
                'message'        => 'Không có thay đổi schema.',
                'migration'      => null,
                'safe_migration' => null,
                'plan'           => [],
                'summary'        => $this->summarize([]),
                'needs_approval' => false,
            ];
        }

        $safeOps = array_values(array_filter($ops, static fn (array $o): bool => ($o['severity'] ?? '') !== 'breaking'));
        $breakingOps = array_values(array_filter($ops, static fn (array $o): bool => ($o['severity'] ?? '') === 'breaking'));

        $needsApproval = $breakingOps !== [] && $this->sync->requiresApprovalForBreaking();

        $safeMigration = null;
        if ($safeOps !== []) {
            $safeMigration = $this->createAndApply($entityName, $safeOps, $requestedBy, $opts);
        }

        $pendingMigration = null;
        if ($breakingOps !== []) {
            if ($needsApproval) {
                $pendingMigration = $this->createRequest($entityName, $breakingOps, $requestedBy, $opts, 'pending_approval');
            } else {
                $pendingMigration = $this->createAndApply($entityName, $breakingOps, $requestedBy, $opts);
            }
        }

        return [
            'status'         => 'success',
            'message'        => $needsApproval ? 'Một số thay đổi phá vỡ cần được duyệt.' : null,
            'migration'      => $pendingMigration,
            'safe_migration' => $safeMigration,
            'plan'           => $ops,
            'summary'        => $this->summarize($ops),
            'needs_approval' => $needsApproval && $pendingMigration !== null,
        ];
    }

    /**
     * Duyệt một migration request đang chờ.
     *
     * @return array<string, mixed>
     */
    public function approve(int $id, string $approvedBy = 'system'): array
    {
        $request = $this->find($id);
        if ($request === null) {
            return $this->result('error', "Migration #{$id} không tồn tại.");
        }

        if (($request['status'] ?? '') !== 'pending_approval') {
            return $this->result('error', "Migration #{$id} không ở trạng thái pending_approval.", ['request' => $request]);
        }

        $now = new RawSql('CURRENT_TIMESTAMP');
        $this->db->table(self::TABLE_REQUEST)->where('id', $id)->update([
            'status'      => 'approved',
            'approved_by' => $approvedBy,
            'approved_at' => $now,
            'updated_at'  => $now,
        ]);
        $this->updateLogStatus($id, 'pending_approval', 'approved');

        return $this->result('success', "Migration #{$id} đã được duyệt.", ['request' => $this->find($id)]);
    }

    /**
     * Áp dụng các op của migration request đã được duyệt.
     *
     * @return array<string, mixed>
     */
    public function apply(int $id, string $appliedBy = 'system'): array
    {
        $request = $this->find($id);
        if ($request === null) {
            return $this->result('error', "Migration #{$id} không tồn tại.");
        }

        $status = (string) ($request['status'] ?? '');
        if ($status !== 'approved') {
            return $this->result('error', "Migration #{$id} chưa được duyệt (trạng thái: {$status}).", ['request' => $request]);
        }

        $ops = $request['ops'] ?? [];
        $opts = $request['opts'] ?? [];

        if ($ops === []) {
            $this->setRequestStatus($id, 'applied', 'applied_by', $appliedBy);

            return $this->result('success', 'Không có operation nào để áp dụng.', ['request' => $this->find($id)]);
        }

        $this->setRequestStatus($id, 'applying');
        $applied = 0;

        $this->sync->acquireAdvisoryLock();
        try {
            foreach ($ops as $op) {
                $op['entity'] = (string) ($op['entity'] ?? $request['entity']);

                if (! $this->sync->isOpAllowed($op, $opts)) {
                    continue;
                }

                $this->sync->applyOperation($op);
                $applied++;
            }

            foreach ($ops as $op) {
                $this->sync->logMigration($op, [
                    'migration_id' => $id,
                    'status'       => 'applied',
                    'applied_at'   => new RawSql('CURRENT_TIMESTAMP'),
                    'created_by'   => (string) ($request['requested_by'] ?? $appliedBy),
                ]);
            }
        } catch (Throwable $e) {
            $this->setRequestStatus($id, 'failed');
            $this->updateLogStatus($id, 'approved', 'failed');
            $this->updateLogStatus($id, 'applying', 'failed');

            service('voltErrorLog')->logException($e, ['migration_id' => $id], 'migration_coordinator', 'migration_apply_failed');

            return $this->result('error', $e->getMessage(), ['request' => $this->find($id)]);
        } finally {
            $this->sync->releaseAdvisoryLock();
        }

        $this->setRequestStatus($id, 'applied', 'applied_by', $appliedBy);

        if ($applied > 0) {
            $this->queueRebuild();
        }

        return $this->result('success', "Migration #{$id} đã áp dụng {$applied} operation.", ['request' => $this->find($id)]);
    }

    /**
     * Rollback một migration request đã apply bằng inverse ops (khả dụng).
     *
     * @return array<string, mixed>
     */
    public function rollback(int $id, string $by = 'system'): array
    {
        $request = $this->find($id);
        if ($request === null) {
            return $this->result('error', "Migration #{$id} không tồn tại.");
        }

        if (($request['status'] ?? '') !== 'applied') {
            return $this->result('error', "Migration #{$id} chưa applied — không thể rollback.", ['request' => $request]);
        }

        $ops = array_reverse($request['ops'] ?? []);
        $rolled = 0;
        $skipped = 0;

        try {
            foreach ($ops as $op) {
                $inverse = $this->sync->inverseSqlFor($op);
                if ($inverse === null) {
                    $skipped++;
                    continue;
                }

                $this->db->query($inverse);
                $rolled++;
            }
        } catch (Throwable $e) {
            service('voltErrorLog')->logException($e, ['migration_id' => $id], 'migration_coordinator', 'migration_rollback_failed');

            return $this->result('error', 'Rollback thất bại: ' . $e->getMessage(), ['request' => $this->find($id)]);
        }

        $this->setRequestStatus($id, 'rolled_back');$this->updateLogStatus($id, 'applied', 'rolled_back');

        return $this->result(
            'success',
            "Migration #{$id} đã rollback {$rolled} operation ({$skipped} operation không tự đảo ngược được).",
            ['request' => $this->find($id)],
        );
    }

    /**
     * Liệt kê migration requests.
     *
     * @return array<string, mixed>
     */
    public function list(array $filters = []): array
    {
        $builder = $this->db->table(self::TABLE_REQUEST)
            ->orderBy('id', 'DESC');

        if (isset($filters['entity']) && $filters['entity'] !== '') {
            $builder->where('entity', (string) $filters['entity']);
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $builder->where('status', (string) $filters['status']);
        }

        $rows = $builder->get(100)->getResultArray() ?: [];

        return array_map(fn (array $row): array => $this->hydrateRow($row), $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $row = $this->db->table(self::TABLE_REQUEST)->where('id', $id)->get()->getRowArray();

        return is_array($row) ? $this->hydrateRow($row) : null;
    }

    /**
     * @param list<array<string, mixed>> $ops
     *
     * @return array<string, mixed>
     */
    private function createAndApply(string $entityName, array $ops, string $requestedBy, array $opts): array
    {
        $request = $this->createRequest($entityName, $ops, $requestedBy, $opts, 'approved');

        return $this->apply((int) $request['id'], $requestedBy);
    }

    /**
     * @param list<array<string, mixed>> $ops
     *
     * @return array<string, mixed>
     */
    private function createRequest(string $entityName, array $ops, string $requestedBy, array $opts, string $status): array
    {
        $summary = json_encode([
            'ops'  => $ops,
            'opts' => $opts,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->db->table(self::TABLE_REQUEST)->insert([
            'entity'       => $entityName,
            'status'       => $status,
            'summary'      => $summary,
            'requested_by' => $requestedBy,
            'created_at'   => new RawSql('CURRENT_TIMESTAMP'),
            'updated_at'   => new RawSql('CURRENT_TIMESTAMP'),
        ]);

        $id = (int) $this->db->insertID();

        foreach ($ops as $op) {
            $this->sync->logMigration($op, [
                'migration_id' => $id,
                'status'       => $status,
                'created_by'   => $requestedBy,
            ]);
        }

        return $this->find($id) ?? ['id' => $id];
    }

    /** @param array<string, mixed> $row */
    private function hydrateRow(array $row): array
    {
        $summary = $this->decodeJson((string) ($row['summary'] ?? '{}'));

        return [
            'id'           => (int) ($row['id'] ?? 0),
            'entity'       => (string) ($row['entity'] ?? ''),
            'status'       => (string) ($row['status'] ?? ''),
            'requested_by' => $row['requested_by'] ?? null,
            'approved_by'  => $row['approved_by'] ?? null,
            'applied_by'   => $row['applied_by'] ?? null,
            'approved_at'  => $row['approved_at'] ?? null,
            'applied_at'   => $row['applied_at'] ?? null,
            'created_at'   => $row['created_at'] ?? null,
            'ops'          => is_array($summary['ops'] ?? null) ? $summary['ops'] : [],
            'opts'         => is_array($summary['opts'] ?? null) ? $summary['opts'] : [],
            'summary'      => $summary,
        ];
    }

    private function setRequestStatus(int $id, string $status, string $byColumn = '', string $by = ''): void
    {
        $data = [
            'status'     => $status,
            'updated_at' => new RawSql('CURRENT_TIMESTAMP'),
        ];
        if ($byColumn !== '') {
            $data[$byColumn] = $by;
        }
        if ($status === 'applied') {
            $data['applied_at'] = new RawSql('CURRENT_TIMESTAMP');
        }

        $this->db->table(self::TABLE_REQUEST)->where('id', $id)->update($data);
    }

    private function updateLogStatus(int $migrationId, string $from, string $to): void
    {
        $this->db->table(self::TABLE_LOG)
            ->where('migration_id', $migrationId)
            ->where('status', $from)
            ->update(['status' => $to]);
    }

    /** @return array<string, mixed> */
    private function summarize(array $ops): array
    {
        $safe = 0;
        $breaking = 0;
        $downtime = [];

        foreach ($ops as $op) {
            if (($op['severity'] ?? '') === 'breaking') {
                $breaking++;
            } else {
                $safe++;
            }
            $downtime[($op['downtime'] ?? 'none')] = ($downtime[$op['downtime'] ?? 'none'] ?? 0) + 1;
        }

        return [
            'total'        => count($ops),
            'safe'         => $safe,
            'breaking'     => $breaking,
            'downtime'     => $downtime,
            'requires_approval' => $breaking > 0 && $this->sync->requiresApprovalForBreaking(),
        ];
    }

    private function queueRebuild(): void
    {
        try {
            service('voltQueue')?->dispatch('rebuild_metadata_cache');
        } catch (Throwable) {
            // Không làm fail migration nếu queue không sẵn sàng.
        }
    }

    /** @return array<string, mixed> */
    private function result(string $status, ?string $message, array $extra = []): array
    {
        return array_merge(['status' => $status, 'message' => $message], $extra);
    }

    private function decodeJson(string $value): array
    {
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}