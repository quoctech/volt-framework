# Volt Framework — Toàn tập

> Metadata-driven ERP engine on CodeIgniter 4 + PostgreSQL + Alpine.js

---

## Mục lục

1. [Tổng quan](#1-tổng-quan)
2. [Cấu trúc thư mục core](#2-cấu-trúc-thư-mục-core)
3. [Database — Hệ thống bảng sys_*](#3-database--hệ-thống-bảng-sys_)
4. [Metadata System](#4-metadata-system)
5. [Engine Layer](#5-engine-layer)
6. [Models](#6-models)
7. [Controllers](#7-controllers)
8. [Auth & Security](#8-auth--security)
9. [Audit & Logging](#9-audit--logging)
10. [Commands](#10-commands)
11. [File/Attachment System](#11-fileattachment-system)
12. [Awesome Bar](#12-awesome-bar)
13. [Multilingual](#13-multilingual)
14. [Role & Permission](#14-role--permission)
15. [Routes](#15-routes)
16. [Entity Builder — UI](#16-entity-builder--ui)

---

## 1. Tổng quan

Volt là ERP engine `metadata-driven`: thay vì viết migration và code CRUD thủ công cho từng bảng, developer (hoặc admin) định nghĩa entity và field trong giao diện Entity Builder, engine tự động:

- Đồng bộ schema PostgreSQL (`SchemaSync`)
- Compile metadata và cache vào Redis (`VoltMetadataCompiler`)
- Sinh Controller, Model, View, Alpine JS (`ArtifactScaffolder`)
- Cung cấp REST API CRUD tự động (`VoltResourceController`)
- Quản lý quyền truy cập động (`PermissionResolver`)
- Ghi audit trail tự động (`AuditTrailWriter`)

### Stack

| Layer | Công nghệ |
|-------|-----------|
| Backend | PHP 8.2+, CodeIgniter 4.7 |
| Database | PostgreSQL 15+ |
| Cache | Redis (metadata, permission) |
| Frontend | Server-rendered HTML + Alpine.js + Tailwind CSS |

### Nguyên tắc thiết kế

1. **Security first** — validate mọi input, permission check ở server, escape output
2. **Performance first** — cache metadata, batch query, tránh N+1
3. **Built-in first** — tận dụng CI4 trước khi tự viết
4. **Metadata-driven with guardrails** — metadata không bypass được validation

---

## 2. Cấu trúc thư mục core

```
core/
  Audit/              Audit trail writer
  Auth/               Authentication, filters, user management
  AwesomeBar/         Quick search & navigation
  Commands/           CLI spark commands
  Config/             System config, language packs
  Controllers/        Core controllers (File, etc.)
  Database/           DB connection, migrations, seeds, TableNameResolver
  docs/               Documentation
  Engine/             Core engines (SchemaSync, MetadataCompiler)
  Metadata/           Entity builder, artifact scaffolder, resource controller
  Models/             Core models (VoltModel, FileModel)
  Role/               Role management
  Security/           Permission resolver
  System/             System status, settings, error logs
  Validation/         Metadata validator
```

Namespace: `Volt\Core` → `core/` (registered in `app/Config/Autoload.php`)

---

## 3. Database — Hệ thống bảng sys_*

### Core tables

| Bảng | Vai trò |
|------|---------|
| `sys_entity` | Định nghĩa entity metadata gốc |
| `sys_entity_field` | Định nghĩa field và thứ tự |
| `sys_entity_custom` | JSONB patch tùy biến metadata |
| `sys_user` | Người dùng |
| `sys_permission` | Ma trận quyền động |
| `sys_sequence` | Bộ đếm sinh mã tự động |
| `sys_audit_trail` | Nhật ký thay đổi |
| `sys_queue_job` | Hàng đợi tác vụ nền |
| `sys_module` | Danh mục module runtime |
| `sys_role` | Danh mục role |
| `sys_awesome_bar` | Index điều hướng & search |
| `sys_setting` | Cấu hình runtime |
| `sys_error_log` | Nhật ký lỗi runtime |
| `sys_file` | File đính kèm |
| `sys_note` | Ghi chú |

### System columns

Mọi bảng entity (`tab_*`) đều có các cột hệ thống:

| Column | Type | Mô tả |
|--------|------|-------|
| `name` | VARCHAR(100) PK | Document ID / primary key |
| `docstatus` | SMALLINT DEFAULT 0 | 0=Draft, 1=Submitted, 2=Cancelled |
| `owner` | VARCHAR(100) | Người tạo |
| `creation` | TIMESTAMP | Thời điểm tạo |
| `modified` | TIMESTAMP | Thời điểm sửa cuối |

Child table (`istable=1`) có thêm:

| Column | Type | Mô tả |
|--------|------|-------|
| `parent` | VARCHAR(100) | FK đến record cha |
| `parentfield` | VARCHAR(100) | Tên field child table trên entity cha |
| `parenttype` | VARCHAR(100) | Tên entity cha |
| `idx` | INTEGER | Thứ tự dòng |

### Table name convention

- Entity: `tab_` + `snake_case(entity_name)` — ví dụ `tab_employee`
- System: `sys_` + `snake_case(name)` — ví dụ `sys_entity`
- Resolver: `Volt\Core\Database\TableNameResolver`

---

## 4. Metadata System

### 4.1 Entity lifecycle

```
Entity Builder UI → EntityBuilderService::saveEntity()
  ├─ Upsert sys_entity, sys_entity_field, sys_entity_custom
  ├─ SchemaSync::syncEntity() → CREATE/ALTER TABLE
  ├─ VoltMetadataCompiler::compileEntity() → compile + cache
  ├─ EntityMetadataCache::invalidateAll()
  └─ ArtifactScaffolder::scaffoldEntity() → sinh file
```

### 4.2 Metadata sources

| Nguồn | Bảng | Dữ liệu |
|-------|------|---------|
| Entity config | `sys_entity` | name, module, label, autoname, istable, issingle, states, custom_attributes |
| Field definitions | `sys_entity_field` | fieldname, label, fieldtype, options, reqd, read_only, hidden, idx, f_custom_jsonb |
| Custom patches | `sys_entity_custom` | entity_name, apply_to_role, custom_meta (JSONB deep-merge) |

### 4.3 Compiler output

`VoltMetadataCompiler::compileEntity()` trả về:

```php
[
  'entity'       => [...],       // entity config đã normalize
  'fields'       => [...],       // field_map keyed by fieldname
  'field_order'  => [...],       // ordered list of fieldnames
  'main_fields'  => [...],       // fieldnames không phải child table
  'child_fields' => [...],       // fieldnames là child table
  'child_tables' => [...],       // map fieldname → {child_entity, storage}
  'custom_patch' => [...],       // merged custom_meta từ sys_entity_custom
  'cache'        => [...],       // cache metadata
  'source'       => [...],       // raw data snapshot
  'derived'      => [...],       // derived indexes (required, hidden, readonly)
]
```

### 4.4 Cache strategy

- Two-layer cache:
  - **Redis** (`EntityMetadataCache`): key `volt:metadata:{name}`, TTL configurable
  - **Compiler cache** (CI4 `Services::cache()`): key `volt_metadata_entity_v1_{name}_{role}`
- Cache invalidation:
  - `VoltMetadataCompiler::invalidateEntity('EntityName')` — xóa entity khỏi cache
  - `EntityMetadataCache::invalidateAll()` — flush toàn bộ metadata cache

### 4.5 Normalize entity name

Mọi entity name được lưu **lowercased** (thường là snake_case) trong `sys_entity.name`. Hàm `normalizeEntityName()` ở nhiều class dùng chung pattern:

```php
$name = preg_replace('/(?<!^)[A-Z]/', '_$0', $name); // CamelCase → snake_case
$name = strtolower(trim($name));
$name = preg_replace('/[^a-z0-9_]+/', '_', $name);
```

### 4.6 Field types

| Type | PostgreSQL | Mô tả |
|------|-----------|-------|
| `Input` | VARCHAR(n) | Ô nhập liệu ngắn |
| `Data` | VARCHAR(n) | Chuỗi ngắn |
| `Int` | INTEGER | Số nguyên |
| `Float` | NUMERIC(18,4) | Số thập phân |
| `Currency` | NUMERIC(18,4) | Tiền tệ |
| `Text` | TEXT | Nội dung dài |
| `Code` | TEXT | Mã code |
| `Check` | SMALLINT | Checkbox 0/1 |
| `Date` | DATE | Ngày |
| `Datetime` | TIMESTAMP | Ngày giờ |
| `Time` | TIME | Giờ |
| `Email` | VARCHAR(255) | Email |
| `Phone` | VARCHAR(32) | Số điện thoại |
| `URL` | VARCHAR(2048) | URL |
| `Password` | VARCHAR(255) | Mật khẩu |
| `Select` | VARCHAR(255) | Dropdown |
| `MultiSelect` | JSONB | Multi-select |
| `JSON` | JSONB | JSON |
| `Link` | VARCHAR(100) | FK đến entity khác |
| `Table` | JSONB / separate | Child table (embedded hoặc separate) |
| `Child Table (JSONB)` | JSONB | Child table embedded |
| `Attach` | VARCHAR(100) | File UUID |
| `Attach Image` | VARCHAR(100) | Image UUID |

### 4.7 Child table modes

Field type `Table` hỗ trợ hai chế độ lưu trữ:

- **Embedded JSONB** (mặc định): Child rows lưu trong JSONB column của parent
- **Separate table** (thêm `:separate` vào options): Mỗi child entity là một bảng vật lý riêng (`tab_*`) với `parent`, `parentfield`, `parenttype`, `idx` columns

Cấu hình options: `"EmployeeEducation:separate"` means child entity `employeeeducation`, storage mode `separate_table`.

---

## 5. Engine Layer

### 5.1 SchemaSync

**File:** `core/Engine/SchemaSync.php`
**Dependency:** `MetadataValidator`, `TableNameResolver`

Chức năng:
- Đọc metadata từ `sys_entity_field`
- CREATE TABLE nếu bảng chưa tồn tại
- ALTER TABLE ADD COLUMN nếu còn thiếu cột
- Tự động sync child table entities (separate mode)
- Map field types → PostgreSQL column types

Flow:
```
syncEntity(entityName)
  ├─ normalizeEntityName
  ├─ isChildEntity (check istable flag)
  ├─ doSyncEntity(entityName, isChild)
  │   ├─ getPostgresSchema → information_schema.columns
  │   ├─ If no table → CREATE TABLE with CORE_COLUMNS/CHILD_COLUMNS + field columns
  │   ├─ If table exists → ALTER TABLE ADD COLUMN for missing base + field columns
  │   └─ If not child → scan Table:separate fields → recursive sync child entities
  └─ return {status, logs}
```

### 5.2 VoltMetadataCompiler

**File:** `core/Engine/VoltMetadataCompiler.php`
**Dependency:** `MetadataValidator`, CI4 Cache, DB

Chức năng:
- Compile entity metadata từ 3 bảng `sys_*` thành một payload thống nhất
- Deep-patch custom meta qua `sys_entity_custom`
- Cache vào CI4 cache handler (Redis)
- Hỗ trợ role-specific variants
- Cache index để invalidation theo entity

Key methods:
| Method | Mô tả |
|--------|-------|
| `compileEntity(name, role?, forceRefresh?)` | Compile + cache một entity |
| `warmAll(role?, forceRefresh?)` | Warm cache cho tất cả entities |
| `invalidateEntity(name, role?)` | Xóa cache một entity |

### 5.3 EntityMetadataCache

**File:** `core/Metadata/EntityMetadataCache.php`

Layer cache riêng biệt dùng direct Redis (không qua CI4 abstraction). Dùng cho đường chạy nóng của `VoltResourceController`.

Key methods:
| Method | Mô tả |
|--------|-------|
| `get(entityName)` | Lấy metadata từ Redis |
| `set(entityName, data)` | Ghi metadata vào Redis |
| `delete(entityName)` | Xóa một entity khỏi cache |
| `invalidateAll()` | Clear toàn bộ metadata cache |

---

## 6. Models

### 6.1 VoltModel (abstract)

**File:** `core/Models/VoltModel.php`
**Extends:** `CodeIgniter\Model`

Model lõi cho mọi entity. Tự động:
- Gắn system fields (`owner`, `creation`, `modified`, `docstatus`)
- Kiểm tra permission trước CRUD (`PermissionResolver`)
- Ghi audit trail (`AuditTrailWriter`)
- Xử lý child table records (save/load/delete)
- Normalize JSON fields

**Lifecycle hooks:**
```
beforeInsert → voltBeforeInsert (permission check, normalize)
  → insert
  → afterInsert → voltAfterInsert (audit write)

beforeUpdate → voltBeforeUpdate (permission, snapshot before)
  → update
  → afterUpdate → voltAfterUpdate (audit delta)

beforeDelete → voltBeforeDelete (permission, snapshot)
  → delete (cascade child records)
  → afterDelete → voltAfterDelete (audit)

beforeFind → voltBeforeFind (permission check)
```

**Child table handling:**

`VoltModel` tự động:
- `extractChildData()` — tách child rows khỏi payload chính
- `stripChildData()` — loại bỏ child arrays trước khi ghi parent
- `saveChildRecords()` — delete cũ → batch insert mới (trong transaction)
- `attachChildRecords()` — load child rows khi `find()`
- `deleteChildRecords()` — cascade delete khi xóa parent

**Usage example trong module:**
```php
final class EmployeeModel extends VoltModel
{
    protected $table = 'tab_employee';
    protected $primaryKey = 'name';
    protected $returnType = 'array';
    protected $useAutoIncrement = false;
    protected $protectFields = false;
    protected $allowedFields = [];

    public function __construct()
    {
        parent::__construct();
        $this->setEntityName('Employee');
    }
}
```

### 6.2 FileModel

**File:** `core/Models/FileModel.php`
**Table:** `sys_file`

Chức năng:
- CRUD file records
- `findByEntity(entity, name, field?)` — tìm files theo entity binding
- `deleteByEntity(entity, name, field?)` — xóa files + file trên disk
- `deleteFileWithRecord(name)` — xóa record + unlink file

---

## 7. Controllers

### 7.1 VoltResourceController

**File:** `core/Metadata/Controllers/VoltResourceController.php`

Controller REST trung tâm, sinh tự động trong module route. Xử lý CRUD cho mọi entity qua `VoltModel`.

| Method | Route | Mô tả |
|--------|-------|-------|
| `restIndex` | GET `/{module}/api/{entity}` | List + pagination + search |
| `restShow` | GET `/{module}/api/{entity}/load/{name}` | Load một record |
| `restStore` | POST `/{module}/api/{entity}/save` | Tạo mới |
| `restUpdate` | POST `/{module}/api/{entity}/save` | Cập nhật (nếu có name) |
| `restDestroy` | POST `/{module}/api/{entity}/delete/{name}` | Xóa |

Response format:
- List: `{data: [...], meta: {page, per_page, total, total_pages}}`
- Load: `{data: {...}}`
- Create: HTTP 201 `{data: {name: "..."}}`
- Update: `{message: "Record updated.", data: {name: "..."}}`
- Delete: HTTP 204 (no content)
- Error: `{status: "error", message: "..."}`

Route auto-generation trong `ArtifactScaffolder::buildModuleRoutesFile()`:
```php
$routes->get('api/{entity}', 'VoltResourceController::restIndex/$1');
$routes->get('api/{entity}/load/(:segment)', 'VoltResourceController::restShow/$1/$2');
$routes->post('api/{entity}/save', 'VoltResourceController::restStore/$1');
$routes->post('api/{entity}/delete/(:segment)', 'VoltResourceController::restDestroy/$1/$2');
```

### 7.2 FileController

**File:** `core/Controllers/FileController.php`

| Method | Route | Mô tả |
|--------|-------|-------|
| `upload` | POST `/api/file/upload` | Upload file (multipart) |
| `download` | GET `/api/file/download/{uuid}` | Download/serve file |
| `delete` | POST `/api/file/delete/{uuid}` | Xóa file + record |
| `listByEntity` | GET `/api/file/list/{entity}/{name}/{field?}` | List files by entity binding |

Upload request: multipart với field `file` + optional `attached_to_entity`, `attached_to_name`, `attached_to_field`.

File storage: `writable/uploads/YYYY/MM/{uuid}.{ext}`

MIME validation: images, PDF, Office docs, text, CSV, zip, JSON, XML (configurable via `ALLOWED_MIME_TYPES`).
Max file size: 10MB (`MAX_FILE_SIZE`).

---

## 8. Auth & Security

### 8.1 AuthService

**File:** `core/Auth/Services/AuthService.php`

Chức năng:
- Login/Logout với session-based authentication
- Setup initial admin
- Change password
- API token authentication (bearer token, 7 days TTL)
- API Key/Secret authentication (dùng cho admin integration)
- Brute-force protection (5 attempts → 15 min lock)

Key methods:
| Method | Mô tả |
|--------|-------|
| `login(username, password)` | Xác thực, trả AuthEntity |
| `setupInitialAdmin(username, password)` | Tạo admin đầu tiên |
| `currentUser()` | Lấy user từ session |
| `logout()` | Xóa session |
| `changePassword(current, new)` | Đổi mật khẩu |
| `issueApiToken(user)` | Tạo API token mới |
| `authenticateApiToken(token)` | Xác thực bằng token |
| `generateApiKeySecret(user)` | Tạo api_key + api_secret |
| `authenticateApiKeySecret(token)` | Xác thực bằng api_key:api_secret |

### 8.2 Filters

| Filter | File | Mô tả |
|--------|------|-------|
| `auth` | `core/Auth/Filters/PageAuthFilter.php` | Session auth → redirect /login nếu chưa login |
| `apiauth` | `core/Auth/Filters/ApiAuthFilter.php` | Bearer token auth cho API |
| `admin` | `core/Auth/Filters/AdminFilter.php` | Yêu cầu admin role |
| `guest` | `core/Auth/Filters/GuestFilter.php` | Chỉ guest mới được truy cập |

### 8.3 UserEntity

**File:** `core/Auth/Entities/UserEntity.php`
**Properties:** `name`, `password`, `roles`, `user_metadata`, `is_active`, `failed_login_attempts`, `locked_until`, `last_login_at`, `api_key`, `api_token_hash`, `api_token_expires_at`, `api_secret_hash`

Key methods:
| Method | Mô tả |
|--------|-------|
| `isAdmin()` | Kiểm tra admin role |
| `isActive()` | Kiểm tra active status |
| `hasRole(role)` | Kiểm tra có role cụ thể không |

### 8.4 User Management

Controllers: `AuthController` (login/logout/setup/profile/api) + `UserController` (CRUD users)
Routes trong `app/Config/Routes.php` — group `/desk/users` với filter `admin`.

---

## 9. Audit & Logging

### 9.1 AuditTrailWriter

**File:** `core/Audit/AuditTrailWriter.php`
**Table:** `sys_audit_trail`

Chức năng:
- Ghi delta khi dữ liệu thay đổi
- Tự động diff `before` vs `after` → chỉ ghi các field thay đổi
- Tự động resolve actor name

**Usage:**
```php
$writer = service('voltAuditTrailWriter');
$writer->write('Employee', 'E-2024-00001', 'create', [], $newData);
$writer->write('Employee', 'E-2024-00001', 'update', $before, $after);
```

**audit_payload format:**
```json
{
  "before": {...},
  "after": {...},
  "changes": {
    "fieldname": {"before": "old", "after": "new"}
  }
}
```

### 9.2 ErrorLogService

**File:** `core/System/Services/ErrorLogService.php`
**Table:** `sys_error_log`

Chức năng:
- Ghi lỗi runtime vào DB (bên cạnh CI4 logger)
- `write(level, message, context, channel?)` — lỗi đã chuẩn hóa
- `logException(Throwable, context?, channel?, code?)` — khi đang cầm exception

Service alias: `voltErrorLog`

---

## 10. Commands

### volt:sync

Đồng bộ schema cho entity từ metadata:
```bash
php spark volt:sync Employee     # Sync một entity
php spark volt:sync --all         # Sync tất cả entities
```

### volt:scaffold

Sinh artifact code cho entity:
```bash
php spark volt:scaffold Employee  # Sinh cho một entity
php spark volt:scaffold --all     # Sinh cho tất cả
```

Sinh ra:
- `Entities/{Entity}/{entity}.json` — compiled metadata snapshot
- `Entities/{Entity}/{Entity}.php` — hook class
- `Entities/{Entity}/{entity}_list.js` — Alpine list component
- `Entities/{Entity}/{entity}_form.js` — Alpine form component
- `Models/{Entity}Model.php` — VoltModel subclass
- `Views/{entity}_list.php` — list view
- `Views/{entity}_form.php` — form view
- `Config/Routes.php` — module routes (regenerated)

### volt:core-migrate / volt:core-migrate-status

```bash
php spark volt:core-migrate           # Chạy migration core
php spark volt:core-migrate-status    # Kiểm tra trạng thái
```

### volt:clean-entities

Quét và xóa entity artifact dư thừa (tương tác y/n).

### sync:awesome-bar

Đồng bộ Awesome Bar index từ entities.

---

## 11. File/Attachment System

### sys_file table

| Column | Type | Mô tả |
|--------|------|-------|
| `name` | VARCHAR(100) PK | UUID của file |
| `file_name` | VARCHAR(500) | Tên file gốc |
| `file_path` | TEXT | Path relative to `writable/uploads/` |
| `file_size` | BIGINT | Dung lượng bytes |
| `file_type` | VARCHAR(255) | MIME type |
| `attached_to_entity` | VARCHAR(100) | Entity name (VD: "employee") |
| `attached_to_name` | VARCHAR(100) | Record name (VD: "E-2024-00001") |
| `attached_to_field` | VARCHAR(100) | Field name (VD: "photo") |
| `is_private` | SMALLINT | 1=private, 0=public |

### Field types

- `Attach` — file input, lưu UUID string (VARCHAR(100))
- `Attach Image` — file input với `accept="image/*"`, lưu UUID string

### Form rendering (Alpine.js)

Trong form view, `Attach`/`Attach Image` fields render:
- Nếu có giá trị: link download `View {uuid_prefix}...`
- File input để upload file mới
- Upload tự động qua AJAX khi chọn file
- `form[fieldname + '__uploading']` flag hiển thị trạng thái

### Routes

```php
POST /api/file/upload
GET  /api/file/download/{uuid}
POST /api/file/delete/{uuid}
GET  /api/file/list/{entity}/{name}/{field?}
```

---

## 12. Awesome Bar

**Namespace:** `Volt\Core\AwesomeBar`

Chức năng: Quick search và navigation cho Desk UI.

**Components:**
- `AwesomeBarController` — endpoint `/api/awesome-bar/search`
- `AwesomeBarModel` — query `sys_awesome_bar` index
- `SyncAwesomeBar` command — rebuild index từ entities

Route: `GET /api/awesome-bar/search` (filter `auth`)

---

## 13. Multilingual

**File:** `core/Config/Lang/LangService.php`

Chức năng:
- File-based language packs (`en.php`, `vi.php`)
- Auto-resolve từ session → DB setting → default `'en'`
- Dot notation access: `LangService::get('common.save')`
- Interpolation: `LangService::get('entity_count', ['count' => 5])`

### Usage in views:
```php
$lang = \Volt\Core\Config\Lang\LangService::load();
echo $lang['nav']['system_settings'];

// Hoặc direct:
echo \Volt\Core\Config\Lang\LangService::get('common.save');
```

### Adding new language:
1. Tạo `core/Config/Lang/{code}.php`
2. Thêm code vào `SUPPORTED_LANGS` constant trong `LangService.php`
3. Định nghĩa đủ các key

---

## 14. Role & Permission

### Role management

**Controllers:** `RoleController` (CRUD roles), `RolePermissionController` (quản lý permission cho role)
**Model:** `RoleModel`, `RolePermissionModel`
**Entity:** `RoleEntity`
**Routes:** group `/desk/roles` (filter `admin`)

### PermissionResolver

**File:** `core/Security/PermissionResolver.php`

Chức năng:
- Role-based permission matrix từ `sys_permission`
- Cache matrix trong Redis (TTL 5 min)
- Hỗ trợ entity-level, state-level, action-level, field-level
- Admin bypasses all checks

**Usage:**
```php
$resolver = service('voltPermissionResolver');
$resolver->can('employee', 'read');           // Entity-level
$resolver->can('employee', 'write', 'Draft'); // State-level
$resolver->can('employee', 'read', null, 'salary'); // Field-level
```

**sys_permission table structure:**
| Column | Type | Mô tả |
|--------|------|-------|
| `role` | VARCHAR(100) | Role name |
| `entity` | VARCHAR(100) | Entity name (hoặc `*` cho tất cả) |
| `state` | VARCHAR(100) | Document state (hoặc `*`) |
| `actions` | JSONB | `{read: 1, write: 1, create: 1, delete: 1, submit: 1}` |
| `field_permissions` | JSONB | Field-level overrides |

---

## 15. Routes

File: `app/Config/Routes.php`

### Public / Guest

| Route | Method | Controller | Filter |
|-------|--------|------------|--------|
| `/login` | GET/POST | AuthController | guest |
| `/setup` | POST | AuthController | guest |
| `/logout` | POST | AuthController | auth |
| `/api/login` | POST | AuthController::apiLogin | — |
| `/api/me` | GET | AuthController::apiMe | apiauth |

### Desk (authenticated)

| Route | Controller | Filter |
|-------|------------|--------|
| `/` | EntityBuilderController::desk | auth |
| `/desk` | EntityBuilderController::desk | auth |
| `/desk/entities` | EntityBuilderController::entityList | auth |
| `/desk/profile` | AuthController::profile | auth |
| `/desk/profile` (POST) | AuthController::updateProfile | auth |
| `/desk/profile/generate-api-key` (POST) | AuthController::generateApiKey | auth |

### Admin

| Route | Controller | Filter |
|-------|------------|--------|
| `/desk/entity-builder` | EntityBuilderController::index | admin |
| `/desk/create-module` | EntityBuilderController::modulePage | admin |
| `/desk/users/*` | UserController | admin |
| `/desk/roles/*` | RoleController, RolePermissionController | admin |
| `/desk/system-status` | SystemStatusController | admin |
| `/desk/system-settings` | SystemSettingController | admin |
| `/api/entity-builder/*` | EntityBuilderController | admin |

### API

| Route | Controller | Filter |
|-------|------------|--------|
| `/api/awesome-bar/search` | AwesomeBarController | auth |
| `/api/file/upload` | FileController::upload | auth |
| `/api/file/download/{uuid}` | FileController::download | auth |
| `/api/file/delete/{uuid}` | FileController::delete | auth |
| `/api/file/list/{entity}/{name}/{field?}` | FileController::listByEntity | auth |

### Module routes (auto-generated)

Mỗi module có file `Config/Routes.php` riêng, sinh bởi `ArtifactScaffolder`.

Ví dụ module `hrms`:
```php
$routes->group('hrms', ['filter' => 'auth'], function (RouteCollection $routes): void {
    // List
    $routes->get('employee', 'EmployeeController::index/$1');
    $routes->get('employee/create', 'EmployeeController::create/$1');
    $routes->get('employee/edit/(:segment)', 'EmployeeController::edit/$1/$2');

    // API
    $routes->get('api/employee', 'VoltResourceController::restIndex/$1');
    $routes->get('api/employee/load/(:segment)', 'VoltResourceController::restShow/$1/$2');
    $routes->post('api/employee/save', 'VoltResourceController::restStore/$1');
    $routes->post('api/employee/delete/(:segment)', 'VoltResourceController::restDestroy/$1/$2');
});
```

---

## 16. Entity Builder — UI

### Pages

| Route | Mô tả |
|-------|-------|
| `/desk/entity-builder` | Entity builder (admin) |
| `/desk/create-module` | Tạo module mới |
| `/desk/entities` | Entity list (auth) |
| `/` + `/desk` | Desk home (auth) |

### Entity Builder features

- Session-based layout (multi-column form, tối đa 4 cột)
- Drag-and-drop field ordering
- Inspector panel cho field properties
- Link target selector cho Link fields
- Entity picker cho Table/Child Table (JSONB) fields
- Preview in list view
- Field type dropdown (18 types)
- Save with Ctrl+S
- Auto-naming pattern (HASH, Custom series)

### Artifacts generated on save

1. **JSON** — compiled metadata snapshot
2. **PHP Hook class** — `beforeInsert`, `beforeSave`, `validate`, `afterInsert`, `afterSave`, `onUpdate`
3. **Alpine JS** — list component + form component
4. **PHP Views** — list template + form template
5. **Model** — VoltModel subclass
6. **Routes** — module Config/Routes.php (regenerated)

### Hook methods

```php
public function beforeInsert(array $data): array      // Modify data before insert
public function beforeSave(array $data): array        // Modify data before insert + update
public function validate(array $data): void            // Business validation (throw on error)
public function afterInsert(array $data, array $context): void  // Post-insert
public function afterSave(array $data, array $context): void    // Post-save
public function onUpdate(array $data, array $context): void     // Post-update
```

### Permissions

- Desk / Entity List: filter `auth`
- Entity Builder + Create Module: filter `admin`
- CRUD API (module routes): filter `auth`

---

## Tham khảo

- [VOLT_FRAMEWORK_RULES.md](VOLT_FRAMEWORK_RULES.md)
- [architecture.md](architecture.md)
- [desc-project.md](desc-project.md)
- [entity-builder.md](entity-builder.md)
- [multilingual.md](multilingual.md)
- [roadmap.md](roadmap.md)
