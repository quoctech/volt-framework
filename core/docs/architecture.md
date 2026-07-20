# Volt Framework Architecture

## Mục tiêu kiến trúc

Volt là một ERP engine `metadata-driven` xây trên `CodeIgniter 4` và `PostgreSQL`, lấy cảm hứng từ Frappe nhưng không sao chép kiến trúc của Frappe.

Kiến trúc của Volt phải đạt đồng thời các mục tiêu sau:

- bảo mật mặc định
- hiệu suất cao ở đường chạy chính
- tận dụng tối đa built-in của CI4
- frontend nhẹ, đơn giản, dùng `Alpine.js`
- hỗ trợ mở rộng theo metadata nhưng không đánh đổi tính kiểm soát

## Nguyên tắc nền

1. `Security first`
2. `Performance first`
3. `Built-in first`
4. `Convention over duplication`
5. `Metadata-driven with guardrails`

## Kiến trúc tổng thể

```text
Client
  -> HTTP Request
  -> CI4 Routes
  -> Filters
  -> Controller
  -> Volt Service / Engine
  -> Model / Query Builder / Database
  -> PostgreSQL
  -> View (CI4) + Alpine.js
  -> HTTP Response
```

Hệ thống chia thành 4 lớp chính:

1. `Delivery layer`
2. `Application layer`
3. `Core engine layer`
4. `Persistence layer`

## 1. Delivery layer

Thành phần:

- `Routes`
- `Filters`
- `Controllers`
- `Views`

Trách nhiệm:

- nhận request
- áp dụng auth, CSRF, CORS, throttling nếu cần
- parse input
- gọi đúng service hoặc engine
- trả HTML hoặc JSON response

Ràng buộc:

- không đặt business logic trong controller
- không query nghiệp vụ trực tiếp trong controller nếu có service/model phù hợp
- response phải nhất quán và không lộ thông tin nhạy cảm

## 2. Application layer

Đây là tầng orchestration cho từng use case cụ thể.

Ví dụ trách nhiệm:

- tạo document
- submit document
- validate quyền sửa
- điều phối audit
- điều phối queue

Tầng này nên tận dụng:

- service class trong `core/`
- `Validation` của CI4
- transaction khi một use case có nhiều bước ghi

## 3. Core engine layer

Đây là phần phân biệt Volt với một ứng dụng CI4 thông thường.

Các engine dự kiến:

- `SchemaSync`
- `MetadataValidator`
- `PermissionResolver`
- `NamingSeriesGenerator`
- `VoltModel` hoặc `DocumentService`
- `AuditTrailWriter`
- `QueueDispatcher`

Trách nhiệm:

- xử lý metadata
- đồng bộ schema
- áp luật nghiệp vụ dùng chung
- tạo ra hành vi nhất quán cho toàn bộ entity

Ràng buộc:

- mọi engine phải deterministic và test được
- không được tự bypass validation hoặc permission
- phải tối ưu để metadata không làm chậm đường chạy nghiệp vụ

## 4. Persistence layer

Gồm:

- PostgreSQL
- bảng hệ thống `sys_*`
- bảng nghiệp vụ được sinh từ metadata
- cache backend nếu bổ sung sau

### 4.1 Bảng hệ thống hiện có

1. `sys_entity`
2. `sys_entity_field`
3. `sys_entity_custom`
4. `sys_user`
5. `sys_permission`
6. `sys_sequence`
7. `sys_audit_trail`
8. `sys_queue_job`
9. `sys_module`
10. `sys_role`
11. `sys_awesome_bar`
12. `sys_setting`
13. `sys_error_log`

### 4.2 Vai trò từng bảng

- `sys_entity`: định nghĩa entity gốc
- `sys_entity_field`: định nghĩa field và thứ tự field
- `sys_entity_custom`: metadata tùy biến
- `sys_user`: người dùng hệ thống
- `sys_permission`: ma trận quyền động
- `sys_sequence`: bộ đếm sinh mã
- `sys_audit_trail`: nhật ký thay đổi dữ liệu
- `sys_queue_job`: hàng đợi tác vụ nền
- `sys_module`: danh mục module runtime
- `sys_role`: danh mục role
- `sys_awesome_bar`: index điều hướng và search nhanh
- `sys_setting`: cấu hình runtime của hệ thống
- `sys_error_log`: nhật ký lỗi hệ thống phục vụ observability

### 4.3 Error Logs

