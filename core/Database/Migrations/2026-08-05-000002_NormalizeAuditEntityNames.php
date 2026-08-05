<?php

declare(strict_types=1);

namespace Volt\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tối ưu truy vấn Activity theo index cho sys_audit_trail.entity.
 *
 * Context:
 * - data mới từ nay được chuẩn hóa snake_case tại AuditTrailWriter::normalizeEntity()
 *   (vd "Employee" → "employee"), nên entity sẽ dần nhất quán.
 * - data cũ có thể đang lưu title-case ("Employee"). Endpoint activity() đọc bằng
 *   LOWER(entity) để khớp cả cũ lẫn mới; functional index dưới đây giúp truy vấn
 *   đó dùng được index thay vì full-scan.
 *
 * KHÔNG UPDATE data có sẵn: sys_audit_trail là append-only (trigger guard chặn
 * UPDATE) và hash-chain ghi nhận cột entity, nên sửa sẽ phá toàn vẹn chuỗi.
 */
class NormalizeAuditEntityNames extends Migration
{
    private const T_AUDIT = 'sys_audit_trail';

    public function up(): void
    {
        // Index dự phòng cho truy vấn case-insensitive LOWER(entity) hiện dùng
        // ở endpoint activity(); giúp dùng được index thay vì Filter/full-scan.
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_sys_audit_entity_lower ON ' . self::T_AUDIT . ' (lower(entity), doc_id)');
    }

    public function down(): void
    {
        $this->db->query('DROP INDEX IF EXISTS idx_sys_audit_entity_lower');
    }
}