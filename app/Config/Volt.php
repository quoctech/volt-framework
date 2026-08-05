<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Cấu hình lõi Volt Framework cho SchemaSync / MigrationCoordinator.
 *
 * Mọi giá trị đều có mặc định an toàn để hệ thống chạy được ngay cả khi file
 * này chưa từng được chỉnh sửa. Có thể ghi đè từng trường qua biến môi trường
 * dạng `volt.schemaSync.lockTimeoutMs` (CI4 đọc env vào config property).
 */
class Volt extends BaseConfig
{
    /** Lock timeout (ms) cho các ALTER/DROP nguy hiểm. 0 = dùng mặc định server. */
    public int $schemaSyncLockTimeoutMs = 2000;

    /** Statement timeout (ms) cho DDL. 0 = không giới hạn. */
    public int $schemaSyncStatementTimeoutMs = 0;

    /** Dùng CREATE INDEX CONCURRENTLY để không khóa bảng khi tạo index trên bảng lớn. */
    public bool $schemaSyncConcurrentIndexCreate = false;

    /** Số dòng tối thiểu để coi bảng là "lớn" (dùng cho quyết định chiến lược). */
    public int $schemaSyncLargeTableRows = 100000;

    /** Tự apply các thay đổi an toàn (add column, create table, widen) ngay khi plan. */
    public bool $schemaSyncAutoApplySafe = true;

    /** Yêu cầu approval cho thao tác breaking (đổi kiểu, xóa cột, drop index, drop constraint). */
    public bool $schemaSyncRequireApprovalForBreaking = true;

    /** Khóa advisory dùng để chặn 2 luồng schema sync chạy song song trên cùng DB. */
    public int $schemaSyncAdvisoryLockKey = 8675309;

    /** Env được coi là production (yêu cầu approval). Rỗng = tự nhận biết từ CI_ENVIRONMENT. */
    public string $schemaSyncProductionEnvs = 'production';

    /** Rate limiting toàn cục theo IP: số request tối đa trong cửa sổ (mỗi IP). */
    public int $rateLimitGlobalAttempts = 300;

    /** Rate limiting toàn cục: cửa sổ thời gian (giây). */
    public int $rateLimitGlobalWindowSeconds = 60;

    /** Grace period (ngày) trước khi tenant DB bị purge thật sau khi xóa. */
    public int $tenantDeleteGraceDays = 7;

    /** Thư mục lưu backup (mặc định WRITEPATH/backups). */
    public string $backupDir = '';

    /** Số ngày giữ backup trước khi prune tự động. */
    public int $backupRetentionDays = 30;

    /** Webhook URL nhận error alert. Rỗng = tắt alert. */
    public string $alertWebhookUrl = '';

    /** Secret HMAC ký payload gửi webhook. Rỗng = không ký. */
    public string $alertWebhookSecret = '';

    /** Level tối thiểu để trigger alert (emergency, alert, critical, error, warning...). */
    public string $alertMinLevel = 'error';

    /** Cho phép drop bảng trực tiếp ở production qua Entity Builder/CLI sync. */
    public bool $schemaSyncAllowDirectDropInProduction = false;

    /** Bật Custom Pages JS (nếu tắt, js_content bị bỏ qua khi render/save). */
    public bool $pagesJsEnabled = true;

    /** Chế độ audit fail-closed: nếu ghi audit thất bại sẽ throw (không âm thầm bỏ qua). */
    public bool $strictAudit = true;
}
