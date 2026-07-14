# VOLT Framework Rules

## Mục đích

Đây là file quy chuẩn bắt buộc cho mọi lần viết, sửa, review hoặc sinh code bằng AI trong dự án Volt Framework.

Mục tiêu của file này:

- ép mọi thay đổi phải bám đúng kiến trúc Volt
- ưu tiên bảo mật và hiệu suất trước tính tiện
- tận dụng tối đa built-in của CodeIgniter 4
- giữ frontend nhẹ, ít phụ thuộc, dùng `Alpine.js`
- giảm drift giữa ý tưởng, code và tài liệu

## Quy trình bắt buộc cho AI và developer

Trước khi viết code, AI hoặc developer phải thực hiện theo thứ tự sau:

1. Đọc file này trước.
2. Đọc [`architecture.md`](architecture.md) để hiểu kiến trúc tổng thể.
3. Đọc [`roadmap.md`](roadmap.md) để biết ưu tiên triển khai hiện tại.
4. Đọc code liên quan trước khi sửa.
5. Chỉ bắt đầu code khi thay đổi không vi phạm các quy tắc bên dưới.

Nếu có xung đột:

1. `Security`
2. `Performance`
3. `Correctness`
4. `Maintainability`
5. `Convenience`

## Nguyên tắc cốt lõi

### 1. Security first

- Không hard-code mật khẩu, token, secret, DSN, API key, salt, private key.
- Mọi cấu hình nhạy cảm phải đi qua `.env` và lớp config của CI4.
- Mọi input từ request, CLI, file import, queue payload, webhook đều là `untrusted input`.
- Không trust metadata do người dùng khai báo nếu chưa validate.
- Không render thẳng dữ liệu người dùng ra HTML nếu chưa escape.
- Không dùng raw SQL nếu Query Builder hoặc binding của CI4 đáp ứng được.
- Nếu buộc phải dùng raw SQL thì phải bind parameter. Không nối chuỗi trực tiếp với input.
- Mọi hành vi đọc/ghi dữ liệu nghiệp vụ phải đi qua tầng authz và kiểm tra permission.
- Audit trail là yêu cầu mặc định cho thay đổi dữ liệu quan trọng.

### 2. Performance first

- Không chấp nhận thay đổi tạo ra `N+1 query`.
- Ưu tiên batch query, preload metadata, cache lookup và giảm round-trip tới database.
- Chỉ lấy các cột cần dùng, không `SELECT *` nếu không có lý do rõ ràng.
- Ưu tiên index đúng cho khóa tra cứu, join, sort và filter.
- Với dữ liệu metadata ít thay đổi, ưu tiên cache ở layer phù hợp.
- Giảm parse và bootstrap không cần thiết ở request path nóng.
- Frontend phải nhẹ, tránh bundle JS lớn khi `Alpine.js` là đủ.

### 3. Built-in first

Ưu tiên dùng sẵn của CodeIgniter 4 trước khi tự viết:

- `Model`, `BaseModel`, `Entity`, `Validation`
- `Filters` cho auth/authz/cors/throttle
- `Query Builder`
- `Migrations`, `Seeds`
- `Cache`
- `Events`
- `Logger`
- `Exceptions`
- `Security`, `CSRF`, `ContentSecurityPolicy`
- `Request`, `Response`, `API Response Trait`
- `Commands` qua `spark`
- `Pager`, `Localization`, `Email` nếu phù hợp

Chỉ tự xây lại khi built-in không đáp ứng được yêu cầu kiến trúc của Volt. Nếu không dùng built-in, phải có lý do kỹ thuật rõ ràng.

### 4. Metadata-driven nhưng có kiểm soát

- Volt là framework `configuration-driven`, nhưng metadata không được phép bypass guardrail kỹ thuật.
- Mọi metadata entity/field/permission phải được validate bằng schema nội bộ trước khi lưu và trước khi sync.
- Không cho phép metadata sinh ra SQL nguy hiểm, tên cột không hợp lệ, hoặc kiểu dữ liệu không nằm trong whitelist.
- Sync schema phải idempotent, có log, có khả năng kiểm tra trước khi apply nếu luồng đó được bổ sung sau.
- Metadata runtime phải đi qua `VoltMetadataCompiler` và cache vào Redis thay vì đọc trực tiếp 3 bảng `sys_*` trên path nóng.

## Quy tắc backend

### 1. Kiến trúc lớp

- `Controller` chỉ nhận request, gọi service/use case, trả response.
- Không đặt business logic trong controller.
- Logic metadata, schema sync, naming, permission, audit, queue phải ở `core/`.
- Truy cập dữ liệu nghiệp vụ phải đi qua model hoặc service tập trung.
- Mỗi class chỉ nên có một trách nhiệm chính.

### 2. Chuẩn mã nguồn PHP

- Dùng `declare(strict_types=1);` cho file PHP mới.
- Tuân thủ `PSR-12`, `PSR-4`.
- Class và namespace dùng `PascalCase`.
- Method và variable dùng `camelCase`.
- Tên bảng, cột, key dữ liệu dùng `snake_case`.
- Không dùng magic string lặp lại; chuyển thành `const`.
- Không tạo helper rác hoặc abstraction vô ích.