Volt có tầng Error Logs riêng bên cạnh logger mặc định của CI4:

- bảng lưu trữ: `sys_error_log`
- service ghi log: `service('voltErrorLog')`
- mục tiêu: lưu lỗi nghiệp vụ/runtime quan trọng ngay trong DB để admin và tooling có thể truy vết tập trung

Payload chuẩn hiện tại gồm:

- `level`, `channel`, `code`
- `message`
- `context` dạng `JSONB`
- `file`, `line`, `trace`
- `request_uri`, `request_method`, `ip_address`, `user_agent`
- `actor`, `created_at`

Quy ước sử dụng:

- `write()` cho lỗi hoặc cảnh báo đã chuẩn hóa
- `logException()` khi đang cầm `Throwable`
- vẫn giữ logger CI4 làm fallback nếu việc ghi `sys_error_log` thất bại

## Metadata model

Metadata là đầu vào điều khiển hành vi hệ thống, nhưng phải được kiểm soát.

### Luồng metadata chuẩn

1. Định nghĩa entity trong `sys_entity`
2. Định nghĩa field trong `sys_entity_field`
3. Validate metadata
4. Sync schema
5. Bật CRUD hoặc document workflow cho entity

### Guardrail bắt buộc

- tên entity phải hợp lệ
- tên field phải hợp lệ và sanitize được
- fieldtype chỉ được nằm trong whitelist
- option của field phải được parse rõ ràng
- metadata thay đổi phải có cơ chế invalidation cache

### Metadata compiler

Volt dùng một lớp compiler để ghép metadata từ:

- `sys_entity`
- `sys_entity_field`
- `sys_entity_custom`

Compiler chịu trách nhiệm:

- deep patch cấu hình gốc bằng custom JSONB
- tạo payload phẳng cho runtime
- cache payload vào Redis
- tránh đọc ngược 3 bảng `sys_*` ở request path nóng

Kết quả compiler phải bao gồm:

- `entity`
- `fields`
- `field_order`
- `main_fields`
- `child_fields`
- `derived` indexes
- `source` snapshot phục vụ debug hoặc warm cache

### Redis cache layer

Redis là lớp đệm mặc định cho metadata đã biên dịch.

Mục tiêu:

- truy cập metadata dưới 1ms trong điều kiện bình thường
- giảm load cho DB
- dùng cache invalidation thay vì truy vấn lại từng request

Cache key phải:

- có version prefix
- có entity segment rõ ràng
- hỗ trợ role-specific variant nếu cần
- có index để xóa theo entity

## Schema synchronization

`SchemaSync` là lõi hiện tại của Volt.

### Chức năng hiện có

- tạo bảng vật lý nếu chưa tồn tại
- thêm cột còn thiếu theo metadata
- ánh xạ fieldtype logic sang PostgreSQL type

### Chức năng đã bắt đầu triển khai

- `VoltMetadataCompiler`
- cache metadata đã biên dịch qua Redis
- invalidate cache theo entity hoặc role

### Chức năng cần mở rộng

- phát hiện thay đổi kiểu
- đổi tên cột an toàn
- thêm index
- kiểm tra compatibility trước khi apply
- dry-run mode
- migration log

### Nguyên tắc thiết kế cho sync

- idempotent
- an toàn khi chạy lặp lại
- không ghép SQL từ input chưa sanitize
- ghi log rõ ràng từng thay đổi
- hạn chế lock kéo dài trên bảng production

## Data access strategy

Volt không nên để business module tự query tùy ý.

Chiến lược chuẩn:

- dùng `Query Builder` của CI4 cho phần lớn thao tác
- đóng gói logic đọc/ghi qua service hoặc model lõi
- chuẩn hóa vòng đời document:
  - validate input
  - resolve permission
  - generate name
  - persist data
  - write audit
  - dispatch event hoặc queue job nếu cần

## Permission architecture

Permission cần được đánh giá theo nhiều chiều:

- user
- roles
- entity
- state
- action
- field-level permission nếu có

Thiết kế mong muốn:

- `Filters` xử lý authentication
- `PermissionResolver` xử lý authorization
- controller hoặc service không tự viết lại ma trận quyền bằng tay

## Audit architecture

Audit trail là thành phần mặc định của ERP engine.

Mỗi thay đổi quan trọng nên ghi:

- actor
- entity
- document id
- action
- before snapshot hoặc delta
- after snapshot nếu cần
- timestamp

Tối ưu đề xuất:

