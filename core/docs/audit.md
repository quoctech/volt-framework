# Audit Trail — Volt Framework

Audit trail ghi mọi sự kiện vận hành (operations) quan trọng nhằm truy vết "ai làm gì, khi nào, ở đâu" và phát hiện giả mạo. Chi phối bởi `core/Audit/AuditTrailWriter.php` + migration `2026-08-05-000001_UpgradeAuditTrailForOps`.

## Nguyên tắc

- **Append-only**: không sửa/xóa trực tiếp `sys_audit_trail`. Trigger `volt_audit_guard` chặn mọi `UPDATE`/`DELETE`; chỉ hàm `volt_audit_purge(N)` (`SECURITY DEFINER`, đặt `volt.purge=1`) được phép xóa theo retention.
- **Ghi đồng bộ** với luồng nghiệp vụ. Mặc định **fail-closed** (`volt.strictAudit = true`, config `app/Config/Volt.php`): nếu insert `sys_audit_trail` thất bại → ném `RuntimeException`, nghiệp vụ bị chặn (audit là bắt buộc). Nếu tắt strictAudit (`volt.strictAudit = false`) → fallback vào `sys_error_log` (cùng `request_id`), không throw để không làm hỏng nghiệp vụ.
- **Hash-chain** phát hiện giả mạo (`volt:audit-verify`).
- **Retention** mặc định 730 ngày (`volt:clean-audit --days=N`).

## Cài đặt / nâng cấp

Migration `2026-08-05-000001_UpgradeAuditTrailForOps`:

- Mở `action` sang `VARCHAR(64)`, thêm cột `category`, `operation`, `status`, `tenant`, `ip_address`, `user_agent`, `request_id`, `prev_hash`, `hash`.
- Tạo bảng singleton `sys_audit_chain (lock_key, last_hash, last_id)` với genesis hash.
- Tạo 3 trigger: `volt_audit_hash_set` (tính hash), `volt_audit_chain_update` (nối chuỗi), `volt_audit_guard` (append-only).
- Tạo hàm `volt_audit_hash(...)` (SHA-256, IMMUTABLE) & `volt_audit_purge(days)`.
- Thêm cột `sys_error_log.request_id`.

```
php spark volt:core-migrate
php spark volt:audit-verify
```

## Category taxonomy

| Category | Ý nghĩa | Ví dụ action |
|----------|---------|--------------|
| `data` | Thay đổi dữ liệu entity | `create`, `update`, `delete` (qua `VoltModel`) |
| `auth` | Đăng nhập/đăng xuất/đổi mật khẩu/API key/token | `auth:login`, `auth:logout`, `auth:api_key_issue`, `auth:api_token_revoke` |
| `role` | Quản trị vai trò | `role:create`, `role:update`, `role:delete` |
| `permission` | Gán/thu hồi quyền | `permission:update` |
| `api` | Sự kiện API key/system | `auth:api_key_issue`... |
| `file` | File | `file:upload`, `file:download`, `file:delete` |
| `export` | Xuất dữ liệu | `report:export` |
| `tenant` | Vòng đời tenant (hub DB) | `tenant:create`, `tenant:delete`, `tenant:db_created`, `tenant:db_dropped`, `tenant:db_migrated` |
| `metadata` | Metadata entity/page | `metadata:entity_save`, `metadata:entity_delete`, `metadata:workflow_save`, `metadata:page_save`, `metadata:page_delete` |
| `schema` | DDL đồng bộ schema | `schema:apply` |
| `workflow` | Workflow transition | `workflow:submit`, `workflow:approve`, `workflow:cancel`, `workflow:amend` |
| `system` | Sự kiện hệ thống | - |

## Cách ghi

```php
use Volt\Core\Audit\AuditTrailWriter;

$ok = service('voltAuditTrailWriter')->write(
    AuditTrailWriter::CAT_WORKFLOW,
    'workflow:approve',
    'leave',
    'LV-0001',
    ['workflow_state' => 'Submitted'],   // before
    ['workflow_state' => 'Approved', 'comment' => 'OK'], // after
    $actorName,                          // optional; mặc định từ voltAuth
    ['operation' => '...', 'request_id' => ...] // optional context
);
```

