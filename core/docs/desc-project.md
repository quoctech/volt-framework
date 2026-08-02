# Volt Framework

## Mục tiêu

Volt Framework là một ERP engine `metadata-driven` xây trên:

- `CodeIgniter 4` (`^4.7`)
- `PHP` (`^8.2`)
- `PostgreSQL`

Định hướng chính là mô hình `configuration-driven`: mô tả thực thể nghiệp vụ bằng metadata, engine tự đồng bộ schema vật lý, sinh CRUD và artifact code, thay vì viết migration thủ công cho từng bảng.

## Trạng thái hiện tại

Các phần đã có trong code:

- Namespace `Volt\Core` map vào thư mục [`core`](../).
- Migration tạo 10 bảng hệ thống `sys_*` (`sys_entity`, `sys_entity_field`, `sys_entity_custom`, `sys_user`, `sys_permission`, `sys_sequence`, `sys_audit_trail`, `sys_queue_job`, `sys_module`, `sys_error_log`) + 2 migration nâng cấp (`sys_queue_job` mở rộng, `sys_schema_migration`).
- `SchemaSync` — đồng bộ metadata → bảng vật lý Postgres theo model plan/apply (CREATE/ALTER/DROP/RENAME/INDEX), hỗ trợ dry-run, prune, đổi tên, đổi kiểu.
- `VoltMetadataCompiler` — compile metadata từ 3 bảng `sys_*`, cache vào Redis.
- `MetadataValidator` — validate entity name, field name, field type, module.
- `VoltModel` — abstract model lõi với permission check, audit trail, system fields, workflow state machine (Draft→Submitted→Cancelled, amend).
- `PermissionResolver` — role-based permission matrix từ `sys_permission` + Redis cache.
- `AuditTrailWriter` — ghi delta `{before, after, changes}` vào `sys_audit_trail`.
- `ErrorLogService` — ghi lỗi runtime vào `sys_error_log` để phục vụ truy vết vận hành.
- `AuthService` + 4 Filters (`auth`, `guest`, `apiauth`, `admin`) — login/logout/setup/admin/API token.
- `EntityBuilderService` + `EntityBuilderController` — tạo module, entity, sync schema, sinh artifact.
- `ArtifactScaffolder` — sinh Controller/Model/View/JS Alpine vào `app/Modules/...`.
- `VoltResourceController` — API trung tâm CRUD cho entity (list/form view, REST CRUD, workflow actions, child table).
- `NamingSeriesGenerator` — sinh tài liệu theo pattern `PREFIX.YYYY.####` từ `sys_sequence`.
- Queue worker cho `sys_queue_job` (`QueueDispatcher`, `QueueWorker`, `QueueJobModel`, handler `rebuild_metadata_cache`).
- `EventBus` — event bus nội bộ (create/update/delete/submit/approve/cancel/amend đều dispatch).
- `WorkflowEngine` — state machine chuyển tiếp giữa các state.
- Child table mode `separate` — tách bảng con riêng (`tab_<child>`) kèm hệ thống cột chuẩn.
- CLI `php spark volt:sync [EntityName]` hoặc `--all`, `volt:queue-work`, `volt:core-migrate`.

Các phần chưa có hoặc mới ở mức định hướng:

- (không có mục nào đang chặn) — xem mục "Hướng phát triển tiếp theo" bên dưới.

## Cấu trúc dự án

```text
volt-project/
├── app/
│   ├── Config/
│   │   ├── Autoload.php
│   │   ├── Database.php
│   │   ├── Filters.php
│   │   ├── Routes.php
│   │   └── Services.php
│   ├── Controllers/
│   └── Views/
├── core/
│   ├── Audit/
│   ├── Auth/
│   ├── Commands/
│   ├── Database/
│   │   └── Migrations/
│   ├── docs/
│   ├── Engine/
│   ├── Metadata/
│   │   ├── Controllers/
│   │   └── Views/
│   ├── Models/
│   ├── Notes/
│   ├── Security/
│   └── Validation/
├── frontend/
├── public/
├── vendor/
├── .env
└── spark
```

