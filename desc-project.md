# Volt Framework

## Mục tiêu

Volt Framework là một framework ERP engine lấy cảm hứng từ Frappe, nhưng được xây trên:

- `CodeIgniter 4` (`^4.7`)
- `PHP` (`^8.2`)
- `PostgreSQL`

Định hướng chính của dự án là mô hình `configuration-driven`: mô tả thực thể nghiệp vụ bằng metadata, sau đó để engine tự đồng bộ schema vật lý thay vì viết CRUD và migration nghiệp vụ thủ công cho từng bảng.

## Trạng thái hiện tại

Repo hiện ở giai đoạn nền tảng. Các phần đã có trong code:

- Namespace `Volt\Core` đã được map vào thư mục [`core`](./core).
- Đã có migration tạo các bảng hệ thống `sys_*`.
- Đã có engine `SchemaSync` để tạo bảng mới hoặc thêm cột còn thiếu từ metadata.
- Đã có lệnh CLI `php spark volt:sync`.

Các phần chưa có hoặc mới ở mức định hướng:

- Chưa có `VoltModel.php`.
- Chưa có tầng CRUD động hoàn chỉnh.
- Chưa có audit trail tự động trong luồng `insert/update/delete`.
- Chưa có giao diện quản trị metadata.
- Chưa có worker hoặc queue processor cho `sys_queue_job`.

## Cấu trúc dự án

```text
volt-project/
├── app/
│   ├── Config/
│   │   ├── Autoload.php
│   │   ├── Database.php
│   │   └── Routes.php
│   ├── Controllers/
│   └── Views/
├── core/
│   ├── Commands/
│   │   └── VoltSync.php
│   ├── Database/
│   │   └── Migrations/
│   │       └── 2026-07-06-103833_CreateVoltBaseTables.php
│   └── Engine/
│       └── SchemaSync.php
├── public/
├── vendor/
├── .env
├── spark
└── desc-project.md
```

## Các thành phần chính

### 1. Autoload và tổ chức mã nguồn

- [`app/Config/Autoload.php`](/home/quoctk/Desktop/volt-project/app/Config/Autoload.php) đăng ký namespace `Volt\Core` trỏ đến `ROOTPATH . 'core'`.
- Điều này cho phép tách phần mở rộng của Volt khỏi `app/` mặc định của CodeIgniter.

### 2. Cấu hình database

- [`app/Config/Database.php`](/home/quoctk/Desktop/volt-project/app/Config/Database.php) đang dùng driver `Postgre`.
- File [`.env`](/home/quoctk/Desktop/volt-project/.env) đang cấu hình kết nối DB runtime.
- Tài liệu này chỉ ghi nhận việc dùng `.env`; không nên sao chép thông tin nhạy cảm vào docs.

### 3. Migration nền tảng

Migration [`core/Database/Migrations/2026-07-06-103833_CreateVoltBaseTables.php`](/home/quoctk/Desktop/volt-project/core/Database/Migrations/2026-07-06-103833_CreateVoltBaseTables.php) đang tạo 8 bảng lõi:

1. `sys_entity`
2. `sys_entity_field`
3. `sys_entity_custom`
4. `sys_user`
5. `sys_permission`
6. `sys_sequence`
7. `sys_audit_trail`
8. `sys_queue_job`

Các bảng này tạo nền cho metadata, phân quyền, đánh số chứng từ, audit và hàng đợi tác vụ.

### 4. Schema sync engine

[`core/Engine/SchemaSync.php`](/home/quoctk/Desktop/volt-project/core/Engine/SchemaSync.php) hiện xử lý hai kịch bản chính:

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

[`core/Commands/VoltSync.php`](/home/quoctk/Desktop/volt-project/core/Commands/VoltSync.php) khai báo lệnh:

```bash
php spark volt:sync Product
php spark volt:sync --all
```

Chức năng:

- Đồng bộ một entity cụ thể từ metadata.
- Hoặc quét toàn bộ entity trong `sys_entity`.

## Luồng hoạt động hiện tại

1. Khai báo entity trong `sys_entity`.
2. Khai báo field trong `sys_entity_field`.
3. Chạy `php spark volt:sync <EntityName>` hoặc `php spark volt:sync --all`.
4. `SchemaSync` đọc metadata và tạo hoặc vá bảng vật lý trong PostgreSQL.

## Điểm lệch giữa ý tưởng và code hiện tại

Đây là các điểm cần ghi rõ để tránh hiểu sai trạng thái dự án:

- Không có file `app/Config/Commands.php` trong repo hiện tại.
- Không có thư mục `core/Models/` hoặc file `VoltModel.php`.
- `SchemaSync` hiện mới hỗ trợ tạo bảng và thêm cột thiếu, chưa xử lý:
  - đổi kiểu cột
  - đổi tên cột
  - xóa cột
  - tạo index nghiệp vụ
  - rollback delta
- Routing web vẫn là mặc định của CodeIgniter, chưa có module quản trị Volt.

## Hướng phát triển hợp lý tiếp theo

### Tầng metadata

- Bổ sung chuẩn định nghĩa entity và field rõ ràng hơn.
- Thêm validation cho metadata trước khi sync schema.

### Tầng data access

- Xây `VoltModel` hoặc service layer để:
  - validate `reqd`
  - sinh `name` theo `autoname`
  - xử lý `docstatus`
  - khóa logic trùng dữ liệu

### Tầng audit

- Ghi tự động `insert`, `update`, `delete` vào `sys_audit_trail`.
- Tính `delta` theo before/after snapshot.

### Tầng child table

- Quy ước rõ hai mode cho field `Table`:
  - nhúng `JSONB`
  - tách bảng con riêng

### Tầng vận hành

- Thêm command migration/setup cho Volt.
- Thêm queue worker cho `sys_queue_job`.
- Viết test cho migration và `SchemaSync`.

## Tóm tắt

Volt hiện là một bộ khung nền tảng cho ERP engine chạy trên CI4 + PostgreSQL, với trọng tâm là metadata và tự động đồng bộ schema. Phần lõi đã có hướng rõ ràng, nhưng mới dừng ở tầng bootstrap kỹ thuật; các tầng model, nghiệp vụ, audit và quản trị vẫn cần được triển khai thêm.