Các vị trí đã instrument:

- `core/Engine/WorkflowEngine.php` — mọi transition ghi audit (không còn gate theo comment).
- `core/Models/VoltModel.php` — `writeAudit?`/`amend()` ghi CAT_DATA; `amend()` luôn ghi `workflow:amend`.
- `app/Auth/Services/AuthService.php` — login/logout/change_password/api_key/api_token (issue + revoke).
- `role/Controllers/RoleController.php`, `RolePermissionController.php`, `Auth/Controllers/UserController.php`.
- `Controllers/FileController.php` (upload/download/delete), `Report/Controllers/ReportController.php` (export).
- `Tenant/Services/TenantService.php`, `Commands/TenantCreate.php`, `Database/VoltDatabase.php` (`auditHubEvent` — ghi vào hub DB qua `hubConnection()`).
- `Metadata/EntityBuilderService.php` (`auditMetadata`), `Metadata/Services/PageService.php` (`auditPage`), `Engine/SchemaSync.php` (mỗi op trong `applyOperation`).
- `System/Services/ErrorLogService.php` — đính `request_id`.

## request_id / correlation

- `core/Audit/RequestContext.php` — cung cấp `requestId()`, `ip()`, `userAgent()`, context.
- `app/Auth/Filters/CorrelationFilter.php` (đã đăng ký global `correlation`) — đọc/dấu `X-Request-ID` header.
- `sys_audit_trail.request_id` khớp `sys_error_log.request_id` để đối chiếu hành vi ↔ lỗi.

## Hash-chain

`hash = sha256(concat_ws('|',
    prev_hash, category, entity, doc_id, action, operation, status, changed_by,
    to_char(changed_at,'YYYY-MM-DD HH24:MI:SS.US'), tenant, ip_address, user_agent,
    request_id, delta::text))`

&nbsp;
- Dòng đầu: `prev_hash` = genesis (trong `sys_audit_chain`).
- Mỗi insert nối chuỗi & cập nhật `last_hash/last_id`.

## Chuẩn hóa entity name

- `AuditTrailWriter::normalizeEntity()` chuẩn hóa `entity` về `snake_case` khi ghi
  (vd `Employeeeducation` → `employeeeducation`), để query khớp chính xác và dùng index.
- Endpoint activity đọc bằng `LOWER(entity)` cho tương thích data cũ (title-case);
  functional index `idx_sys_audit_entity_lower (lower(entity), doc_id)` (migration
  `2026-08-05-000002`) giữ truy vấn dùng được index thay vì full-scan.
- Không UPDATE data cũ: bảng append-only (trigger guard) và hash-chain nhận cột entity
  nên sửa sẽ phá toàn vẹn chuỗi.

## Commands

```bash
php spark volt:audit-verify [--list N] [--genesis <hash>]
php spark volt:clean-audit [--days 730] [--dry-run]
```

`volt:audit-verify`:
- Recompute hash từng dòng bằng đúng hàm SQL `volt_audit_hash()` → đếm hash mismatch.
- Kiểm tra `prev_hash` từng dòng khớp hash dòng trước (anchor = prev_hash dòng khởi đầu) → đếm chain break.
- Kiểm tra `sys_audit_chain.last_hash/last_id` khớp dòng cuối.
- Nếu mọi thứ khớp: `VERIFIED`; ngược lại liệt kê dòng lỗi và cảnh báo `BROKEN`.

## Lưu ý vận hành

- Không xóa trực tiếp bằng SQL; dùng `volt:clean-audit`.
- Genesis hash mong đợi của code là hằng số trong `AuditTrailWriter::GENESIS_HASH` (đồng bộ với migration). Nếu một DB đã seed chuỗi với genesis khác (VD nâng cấp từ migration cũ), hãy dùng `--genesis` để ép kiểm tra anchor thực tế; chuỗi phải nhất quán nội bộ (liên tiếp).
- Ghi audit chạy đồng bộ được cố ý (truy vết đầy đủ). Nếu tải cao, cân nhắc index theo `(category, changed_at)` — migration đã tạo các index cần thiết.