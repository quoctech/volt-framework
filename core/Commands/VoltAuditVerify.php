<?php

declare(strict_types=1);

namespace Volt\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Volt\Core\Database\VoltDatabase;

/**
 * Xác minh tính toàn vẹn của hash-chain trên sys_audit_trail.
 * - Recompute lại hash từng dòng bằng đúng hàm SQL volt_audit_hash() (không tự suy diễn lại thuật toán).
 * - Kiểm tra prev_hash từng dòng có khớp hash dòng trước (anchor đầu = prev_hash dòng khởi đầu).
 * - Kiểm tra sys_audit_chain.last_hash/last_id khớp dòng cuối cùng.
 */
final class VoltAuditVerify extends BaseCommand
{
    protected $group       = 'Volt Core';
    protected $name        = 'volt:audit-verify';
    protected $description = 'Verify sys_audit_trail hash-chain integrity (append-only, tamper detection)';
    protected $usage       = 'volt:audit-verify [options]';
    protected $options     = [
        '--list' => 'List up to N mismatched rows (default: 10)',
        '--genesis' => 'Optional: expected anchor hash for the first chained row',
    ];

    public function run(array $params): void
    {
        $db = VoltDatabase::connection();

        if (! $db->tableExists('sys_audit_trail') || ! $db->tableExists('sys_audit_chain')) {
            CLI::error('sys_audit_trail / sys_audit_chain not found. Run volt:core-migrate first.');
            return;
        }

        $expectedGenesis = (string) ($params['genesis'] ?? '');

        // Anchor của chuỗi = prev_hash dòng có hash đầu tiên (không hardcode genesis).
        $anchorRow = $db->query(
            'SELECT id, prev_hash FROM sys_audit_trail WHERE hash IS NOT NULL ORDER BY id LIMIT 1'
        )->getRowArray();

        if (! is_array($anchorRow)) {
            CLI::write('Audit trail rows with hash: 0', 'yellow');
            CLI::write('Audit trail integrity: VERIFIED (no chained rows).', 'green');

            return;
        }

        $anchorId = (int) $anchorRow['id'];
        $anchorHash = (string) $anchorRow['prev_hash'];

        if ($expectedGenesis !== '' && $expectedGenesis !== $anchorHash) {
            CLI::error("Anchor (genesis) mismatch: expected {$expectedGenesis}, actual {$anchorHash}.");
            CLI::error('Audit trail integrity: BROKEN. Investigate immediately.');

            return;
        }

        $summary = $db->query(
            'WITH hashed AS (
                SELECT id, hash, prev_hash,
                       volt_audit_hash(
                           prev_hash, category, entity, doc_id, action, operation, status,
                           changed_by, changed_at, tenant, ip_address, user_agent, request_id, delta::text
                       ) AS expected_hash,
                       LAG(hash) OVER (ORDER BY id) AS prev_row_hash
                FROM sys_audit_trail
                WHERE hash IS NOT NULL
            )
            SELECT
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE expected_hash <> hash) AS hash_mismatches,
                COUNT(*) FILTER (WHERE id <> :anchor: AND prev_hash IS DISTINCT FROM prev_row_hash) AS chain_breaks
            FROM hashed',
            ['anchor' => $anchorId],
        )->getRowArray();

        $total = (int) ($summary['total'] ?? 0);
        $hashMismatches = (int) ($summary['hash_mismatches'] ?? 0);
        $chainBreaks = (int) ($summary['chain_breaks'] ?? 0);

        $chain = $db->table('sys_audit_chain')->where('lock_key', 1)->get()->getRowArray();

        $lastRow = $db->table('sys_audit_trail')
            ->select('id, hash')
            ->where('hash IS NOT NULL')
            ->orderBy('id', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();

        $chainOk = true;
        $chainNote = '';
        if ($total === 0) {
            $chainNote = ' (no chained rows yet)';
        } else {
            $chainLastHash = (string) ($chain['last_hash'] ?? '');
            $chainLastId = (int) ($chain['last_id'] ?? 0);
            $rowLastHash = (string) ($lastRow['hash'] ?? '');
            $rowLastId = (int) ($lastRow['id'] ?? 0);

            if ($chainLastHash !== $rowLastHash || $chainLastId !== $rowLastId) {
                $chainOk = false;
                $chainNote = sprintf(
                    ' [chain state diverged: last_hash %s vs row %s, last_id %d vs %d]',
                    $chainLastHash, $rowLastHash, $chainLastId, $rowLastId,
                );
            }
        }

        CLI::write(sprintf('Audit trail rows with hash: %d', $total), 'yellow');
        CLI::write(sprintf('Hash mismatches: %d', $hashMismatches), $hashMismatches === 0 ? 'green' : 'red');
        CLI::write(sprintf('Chain breaks: %d', $chainBreaks), $chainBreaks === 0 ? 'green' : 'red');
        CLI::write('Chain tail state: ' . ($chainOk ? 'OK' : 'DIVERGED') . $chainNote, $chainOk ? 'green' : 'red');

        if ($hashMismatches === 0 && $chainBreaks === 0 && $chainOk) {
            CLI::write('Audit trail integrity: VERIFIED.', 'green');

            return;
        }

        $limit = max(1, (int) ($params['list'] ?? 10));
        $mismatchRows = $db->query(
            'WITH hashed AS (
                SELECT id, category, entity, action, changed_by, changed_at, hash,
                       volt_audit_hash(
                           prev_hash, category, entity, doc_id, action, operation, status,
                           changed_by, changed_at, tenant, ip_address, user_agent, request_id, delta::text
                       ) AS expected_hash
                FROM sys_audit_trail
                WHERE hash IS NOT NULL
            )
            SELECT id, category, entity, action, changed_by, changed_at, hash, expected_hash
            FROM hashed
            WHERE expected_hash <> hash
            ORDER BY id
            LIMIT :limit:',
            ['limit' => $limit],
        )->getResultArray();

        if ($mismatchRows !== []) {
            CLI::write('First ' . count($mismatchRows) . ' mismatched row(s):', 'red');
            foreach ($mismatchRows as $row) {
                CLI::write(sprintf(
                    '  #%d %s/%s %s by %s at %s',
                    (int) $row['id'],
                    (string) $row['category'],
                    (string) $row['entity'],
                    (string) $row['action'],
                    (string) $row['changed_by'],
                    (string) $row['changed_at'],
                ), 'red');
            }
        }

        CLI::error('Audit trail integrity: BROKEN. Investigate immediately.');
    }
}