- lưu `delta` ưu tiên hơn lưu full payload khi phù hợp
- index theo `(entity, doc_id)`
- phân luồng audit nặng sang queue nếu ảnh hưởng latency

## Queue architecture

`sys_queue_job` là nền cho background processing.

Các tác vụ phù hợp:

- audit hậu xử lý
- gửi email
- webhook
- rebuild cache
- indexing

Yêu cầu thiết kế:

- retry policy
- dead-letter strategy nếu bổ sung sau
- idempotency cho job handler

## Frontend asset architecture

Volt sẽ dùng:

- `Alpine.js` cho tương tác client nhẹ
- `Tailwind CSS` cho layout và utility styling

Nguyên tắc lưu trữ:

- lưu source/vendor assets trong repo theo thư mục versioned
- tránh phụ thuộc vào CDN khi đã có bản vendored
- cho phép update version bằng script rõ ràng
- tách file build output khỏi file source nếu có pipeline build

Khuyến nghị cấu trúc:

- `frontend/` chứa package quản lý dependency
- `public/assets/vendor/alpinejs/` chứa JS vendored
- `public/assets/vendor/tailwindcss/` chứa CSS build output
- lock hoặc claim job an toàn

## Frontend architecture

Frontend mặc định của Volt là `server-rendered` với `Alpine.js`.

### Stack

- CI4 Views
- HTML semantic
- CSS nhẹ
- `Alpine.js` cho tương tác

### Alpine.js dùng cho

- toggle form section
- modal
- inline filtering nhẹ
- async submit nhẹ
- dynamic form state

### Không mặc định dùng

- SPA framework nặng
- state manager phức tạp
- build pipeline lớn nếu chưa cần

## Tận dụng built-in CodeIgniter 4

Volt phải tận dụng tối đa các thành phần có sẵn của CI4:

- `Config`
- `Routes`
- `Filters`
- `Validation`
- `Models`
- `Query Builder`
- `Migrations`
- `Cache`
- `Events`
- `Logger`
- `Exceptions`
- `Commands`
- `Security`
- `CSP`

Nguyên tắc:

- dùng built-in trước
- chỉ tự viết khi built-in không đáp ứng được kiến trúc Volt
- không duplicate framework feature

## Bảo mật

### Bề mặt tấn công chính

- form input
- metadata input
- raw SQL path
- file import/export
- auth/session
- queue payload
- admin actions

### Kiểm soát bắt buộc

- escape output
- validate input
- bind parameter
- CSRF cho web form phù hợp
- CSP
- session security
- permission check ở server
- log bảo mật

## Hiệu suất

### Nút thắt dự kiến

- đọc metadata lặp lại
- sync schema trên bảng lớn
- audit write nhiều
- permission resolution phức tạp
- queue polling

### Hướng tối ưu

- cache metadata
- preload permission matrix
- gom query
- thêm index đúng
- giảm payload response
- đẩy tác vụ nặng sang queue

## Cấu trúc thư mục mục tiêu

```text
app/
  Config/
  Controllers/
  Filters/
  Views/
core/
  Commands/
  Database/
    Migrations/
  Engine/
  Models/
  Services/
  Security/
  Support/
```

## Trạng thái hiện tại

Đã có:

- autoload `Volt\Core`
- Core migrations chạy theo namespace `Volt\Core`. Dùng `php spark volt:core-migrate` để apply schema core và `php spark volt:core-migrate-status` để kiểm tra trạng thái.
- migration base tables
- `SchemaSync`
- `volt:sync`
- `VoltMetadataCompiler` + Redis cache
- `MetadataValidator`
- `VoltModel` (abstract CI4 Model với permission + audit + system fields)
- `PermissionResolver`
- `AuditTrailWriter`
- `AuthService` + Filters (`auth`, `guest`, `apiauth`, `admin`)
- `EntityBuilderController` + Desk UI (CI4 Views + Alpine.js + Tailwind CSS)
- `ArtifactScaffolder` sinh Controller/Model/View/JS tự động

Chưa có:

- queue worker cho `sys_queue_job`
- `VoltResourceController` (API trung tâm cho entity)
- `NamingSeriesGenerator`
- Child table mode `separate` hoàn chỉnh

## Tài liệu liên quan

- [`VOLT_FRAMEWORK_RULES.md`](VOLT_FRAMEWORK_RULES.md)
- [`roadmap.md`](roadmap.md)
- [`desc-project.md`](desc-project.md)