### 3. Database và PostgreSQL

- Tên bảng nghiệp vụ sinh từ entity phải deterministic và sanitize được.
- Tên cột sinh từ metadata phải qua whitelist regex.
- Dùng transaction cho các thao tác ghi nhiều bước cần tính nhất quán.
- Với JSONB:
  - chỉ dùng khi phù hợp với đặc tính dữ liệu
  - không lạm dụng JSONB để né thiết kế quan hệ
- Tạo index cho cột lookup chính, foreign key logic, audit lookup và queue status.
- Khi thay đổi schema, phải cân nhắc lock, downtime và backward compatibility.

### 4. Validation và permission

- Validate ở cả metadata layer lẫn document data layer.
- `reqd`, `read_only`, `hidden`, `fieldtype`, `options`, `state` phải có quy tắc xử lý rõ.
- Permission phải check theo:
  - user
  - roles
  - entity
  - document state
  - action
- Không check permission ở frontend rồi xem như đủ.

### 5. Logging, audit và lỗi

- Mọi lỗi hệ thống phải được log qua logger của CI4.
- Không lộ stack trace hoặc SQL nhạy cảm ra response production.
- Thao tác dữ liệu quan trọng phải có audit trail.
- Audit phải lưu đủ:
  - actor
  - entity
  - document id
  - action
  - timestamp
  - delta

## Quy tắc frontend

### 1. Frontend stack

- Frontend mặc định của Volt là:
  - server-rendered HTML từ CI4 view
  - `Alpine.js` cho tương tác phía client
- `Tailwind CSS` được vendored trong repo để dễ nâng version và giảm phụ thuộc CDN
- Không mặc định kéo React/Vue/SPA nếu chưa có lý do rõ ràng.
- Alpine.js được dùng cho:
  - toggle UI
  - modal
  - dropdown
  - inline validation state
  - small component state
  - async interaction nhẹ

### 2. Frontend security

- Escape output theo cơ chế view của CI4.
- Không chèn HTML từ dữ liệu người dùng nếu chưa sanitize rõ ràng.
- Form ghi dữ liệu phải đi qua CSRF protection của CI4 khi phù hợp.
- Không lưu token nhạy cảm trong localStorage nếu có lựa chọn an toàn hơn.

### 3. Frontend performance

- Tránh JS bundle lớn.
- Ưu tiên progressive enhancement.
- Không render dữ liệu thừa ra DOM.
- CSS và JS chỉ nạp ở page cần dùng nếu có thể.

## Quy tắc tận dụng built-in CodeIgniter 4

### 1. Config

- Cấu hình phải nằm ở `app/Config` hoặc `.env`, không rải trong code.
- Không duplicate config mà CI4 đã có.

### 2. Filters

- Dùng `Filters` cho xác thực, phân quyền, rate limiting, CORS, CSRF policy.
- Không nhúng logic cross-cutting lặp lại trong controller.

### 3. Validation

- Ưu tiên `Validation` của CI4 trước khi tự viết validator riêng.
- Chỉ viết custom rule khi rule built-in không đủ.

### 4. Caching

- Ưu tiên cache abstraction của CI4 cho metadata và lookup hay dùng.
- Cache key phải có namespace rõ ràng và có chiến lược invalidation.

### 5. CLI

- Tác vụ vận hành dùng `spark command`.
- Không tạo script rời nếu CI4 command giải quyết được.

## Danh sách cấm

- Cấm hard-code secret.
- Cấm query trong vòng lặp nếu có thể gom query.
- Cấm business logic trong controller.
- Cấm viết feature mới bằng raw PHP khi CI4 đã có built-in phù hợp.
- Cấm thêm dependency frontend nặng cho tác vụ mà Alpine.js xử lý được.
- Cấm bypass validation metadata.
- Cấm bypass permission check ở thao tác ghi dữ liệu.
- Cấm sửa schema production theo kiểu phá hủy dữ liệu mà không có kế hoạch rõ ràng.

## Checklist trước khi merge hoặc giao AI tiếp tục code

- Đã đọc file rules này.
- Đã đối chiếu với `architecture.md`.
- Đã đối chiếu với `roadmap.md`.
- Không có hard-coded secret hoặc config nhạy cảm.
- Không có `N+1 query`.
- Đã ưu tiên built-in của CI4.
- Frontend dùng Alpine.js nếu chỉ cần tương tác nhẹ.
- Có validation.
- Có permission check ở luồng ghi dữ liệu.
- Có log và audit nếu thay đổi dữ liệu quan trọng.
- Có đánh giá tác động hiệu suất.

## Ghi chú áp dụng

Nếu một yêu cầu của AI hoặc developer mâu thuẫn với file này, phải ưu tiên sửa hướng tiếp cận thay vì phá rule. Chỉ được lệch rule khi có lý do kỹ thuật rõ ràng, được ghi lại trong thay đổi tương ứng và không làm giảm security hoặc performance của hệ thống.