## Các thành phần chính

### 1. Autoload và tổ chức mã nguồn

- [`../../app/Config/Autoload.php`](../../app/Config/Autoload.php) đăng ký namespace `Volt\Core` trỏ đến `ROOTPATH . 'core'`.
- Database schema của core nằm trong [`../../core/Database/Migrations`](../../core/Database/Migrations) và được chạy bằng `php spark volt:core-migrate`.
- Điều này cho phép tách phần mở rộng của Volt khỏi `app/` mặc định của CodeIgniter.

### 2. Cấu hình database

- [`../../app/Config/Database.php`](../../app/Config/Database.php) đang dùng driver `Postgre`.
- File [`../../.env`](../../.env) đang cấu hình kết nối DB runtime.
- Tài liệu này chỉ ghi nhận việc dùng `.env`; không nên sao chép thông tin nhạy cảm vào docs.

### 3. Migration nền tảng

Migration nền tảng + migration bổ sung hiện đang tạo 10 bảng lõi:

1. `sys_entity`
2. `sys_entity_field`
3. `sys_entity_custom`
4. `sys_user`
5. `sys_permission`
6. `sys_sequence`
7. `sys_audit_trail`
8. `sys_queue_job`
9. `sys_module`
10. `sys_error_log`

Các bảng này tạo nền cho metadata, phân quyền, đánh số chứng từ, audit, hàng đợi tác vụ và theo dõi lỗi hệ thống.

`sys_error_log` hiện lưu:

- `level`, `channel`, `code`
- `message`, `context`
- `file`, `line`, `trace`
- `request_uri`, `request_method`, `ip_address`, `user_agent`
- `actor`, `created_at`

### 4. Schema sync engine

[`../Engine/SchemaSync.php`](../Engine/SchemaSync.php) hiện xử lý theo mô hình **plan → apply**:

1. `planEntity($entityName, $opts)` — đọc metadata từ `sys_entity`/`sys_entity_field` và schema vật lý từ Postgres, tính toán danh sách thao tác (`CREATE TABLE`, `ALTER TABLE ADD/DROP/RENAME COLUMN`, `CREATE INDEX`) kèm log giải thích.
2. `syncEntity($entityName, $opts)` — gọi plan, nếu `dry_run=false` thì `applyPlan()` thực thi.
3. Sau khi apply thay đổi schema thành công, dispatch job `rebuild_metadata_cache` vào queue để warm lại cache Redis.

Các thao tác phá vỡ đều được gate bằng option:

- `--prune` → cho phép `DROP COLUMN` các cột dư thừa không còn trong metadata.
- `--allow-type-change` → cho phép `ALTER COLUMN TYPE` khi metadata khác schema.
- `--allow-rename` + `--renames` → bản đồ đổi tên cột `old:new`.

Một số ánh xạ kiểu dữ liệu hiện có:

- `Int` -> `INTEGER`
- `Float` -> `NUMERIC(18, 4)`
- `Data` -> `VARCHAR(n)`
- `Text` -> `TEXT`
- `Check` -> `SMALLINT`
- `Link` -> `VARCHAR(100)`
- `Table` -> `JSONB` (nhúng) hoặc bảng con riêng (`istable=1`)

Ngoài metadata field, engine còn tự thêm các cột hệ thống:

- `name`
- `docstatus`
- `owner`
- `creation`
- `modified`

### 5. Lệnh CLI

[`../Commands/VoltSync.php`](../Commands/VoltSync.php) khai báo lệnh:

```bash
php spark volt:sync Product
php spark volt:sync --all
```

Chức năng:

- Đồng bộ một entity cụ thể từ metadata.
- Hoặc quét toàn bộ entity trong `sys_entity`.

Options hỗ trợ:

