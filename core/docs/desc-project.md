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
- Migration tạo 10 bảng hệ thống `sys_*` (`sys_entity`, `sys_entity_field`, `sys_entity_custom`, `sys_user`, `sys_permission`, `sys_sequence`, `sys_audit_trail`, `sys_queue_job`, `sys_module`, `sys_error_log`).
- `SchemaSync` — đồng bộ metadata → bảng vật lý Postgres (CREATE/ALTER TABLE).
- `VoltMetadataCompiler` — compile metadata từ 3 bảng `sys_*`, cache vào Redis.
- `MetadataValidator` — validate entity name, field name, field type, module.
- `VoltModel` — abstract model lõi với permission check, audit trail, system fields.
- `PermissionResolver` — role-based permission matrix từ `sys_permission` + Redis cache.
- `AuditTrailWriter` — ghi delta `{before, after, changes}` vào `sys_audit_trail`.
- `ErrorLogService` — ghi lỗi runtime vào `sys_error_log` để phục vụ truy vết vận hành.
- `AuthService` + 4 Filters (`auth`, `guest`, `apiauth`, `admin`) — login/logout/setup/admin/API token.
- `EntityBuilderService` + `EntityBuilderController` — tạo module, entity, sync schema, sinh artifact.
- `ArtifactScaffolder` — sinh Controller/Model/View/JS Alpine vào `app/Modules/...`.
- CLI `php spark volt:sync [EntityName]` hoặc `--all`.

Các phần chưa có hoặc mới ở mức định hướng:

- Queue worker cho `sys_queue_job`.
- `VoltResourceController` — API trung tâm cho entity CRUD.
- `NamingSeriesGenerator` — sinh tài liệu theo pattern.
- Child table mode `separate` — tách bảng con riêng.

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

[`../Engine/SchemaSync.php`](../Engine/SchemaSync.php) hiện xử lý hai kịch bản chính:

- Nếu bảng vật lý chưa tồn tại: tự sinh `CREATE TABLE`.
- Nếu bảng đã tồn tại: kiểm tra metadata trong `sys_entity_field` và `ALTER TABLE ADD COLUMN` cho các cột còn thiếu.

Một số ánh xạ kiểu dữ liệu hiện có:

- `Int` -> `INTEGER`
- `Float` -> `NUMERIC(18, 4)`
- `Data` -> `VARCHAR(n)`
- `Text` -> `TEXT`
- `Check` -> `SMALLINT`
- `Link` -> `VARCHAR(100)`
- `Table` -> `JSONB`

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
- `SchemaSync` hiện mới hỗ trợ tạo bảng và thêm cột thiếu, chưa xử lý:
  - đổi kiểu cột
  - đổi tên cột
  - xóa cột
  - tạo index nghiệp vụ
  - rollback delta

## Hướng phát triển hợp lý tiếp theo

### Tầng data access

- Hoàn thiện `VoltModel`: docstatus state machine (Draft→Submitted→Cancelled).
- Xây `VoltResourceController` — API trung tâm CRUD cho entity.

### Tầng child table

- Quy ước rõ hai mode cho field `Table`:
  - nhúng `JSONB`
  - tách bảng con riêng

### Tầng vận hành

- Queue worker cho `sys_queue_job`.
- Thêm command migration/setup cho Volt.
- Viết test cho migration, `SchemaSync`, `VoltModel`.

## Tóm tắt

Volt là ERP engine metadata-driven trên CI4 + PostgreSQL. Phần lõi đã có đủ: schema sync, metadata compiler + cache, validation, model với permission/audit, auth, và admin UI. Các tầng còn lại cần triển khai: queue worker, resource controller, child table mode, naming series generator.
