# Volt Framework Roadmap

## Mục tiêu

Roadmap này mô tả thứ tự dựng khung Volt Framework theo tư duy Frappe-like, nhưng triển khai trên `CodeIgniter 4`, `PostgreSQL`, `Redis`, `Alpine.js` và `Tailwind CSS`.

Ưu tiên được sắp theo thứ tự:

1. bảo mật
2. hiệu suất
3. tính đúng đắn nghiệp vụ
4. khả năng mở rộng
5. trải nghiệm quản trị

## Nguyên tắc triển khai

- Không mở rộng tính năng khi phần nền chưa an toàn.
- Không thêm UI lớn khi metadata, permission và audit chưa vững.
- Không thêm framework frontend nặng khi `Alpine.js` đủ dùng.
- Không query trực tiếp 3 bảng `sys_*` trên path nóng nếu Redis đã có payload compile.
- Tận dụng built-in của CI4 trước, chỉ tự viết khi CI4 không đáp ứng được.

## Phase 0: Chuẩn hóa nền tài liệu và quy tắc

Mục tiêu:

- thống nhất cách AI và developer đọc rule trước khi code
- giảm drift giữa ý tưởng, code và tài liệu

Hạng mục:

- hoàn thiện `VOLT_FRAMEWORK_RULES.md`
- hoàn thiện `architecture.md`
- hoàn thiện `roadmap.md`
- cập nhật `README.md` để phản ánh Volt thay vì CI4 starter
- chuẩn hóa chiến lược update asset vendored

Tiêu chí hoàn thành:

- mọi tác vụ code mới đều có thể dựa trên docs này
- AI luôn biết phải đọc file nào trước khi sửa code

## Phase 1: Metadata Compiler và Redis cache

Mục tiêu:

- gộp metadata thành một payload runtime duy nhất
- cache payload đó vào Redis
- không truy vấn lại `sys_entity`, `sys_entity_field`, `sys_entity_custom` ở request path nóng

Hạng mục:

- tạo `VoltMetadataCompiler`
- đọc dữ liệu gốc từ 3 bảng `sys_*`
- deep patch `custom_meta` JSONB vào cấu hình gốc
- build payload phẳng cho entity
- cache payload vào Redis
- tạo cache index theo entity và role
- hỗ trợ invalidate và warm cache

Ưu tiên hiệu suất:

- cache hit phải là đường đi mặc định
- payload compile phải đủ gọn để tái dùng nhanh
- warm cache trước khi traffic vào hệ thống

Ưu tiên bảo mật:

- metadata phải được sanitize trước khi compile
- cache payload không được chứa dữ liệu nhạy cảm không cần thiết

Tiêu chí hoàn thành:

- client/backend chỉ đọc Redis cho metadata đã biên dịch
- có thể invalidate một entity hoặc warm toàn bộ cache

## Phase 2: Data layer, hooks và audit

Mục tiêu:

- dựng một model lõi xử lý tất cả entity
- chuẩn hóa vòng đời document
- ghi audit trail tự động

Hạng mục:

- xây `VoltModel`
- nạp metadata từ Redis
- map rule từ metadata sang validator
- xử lý main table và child table
- triển khai hooks kiểu Frappe:
  - `before_insert`
  - `after_insert`
  - `before_submit`
  - `on_cancel`
- ghi audit theo `OLD`/`NEW` hoặc payload diff

Ưu tiên hiệu suất:

- tránh `N+1 query`
- preload metadata trước khi thao tác document
- transaction cho thao tác nhiều bước

Ưu tiên bảo mật:

- mọi ghi dữ liệu phải qua validation và permission check

Tiêu chí hoàn thành:

- document có thể insert/update/delete qua lõi chung
- hook lifecycle chạy ổn định

## Phase 3: Core controller và state machine

Mục tiêu:

- gom toàn bộ luồng API nghiệp vụ về một mối
- kiểm soát trạng thái document chặt chẽ

Hạng mục:

- xây `VoltResourceController`
- hỗ trợ route `/api/resource/{entity_name}/{id?}`
- điều phối request qua `VoltModel`
- chuẩn hóa `docstatus`
- xử lý:
  - `Draft = 0`
  - `Submitted = 1`
  - `Cancelled = 2`
- sinh naming series từ `sys_sequence`

Ưu tiên bảo mật:

- controller không tự quyết định state
- action nguy hiểm phải qua permission check

Ưu tiên hiệu suất:

- controller phải mỏng
- không chèn logic nặng vào HTTP path

Tiêu chí hoàn thành:

- API trung tâm chạy được cho nhiều entity
- state machine hoạt động nhất quán

## Phase 4: Permission engine

Mục tiêu:

- chặn quyền truy cập ngay từ lúc sinh câu lệnh SQL khi có thể
- hỗ trợ row-level và field-level security

Hạng mục:

- xây `PermissionResolver`
- cache `sys_permission` trong Redis
- xử lý token-based authentication
- can thiệp `SELECT`/`UPDATE` bằng điều kiện quyền
- giấu field không có quyền xem
- tích hợp với CI4 `Filters`

Ưu tiên bảo mật:

- không cho controller hoặc frontend tự quyết định permission
- permission phải check theo user, role, entity, state, action

Ưu tiên hiệu suất:

- cache permission matrix
- preload rule set theo user/session

Tiêu chí hoàn thành:

- thao tác dữ liệu bị chặn đúng khi không đủ quyền
- query layer nhận được điều kiện quyền phù hợp

## Phase 5: Audit trail và observability

Mục tiêu:

- theo dõi thay đổi dữ liệu quan trọng
- phục vụ truy vết và vận hành

Hạng mục:

- viết `AuditTrailWriter`
- chuẩn hóa logger và error handling
- bổ sung `sys_error_log` + `ErrorLogService` để lưu lỗi runtime vào DB
- ghi actor, action, entity, document id, timestamp, delta
- ẩn thông tin nhạy cảm ở production

Ưu tiên hiệu suất:

- cân nhắc đẩy audit nặng sang queue
- index theo `(entity, doc_id)`

Tiêu chí hoàn thành:

- thay đổi dữ liệu quan trọng có thể truy vết đầy đủ
- lỗi runtime ở các nhánh core quan trọng có thể tra cứu lại trong `sys_error_log`

## Phase 6: Child table và quan hệ dữ liệu

Mục tiêu:

- xử lý document phức tạp có bảng con

Hạng mục:

- định nghĩa rõ mode `JSONB embedded`
- định nghĩa rõ mode `separate child table`
- chuẩn hóa save/load cho child records
- làm rõ chiến lược validation và cascade

Ưu tiên hiệu suất:

- không lạm dụng JSONB cho dữ liệu cần query quan hệ nhiều

Tiêu chí hoàn thành:

- document nhiều dòng có thể thao tác nhất quán

## Phase 7: Queue và background jobs

Mục tiêu:

- đẩy tác vụ không cần đồng bộ ra nền

Hạng mục:

- viết worker cho `sys_queue_job`
- retry policy
- claim job an toàn
- idempotent handler
- cron hoặc supervisor integration sau

Ưu tiên bảo mật:

- payload job phải validate lại ở worker

Ưu tiên hiệu suất:

- giảm latency của request chính

Tiêu chí hoàn thành:

- email, webhook, audit hậu xử lý hoặc rebuild cache có thể chạy nền

## Phase 8: Dynamic UI engine bằng Alpine.js + Tailwind CSS

Mục tiêu:

- dựng UI từ metadata
- render chớp mắt, không cần build nặng

Hạng mục:

- backend biên dịch layout thành ma trận 2D
- Alpine.js renderer cho form/list
- hỗ trợ grid child table
- chuẩn hóa `volt-ui.js`
- dùng `Tailwind CSS` cho layout và utility styling

Nguyên tắc frontend:

- render server-side bằng CI4 Views khi phù hợp
- dùng `Alpine.js` cho state nhẹ
- dùng Tailwind CSS vendored trong source để dễ nâng version
- không thêm SPA framework nếu chưa bắt buộc

Tiêu chí hoàn thành:

- UI có thể dựng từ metadata mà không cần build phức tạp
- asset update được bằng file versioned trong repo

## Phase 9: Test và release quality

Mục tiêu:

- đảm bảo Volt có thể mở rộng mà không vỡ nền

Hạng mục:

- unit test cho compiler, validator, naming, permission
- integration test cho cache, schema sync, document flow
- benchmark đơn giản cho metadata-heavy path
- checklist release

Tiêu chí hoàn thành:

- thay đổi lõi có thể kiểm chứng tự động

## Backlog kỹ thuật

- multi-tenant strategy
- soft delete chuẩn hóa
- event bus nội bộ
- import/export engine
- attachment subsystem
- full-text search strategy
- cache invalidation strategy chi tiết
- API module cho admin và integration

## Ưu tiên thực tế đề xuất ngay lúc này

Nếu bắt đầu code tiếp từ trạng thái repo hiện tại, thứ tự hợp lý là:

1. cập nhật `README.md`
2. hoàn thiện `VoltMetadataCompiler`
3. tích hợp Redis cache và invalidation
4. tạo `MetadataValidator`
5. nâng cấp `SchemaSync` với sanitize + dry-run + logging
6. tạo `VoltModel`
7. tạo `VoltResourceController`
8. tạo `PermissionResolver`
9. thêm audit trail tự động
10. làm UI quản trị bằng CI4 Views + Alpine.js + Tailwind CSS

## Tài liệu liên quan

- [`VOLT_FRAMEWORK_RULES.md`](VOLT_FRAMEWORK_RULES.md)
- [`architecture.md`](architecture.md)
- [`desc-project.md`](desc-project.md)
