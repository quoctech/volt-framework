<?php

declare(strict_types=1);

namespace Volt\Core\Engine;

use CodeIgniter\Database\BaseConnection;
use Volt\Core\Database\VoltDatabase;

final class NamingSeriesGenerator
{
    private const HASH = 'HASH';

    private const TABLE_ENTITY = 'sys_entity';
    private const TABLE_SEQUENCE = 'sys_sequence';
    private const COL_AUTONAME = 'autoname';
    private const COL_NAME = 'name';
    private const COL_KEY = 'key';
    private const COL_CURRENT_VALUE = 'current_value';

    private readonly BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? VoltDatabase::connection();
    }

    /**
     * Sinh tên document từ autoname của entity.
     *
     * Ưu tiên đọc pattern từ compiled metadata (Redis cache), fallback đọc trực tiếp sys_entity.
     */
    public function generateForEntity(string $entityName, ?array $compiledMeta = null): string
    {
        $entityName = $this->normalizeEntityName($entityName);
        $pattern = '';

        if (is_array($compiledMeta)) {
            $pattern = mb_trim((string) ($compiledMeta['entity'][self::COL_AUTONAME] ?? ''));
        }

        if ($pattern === '') {
            $row = $this->db->table(self::TABLE_ENTITY)
                ->select(self::COL_AUTONAME)
                ->where(self::COL_NAME, $entityName)
                ->get()
                ->getRowArray();

            $pattern = is_array($row) ? mb_trim((string) ($row[self::COL_AUTONAME] ?? '')) : '';
        }

        return $this->generate($pattern, $entityName);
    }

    public function generate(string $pattern, string $entityName): string
    {
        $pattern = mb_trim($pattern);

        if ($pattern === '' || $pattern === self::HASH) {
            return bin2hex(random_bytes(16));
        }

        $resolved = strtr($pattern, [
            '.YYYY.' => gmdate('Y'),
            '.YY.'   => gmdate('y'),
            '.MM.'   => gmdate('m'),
            '.DD.'   => gmdate('d'),
        ]);

        // Chuẩn hóa placeholder lỗi: "E-.YYYY.-.00001" -> "E-2026-00001" (bỏ dấu chấm thừa
        // đứng sau dấu phân cách khi liền trước placeholder số thứ tự '#...' hoặc chuỗi số).
        $resolved = preg_replace('/([\-\/])\.(?=[#\d])/', '$1', $resolved) ?? $resolved;

        if (! preg_match('/#+/', $resolved, $matches)) {
            return $resolved;
        }

        $token = $matches[0];
        $serial = str_pad((string) $this->nextSequenceValue($this->sequenceKey($entityName, $resolved)), strlen($token), '0', STR_PAD_LEFT);

        return preg_replace('/#+/', $serial, $resolved, 1) ?? $resolved;
    }

    /**
     * Cấp phát số thứ tự kế tiếp một cách atomic (1 round-trip, chống race condition).
     *
     * Raw SQL có lý do: CI4 Query Builder upsert() không hỗ trợ RETURNING.
     */
    public function nextSequenceValue(string $key): int
    {
        $sql = 'INSERT INTO ' . self::TABLE_SEQUENCE . ' (' . self::COL_KEY . ', ' . self::COL_CURRENT_VALUE . ') '
             . 'VALUES (?, 1) '
             . 'ON CONFLICT (' . self::COL_KEY . ') '
             . 'DO UPDATE SET ' . self::COL_CURRENT_VALUE . ' = ' . self::TABLE_SEQUENCE . '.' . self::COL_CURRENT_VALUE . ' + 1 '
             . 'RETURNING ' . self::COL_CURRENT_VALUE;

        $result = $this->db->query($sql, [$key]);

        if ($result === null || $result->getNumRows() === 0) {
            return 0;
        }

        $row = $result->getRowArray();

        return is_array($row) ? (int) ($row[self::COL_CURRENT_VALUE] ?? 0) : 0;
    }

    private function sequenceKey(string $entityName, string $resolved): string
    {
        return $this->normalizeEntityName($entityName) . ':' . $resolved;
    }

    private function normalizeEntityName(string $name): string
    {
        $name = preg_replace('/(?<!^)[A-Z]/', '_$0', $name) ?? $name;
        $name = mb_strtolower(mb_trim($name));
        $name = preg_replace('/[^a-z0-9_]+/', '_', $name) ?? '';
        $name = preg_replace('/_+/', '_', $name) ?? '';

        return mb_trim($name, '_');
    }
}
