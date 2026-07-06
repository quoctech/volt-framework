# ⚡ VOLT FRAMEWORK - ARCHITECTURE & CODING RULES

Tài liệu này quy định các nguyên tắc thiết kế hệ thống, an ninh dữ liệu và quy chuẩn mã nguồn tối cao bắt buộc phải tuân thủ khi phát triển hoặc mở rộng bộ lõi (Core Engine) cũng như các Module nghiệp vụ trên Volt Framework.

---

## 🔐 1. NGUYÊN TẮC BẢO MẬT TỐI CAO (SECURITY FIRST)

*   **Tuyệt đối không Hard-code thông tin nhạy cảm:** Mọi thông tin cấu hình môi trường, tài khoản, mật khẩu kết nối Database, khóa bí mật (Secret Key), cổng dịch vụ (Port) bắt buộc phải nằm trong file `.env` và được nạp động thông qua cấu hình hệ thống.
*   **Chống SQL Injection tuyệt đối:** 
    *   Tất cả các câu lệnh truy vấn thô (`$this->db->query()`) bắt buộc phải sử dụng cơ chế Binding Parameter (dạng `?` hoặc đặt tên biến `:name:`).
    *   Không bao giờ được phép cộng chuỗi trực tiếp từ biến đầu vào (Input) vào câu lệnh SQL.
*   **Xác thực và phân quyền đa tầng:**
    *   Mọi Request đi vào hệ thống bắt buộc phải đi qua tầng Middleware để kiểm tra danh tính dựa trên bảng `sys_user`.
    *   Trước khi thực thi bất kỳ lệnh đọc/ghi nào xuống các bảng vật lý (`db_`), hệ thống phải kiểm tra ma trận quyền động tại bảng `sys_permission` dựa theo đúng Trạng thái (`state`) hiện tại của chứng từ.

---

## 🧼 2. TIÊU CHUẨN MÃ NGUỒN SẠCH (CLEAN CODE PHP NATIVE)

*   **Tuân thủ nghiêm ngặt chuẩn PSR:** Toàn bộ mã nguồn phải viết đúng theo tiêu chuẩn PSR-12 (Coding Style) và PSR-4 (Autoloading Namespace).
*   **Khai báo kiểu dữ liệu tường minh (Strict Typing):** 
    *   Bắt buộc khai báo `declare(strict_types=1);` ở đầu mọi file PHP để kiểm soát chặt chẽ kiểu dữ liệu, tránh lỗi ép kiểu ngầm.
    *   Mọi thuộc tính của Class, tham số đầu vào và giá trị trả về của Function bắt buộc phải định nghĩa kiểu dữ liệu (Type Hinting) rõ ràng (Ví dụ: `public function syncEntity(string $entityName): array`).
*   **Đặt tên có nghĩa (Meaningful Names):** 
    *   Tên Class và Namespace dùng `PascalCase` (Ví dụ: `SchemaSync`, `VoltModel`).
    *   Tên Function và Biến dùng `camelCase` (Ví dụ: `getPostgresSchema`, `tableName`).
    *   Tên trường dữ liệu và tên bảng vật lý dùng `snake_case` (Ví dụ: `product_name`, `sys_entity_field`).
*   **Triệt tiêu hoàn toàn vấn nạn N+1 Query:** 
    *   Tuyệt đối không được phép đặt câu lệnh truy vấn SQL (`SELECT`, `query()`) bên trong vòng lặp (`foreach`, `while`, `for`) để bốc dữ liệu liên quan.
    *   **Giải pháp bắt buộc:** Sử dụng giải pháp gom mảng ID (`whereIn()`), nạp trước dữ liệu liên quan (Eager Loading), hoặc tận dụng các trường lưu trữ mảng lồng cấu trúc `JSONB` của Postgres để bốc toàn bộ dữ liệu chỉ trong **1 câu lệnh SQL duy nhất**, giải phóng băng thông kết nối cho Database Server.

---

## 💎 3. QUẢN LÝ HẰNG SỐ - TUYỆT ĐỐI KHÔNG HARD-CODE (NO MAGIC STRINGS)

*   **Định nghĩa hằng số cho giá trị lặp lại:** Khi một giá trị chuỗi (String) hoặc số (Integer) xuất hiện từ 2 lần trở lên trong cùng một Class hoặc có tính chất đại diện hệ thống, bắt buộc phải định nghĩa nó thành hằng số (`const`).
*   **Áp dụng triệt để cho tên bảng và trạng thái:** 
    *   Tên các bảng hệ thống bắt buộc phải quản lý bằng hằng số ở đầu Class để khi cần bảo trì chỉ sửa đúng một chỗ.
    ```php
    const T_ENTITY = 'sys_entity';
    const T_FIELD  = 'sys_entity_field';
    ```
    *   Các trạng thái cốt lõi của hệ thống cũng phải dùng hằng số:
    ```php
    const DOC_DRAFT     = 0;
    const DOC_SUBMITTED = 1;
    const DOC_CANCELLED = 2;
    ```

---

## 🔄 4. TÁI SỬ DỤNG MÃ NGUỒN (DRY - DON'T REPEAT YOURSELF)

*   **Tái sử dụng cấu hình môi trường:** Tận dụng tối đa các thuộc tính và hàm có sẵn của Framework gốc (CodeIgniter 4) thay vì viết lại các hàm tiện ích trùng lặp. Ví dụ: Sử dụng `$this->db->DBPrefix` để lấy tiền tố bảng động từ `.env`.
*   **Phân rã Function đơn nhiệm (Single Responsibility):** Một hàm chỉ làm đúng một việc duy nhất và không dài quá 50 dòng code. Nếu phát hiện một đoạn logic tính toán (như map kiểu dữ liệu, định dạng chuỗi) lặp lại ở nhiều nơi, bắt buộc phải tách ra thành một hàm dùng chung (Helper/Utility Function).
*   **Tập trung hóa xử lý dữ liệu:** Mọi hành động tương tác với dữ liệu (Đọc, ghi, kiểm tra hợp lệ, ghi vết thanh tra Audit Trail) bắt buộc phải đi qua Model lõi tập trung `VoltModel`, không viết logic nghiệp vụ (Business Logic) rải rác ở tầng Controller.
