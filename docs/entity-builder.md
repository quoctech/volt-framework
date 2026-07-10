# Entity Builder

Entity Builder là giao diện metadata trong Volt để tạo module, entity và sinh artifact code vào `app/Modules/...`.

## Mục tiêu

- Tách rõ `Create Module` và `Entity Builder` theo luồng làm việc của framework.
- Tạo entity nhanh mà không phải viết migration thủ công.
- Sinh file JSON/PHP cho DocType trong app để dev tiếp tục gắn logic nghiệp vụ.
- Giữ metadata gọn, nhưng vẫn đủ để core sync schema và cache metadata.

## Route chính

- `GET /`: tự chuyển vào `/desk`
- `GET /desk`: màn hình vào việc, điều hướng sang `Create Module` hoặc `Entity Builder`.
- `GET /desk/create-module`: tạo module mới.
- `GET /desk/entity-builder`: dựng entity trong module đã có.
- `POST /api/entity-builder/module/save`: lưu metadata module và scaffold thư mục module vào app.
- `GET /api/entity-builder/load/{entity_name}`: nạp metadata entity.
- `POST /api/entity-builder/save`: lưu metadata entity, sync schema, sinh artifact entity.

## Luồng sử dụng

1. Mở `GET /desk`.
2. Tại `Desk`, có thể xem `Entity List` và lọc entity theo module.
3. Vào `Create Module`, nhập tên module theo `snake_case`, ví dụ `sales`.
4. Hệ thống sẽ tạo:
   - bản ghi `sys_module`
   - thư mục `app/Modules/{ModuleStudly}`
   - `module.json`
   - `Config/Routes.php`
5. Vào `Entity Builder`.
6. Vào `Entity Settings`, nhập `Entity name`, `Label`, `Module`, `Naming Rule ID`.
7. Chuyển qua tab `Entity`, tạo session để nhóm field.
8. Bấm `Add Field`, chọn datatype từ dropdown.
9. Với mỗi session, bấm `...` để chèn session trên/dưới hoặc thêm cột, tối đa `4` cột.
10. Chọn từng field để chỉnh thuộc tính ở inspector bên phải, gồm cả cột hiển thị nếu session có nhiều cột.
11. Tick `In list view` ở field nào thì field đó mới xuất hiện trên `Entity List`.
12. Dùng `Load` để nạp lại entity có sẵn.
13. Bấm `Save` hoặc `Ctrl+S`.

## Artifact được sinh

Sau khi lưu entity, core sẽ sinh lại các file sau trong app:

- `app/Modules/{ModuleStudly}/DocTypes/{EntityStudly}/{entity_name}.json`
- `app/Modules/{ModuleStudly}/DocTypes/{EntityStudly}/{EntityStudly}.php`
- `app/Modules/{ModuleStudly}/DocTypes/{EntityStudly}/{entity_name}_list.js`
- `app/Modules/{ModuleStudly}/Models/{EntityStudly}Model.php`
- `app/Modules/{ModuleStudly}/Controllers/{EntityStudly}Controller.php`
- `app/Modules/{ModuleStudly}/Views/{entity_name}_list.php`
- `app/Modules/{ModuleStudly}/Views/{entity_name}_form.php`
- `app/Modules/{ModuleStudly}/Config/Routes.php`

File JSON là snapshot metadata đã compile.
File PHP là hook class để dev app thêm validation hay business logic.
File JS là Alpine component cho màn hình list/form của entity.
Route module sẽ tự sinh URL list theo dạng `/{module}/{entity}`. Ví dụ `employee` trong module `hrms` sẽ có `GET /hrms/employee`.
Route create sẽ là `GET /{module}/{entity}/create`.
Route edit sẽ là `GET /{module}/{entity}/edit/{name}`.

## Entity CRUD

Core sẽ scaffold sẵn CRUD cho từng entity:

- `Entity List` có tìm kiếm và phân trang.
- `Create Item` để tạo record mới.
- `Edit` để sửa record hiện có.
- `Delete` để xóa record.

Tùy chọn phân trang hiện hỗ trợ:

- `50`
- `100`
- `200`
- `500`
- `1000`
- `2500`

## Hook DocType

Class hook của entity hiện có sẵn các điểm nối sau:

- `beforeInsert(array $data): array`
- `beforeSave(array $data): array`
- `validate(array $data): void`
- `afterInsert(array $data, array $context = []): void`
- `afterSave(array $data, array $context = []): void`
- `onUpdate(array $data, array $context = []): void`

## Session và field custom

- Session không có bảng riêng.
- Danh sách session được lưu trong `sys_entity.custom_attributes -> layout.sessions`.
- Mỗi session có `column_count` để builder dựng layout nhiều cột.
- Metadata custom của từng field được lưu trong `sys_entity_custom.custom_meta.fields.{fieldname}`.
- `session_uid` và `column` của field cũng được lưu trong patch này để builder dựng lại layout.

## Data type hỗ trợ

Builder hiện hỗ trợ các datatype sau:

- `Input`
- `Int`
- `Float`
- `Data`
- `Text`
- `Check`
- `Date`
- `Select`
- `Code`
- `Table`

## Mapping lưu trữ

Kiểu vật lý hiện tại được sync theo `Volt\Core\Engine\SchemaSync`:

| Data type | Postgres type | Ghi chú |
| --- | --- | --- |
| `Input` | `VARCHAR(length)` | Ô nhập liệu ngắn kiểu Frappe |
| `Int` | `INTEGER` | Số nguyên |
| `Float` | `NUMERIC(18, 4)` | Số thập phân |
| `Data` | `VARCHAR(length)` | Chuỗi ngắn |
| `Text` | `TEXT` | Nội dung dài |
| `Check` | `SMALLINT` | Cờ `0/1` |
| `Date` | `DATE` | Ngày |
| `Select` | `VARCHAR(255)` | Một giá trị chọn |
| `Code` | `TEXT` | Nội dung code |
| `Table` | `JSONB` | Child table embedded |

## Quy ước cho options

- `Select`: bắt buộc có option, nhập danh sách option bằng xuống dòng hoặc dấu phẩy.
- `Table`: bắt buộc có option, nhập tên child entity. Có thể thêm `:separate` nếu muốn sync bảng con riêng.

## Validation

Core validator đang chặn các giá trị không hợp lệ sau:

- `Entity name` phải theo `snake_case` hoặc ký tự chữ/số/`_`.
- `Module` phải theo snake_case thường.
- `Fieldname` phải theo snake_case thường.
- `Field type` phải nằm trong danh sách cho phép.
- `Select` và `Table` bắt buộc có `options`.

## Sync

Sau khi lưu entity, core sẽ:

1. Upsert `sys_entity`
2. Upsert `sys_entity_field`
3. Upsert `sys_entity_custom`
4. Gọi `SchemaSync` để sync bảng vật lý Postgres
5. Compile metadata và ghi vào Redis
6. Sinh lại artifact entity trong `app/Modules/...`