```bash
php spark volt:sync employee --dry-run
php spark volt:sync employee --prune --allow-type-change
php spark volt:sync employee --allow-rename --renames "old_col:new_col"
php spark volt:sync employee --data-check   # kiểm tra dữ liệu, không sửa schema
```

Lệnh queue worker:

```bash
php spark volt:queue-work [--queue default] [--sleep 3] [--max-jobs 10] [--max-time 300]
php spark volt:queue-work --status
php spark volt:queue-work --retry <jobId>
php spark volt:queue-work --purge-dead --days 30
php spark volt:queue-work --stale-requeue
```

Chức năng:

- `volt:queue-work`: xử lý job trong `sys_queue_job` theo round-robin nhiều queue, tự requeue job hết hạn, dừng khi hết job hoặc quá `--max-time`/`maxRunSeconds` config.
- `--status`: thống kê số job theo trạng thái.
- `--retry`: reset job (failed/dead) về `queued`.
- `--purge-dead`: xóa job dead cũ hơn `--days` (mặc định 30).
- `--stale-requeue`: requeue job đang `processing` nhưng quá hạn timeout.

Lệnh cleanup hiện có thêm:

```bash
php spark volt:clean-entities
```

Chức năng:

- quét artifact entity dư thừa trong `app/Modules/.../Entities`
- đối chiếu bảng `tab_*` vật lý và metadata `sys_entity`
- hỏi tương tác `y/n` trước khi xóa từng candidate

Lệnh migrate cho riêng core:

```bash
php spark volt:core-migrate
php spark volt:core-migrate-status
```

Chức năng:

- `volt:core-migrate`: chạy toàn bộ migrations thuộc namespace `Volt\Core`
- `volt:core-migrate-status`: hiển thị trạng thái đã chạy/chưa chạy của các migration core
- dùng khi cần setup hoặc nâng cấp schema hệ thống như `sys_entity`, `sys_setting`, `sys_error_log`

## Luồng hoạt động hiện tại

1. Khai báo entity trong `sys_entity`.
2. Khai báo field trong `sys_entity_field`.
3. Chạy `php spark volt:sync <EntityName>` hoặc `php spark volt:sync --all`.
4. `SchemaSync` đọc metadata và tạo hoặc vá bảng vật lý trong PostgreSQL.
5. Khi runtime core bắt được exception ở các nhánh đã hook, gọi `service('voltErrorLog')->write(...)` hoặc `logException(...)` để ghi vào `sys_error_log`.

## Điểm lệch giữa ý tưởng và code hiện tại

- Không có file `app/Config/Commands.php` trong repo hiện tại (CI4 dùng autodiscovery, chưa cần).
- `SchemaSync` chưa hỗ trợ:
  - rollback delta (undo một lần apply)
  - merge hai entity tên khác nhau
- Queue chưa có handler mặc định cho job tuỳ ý — handler phải được đăng ký thủ công theo `job_type`.

## Hướng phát triển hợp lý tiếp theo

### Tầng data access

- Hoàn thiện `VoltModel`: bulk submit/cancel, import/export dữ liệu.
- Validation nghiệp vụ nâng cao (unique field config, dependent field).

### Tầng vận hành

- Dashboard queue (trang web xem job đang xử lý, thời gian chạy, retry).
- Retry tự động theo exponential backoff trong `QueueWorker`.
- Gắn thêm handler nghiệp vụ (notification, email) qua `EventBus`/queue.

### Tầng tài liệu

- Viết test cho `VoltModel`, `VoltResourceController`, `VoltMetadataCompiler`.
- Hoàn thiện `VOLT_FRAMEWORK.md` cho từng module core.

## Tóm tắt

Volt là ERP engine metadata-driven trên CI4 + PostgreSQL. Phần lõi đã có đủ: schema sync (plan/apply, prune, rename), metadata compiler + cache Redis, validation, model với permission/audit/workflow, auth, resource controller, naming series, queue worker, event bus, và admin UI.
