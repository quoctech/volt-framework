# Volt Framework — Toàn tập

> Metadata-driven ERP engine on CodeIgniter 4 + PostgreSQL + Alpine.js

---

## Mục lục

1. [Tổng quan](#1-tổng-quan)
2. [Cấu trúc thư mục core](#2-cấu-trúc-thư-mục-core)
3. [Database — Hệ thống bảng sys_*](#3-database--hệ-thống-bảng-sys_)
   - [3.3 QueryParser](#33-queryparser)
4. [Metadata System](#4-metadata-system)
5. [Engine Layer](#5-engine-layer)
6. [Workflow Engine](#6-workflow-engine)
7. [Models](#7-models)
8. [Controllers](#8-controllers)
9. [Auth & Security](#9-auth--security)
10. [Audit & Logging](#10-audit--logging)
11. [Events & Event Bus](#11-events--event-bus)
12. [Commands](#12-commands)
13. [File/Attachment System](#13-fileattachment-system)
14. [Awesome Bar](#14-awesome-bar)
15. [Multilingual](#15-multilingual)
16. [Role & Permission](#16-role--permission)
17. [Routes](#17-routes)
18. [Entity Builder — UI](#18-entity-builder--ui)
19. [Pages (Custom Pages)](#19-pages-custom-pages)
20. [Multi-tenancy](#20-multi-tenancy)
21. [Workspace](#21-workspace)

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
  Workspace/          User workspace & block engine
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
| `sys_audit_trail` | Nhật ký thay đổi (append-only, hash-chain) |
| `sys_audit_chain` | Singleton giữ `last_hash`/`last_id` của chuỗi hash audit |
| `sys_queue_job` | Hàng đợi tác vụ nền |
| `sys_module` | Danh mục module runtime |
| `sys_page` | Custom page metadata (HTML/CSS/JS, roles, route) |
| `sys_role` | Danh mục role |
| `sys_awesome_bar` | Index điều hướng & search |
| `sys_setting` | Cấu hình runtime |
| `sys_error_log` | Nhật ký lỗi runtime |
| `sys_workflow` | Định nghĩa workflow cho entity |
| `sys_workflow_state` | Trạng thái workflow |
| `sys_workflow_action` | Hành động workflow (submit, approve, reject, ...) |
| `sys_workflow_transition` | Chuyển tiếp workflow (from_state → action → to_state) |
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

### 3.3 QueryParser

**File:** `core/Database/QueryParser.php`

Parser query parameters từ REST API (`restIndex`) — filter, sort, phân trang, field selection. Tích hợp với `PermissionResolver` để tự động loại bỏ field mà user không có quyền read.

**Usage:**
```php
$query = new QueryParser(
    builder: $model->builder(),
    entityName: 'employee',
    permissionResolver: service('voltPermissionResolver'),
    compiledMeta: $compiler->compileEntity('employee'),
);

$result = $query->apply($request->getGet());
// $result = ['builder' => BaseBuilder, 'total' => int, 'page' => int, 'perPage' => int]
$rows = $result['builder']->get()->getResultArray();
```

**Query parameters:**

| Param | Format | Ví dụ |
|-------|--------|-------|
| `filters` | JSON array of tuples `[field, op, value]` | `[["status","=","Active"],["age",">=",18]]` |
| `fields` | Comma-separated hoặc JSON array | `name,status,age` hoặc `["name","status"]` |
| `order_by` | `field dir` | `creation desc` |
| `page` | Integer ≥ 1 | `2` |
| `per_page` | One of: 10, 20, 50, 100, 200, 500, 1000, 2500 | `20` |
| `q` | Free-text search (LIKE trên string fields) | `john` |

**Supported operators:**

| Operator | Mô tả |
|----------|-------|
| `=` | Equals |
| `!=` | Not equals |
| `>` / `>=` / `<` / `<=` | Comparison |
| `like` / `not like` | Pattern match (CI4 builder) |
| `in` / `not in` | Array membership (value là JSON array) |
| `between` | Range (value là `[from, to]`) |

**Security:**
- Field names được validate chỉ cho phép `[a-zA-Z_][a-zA-Z0-9_]*` và phải tồn tại trong compiled metadata hoặc system fields
- Operator phải thuộc whitelist
- Values được truyền qua CI4 parameterized queries (không concatenate vào SQL)
- Fields filter tự động loại bỏ field không có quyền `read`

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
| Field definitions | `sys_entity_field` | fieldname, label, fieldtype, options, reqd, read_only, hidden, idx |
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
- Tính toán **plan** thay đổi schema (delta) mà không đụng tới DB trước
- CREATE TABLE nếu bảng chưa tồn tại
- ALTER TABLE ADD COLUMN nếu còn thiếu cột
- Phát hiện và plan đổi kiểu cột (an toàn khi mở rộng VARCHAR/NUMERIC, phá vỡ khi thu hẹp)
- Phát hiện cột dư (orphan) — chỉ xóa khi bật `--prune`
- Đổi tên cột theo bản đồ `--renames` — chỉ khi bật `--allow-rename`
- Tự động đổi tên bảng legacy (giữ dữ liệu)
- Tạo index từ `custom_attributes.indexes` (idempotent)
- Tự động sync child table entities (separate mode)
- Map field types → PostgreSQL column types
- Ghi mỗi thao tác đã apply vào bảng `sys_schema_migration`
- **Dry-run mặc định**: gọi `syncEntity(name, opts)` với `dry_run=true` chỉ trả về plan, không thay đổi DB

API:
```php
// Trả về {status, message, logs, plan, dry_run}
$result = $sync->planEntity('Employee', $opts);       // chỉ tính toán
$result = $sync->syncEntity('Employee', $opts);        // dry_run=false → apply

// $opts:
//   dry_run          => true  (mặc định chỉ plan, không apply)
//   allow_type_change=> true  cho phép đổi kiểu cột (phá vỡ)
//   allow_rename     => true  cho phép đổi tên cột
//   renames          => ['old' => 'new', ...]
//   prune / allow_drop => true cho phép xóa cột dư
```

Flow:
```
planEntity(entityName, opts)
  ├─ normalizeEntityName
  ├─ isChildEntity (check istable flag)
  ├─ doPlanEntity(entityName, isChild, ...)
  │   ├─ getPostgresSchema → information_schema.columns
  │   ├─ If no table → op CREATE TABLE với base columns + field columns
  │   ├─ If table exists → op ADD COLUMN cho cột thiếu + so sánh kiểu cột
  │   ├─ planRenames / planOrphanDrops / planIndexes
  │   └─ If not child → scan Table:separate fields → recursive sync child entities
  └─ return {status, logs, plan}
syncEntity = planEntity + applyPlan (nếu !dry_run)
```

**Production guard:** trong môi trường production (`isProductionEnv()`), các operation phá hủy (`drop_column`, `drop_index`, `drop_constraint`) bị **chặn** trừ khi `volt.schemaSyncAllowDirectDropInProduction = true` (config `app/Config/Volt.php`, env `volt.schemaSyncAllowDirectDropInProduction`) — plan sẽ báo lỗi thay vì generate drop op. `EntityBuilderService::deleteEntity()` cũng gọi `assertDropAllowedInProduction()`: ở production, delete entity mà không bật cờ này → ném `InvalidArgumentException` (an toàn mặc định).

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

## 6. Workflow Engine

### 6.1 Tổng quan

Workflow Engine quản lý vòng đời document (Draft → Submitted → Cancelled) cho các entity có flag `is_submittable = true`. Hỗ trợ cả implicit workflow (mặc định) và custom workflow với nhiều trạng thái, hành động, chuyển tiếp.

**Nguyên tắc:**
- Mỗi entity có thể có 0 hoặc 1 workflow đang active
- workflow state được lưu trong cột `workflow_state` của bảng dữ liệu (VARCHAR(100), mặc định `'Draft'`)
- docstatus mapping: 0=Draft, 1=Submitted, 2=Cancelled
- Khi không có custom workflow, implicit workflow được dùng (Draft → Submitted → Cancelled)

### 6.2 Database tables

| Table | Vai trò |
|-------|---------|
| `sys_workflow` | Định nghĩa workflow cho entity |
| `sys_workflow_state` | Danh sách trạng thái trong workflow |
| `sys_workflow_action` | Hành động chuẩn (submit, approve, reject, send_back, cancel, amend) |
| `sys_workflow_transition` | Chuyển tiếp hợp lệ (from → action → to) |

### 6.3 WorkflowEngine

**File:** `core/Engine/WorkflowEngine.php`
**Service name:** `voltWorkflowEngine`

Key methods:
| Method | Mô tả |
|--------|-------|
| `getWorkflow(entityName)` | Lấy custom workflow active của entity (null nếu không có) |
| `getImplicitWorkflow(entityName)` | Lấy workflow mặc định 3 trạng thái |
| `getTransitions(workflow, state)` | Các chuyển tiếp khả dụng từ state hiện tại |
| `applyTransition(entity, doc, action, comment?)` | Thực thi chuyển tiếp, cập nhật docstatus + workflow_state |
| `canTransition(workflow, from, action)` | Kiểm tra xem chuyển tiếp có hợp lệ không |
| `getStates(entityName)` | Danh sách states cho entity |
| `isSubmittable(entityName)` | Kiểm tra entity có hỗ trợ workflow không |

### 6.4 VoltModel workflow methods

VoltModel bổ sung 5 methods cho workflow:

```php
$model->submit($id, $comment = null);   // Submit → docstatus=1, workflow_state='Submitted'
$model->approve($id, $comment = null);  // Approve → docstatus=1, workflow_state='Approved'
$model->cancel($id, $comment = null);   // Cancel → docstatus=2, workflow_state='Cancelled'
$model->amend($id, $comment = null);    // Amend → tạo bản copy mới, docstatus=0, workflow_state='Draft'
$model->assertDocumentEditable($id);    // Throw nếu doc không editable ở state hiện tại
$model->assertWorkflowTransition($id, $action);  // Throw nếu action không hợp lệ từ state hiện tại
```

Mọi workflow transition đều được ghi vào `sys_audit_trail` với action `workflow:submit`, `workflow:approve`, `workflow:cancel`, hoặc `workflow:amend` — kể cả khi không có comment. Comment (nếu có) chỉ nằm trong `delta.after.comment`.

### 6.5 Audit trail format

Mỗi workflow transition được ghi vào `sys_audit_trail` với cấu trúc:

| Column | Giá trị |
|--------|---------|
| `entity` | Tên entity (VD: `leave`) |
| `doc_id` | Document name |
| `action` | `workflow:submit`, `workflow:approve`, `workflow:cancel`, `workflow:amend` |
| `changed_by` | Actor name (từ `voltAuth->currentUser()` hoặc `'system'`) |
| `delta` | JSON: `{before: {workflow_state}, after: {workflow_state, comment}}` |
| `changed_at` | Timestamp (mặc định `CURRENT_TIMESTAMP`) |

### 6.6 API endpoints (auto-generated)

Mỗi entity có `is_submittable = true` được sinh thêm 4 API routes:

| Method | Route | Controller |
|--------|-------|------------|
| POST | `/{module}/api/{entity}/submit/{id}` | `VoltResourceController::restSubmit` |
| POST | `/{module}/api/{entity}/approve/{id}` | `VoltResourceController::restApprove` |
| POST | `/{module}/api/{entity}/cancel/{id}` | `VoltResourceController::restCancel` |
| POST | `/{module}/api/{entity}/amend/{id}` | `VoltResourceController::restAmend` |

Các endpoint đều nhận JSON body tùy chọn với field `comment` (string).

**Activity (audit history của một record):**

| Method | Route | Controller |
|--------|-------|------------|
| GET | `/{module}/api/{entity}/activity/{id}` | `VoltResourceController::activity` |

Trả về `{status, entity, doc_id, items[], total}` — mỗi `item` gồm action, category, changed_by, changed_at, tenant, request_id và `delta` (before/after/changes). Dùng cho tab **Activity** ở chân màn hình Edit của record.

### 6.7 Cấu hình Entity Builder

Trong Entity Builder (Settings tab), checkbox **Submittable**:
- Khi bật, entity được đánh dấu `is_submittable = true`
- SchemaSync tự động thêm cột `workflow_state` vào bảng vật lý
- ArtifactScaffolder sinh routes + model methods hỗ trợ workflow
- Implicit workflow được kích hoạt mặc định

### 6.8 Custom workflow

Workflow custom được định nghĩa qua migration seed:

```sql
INSERT INTO sys_workflow (name, entity, label) VALUES ('employee_wf', 'employee', 'Employee Workflow');
INSERT INTO sys_workflow_state (name, workflow, label, docstatus, allow_edit, is_final, idx) VALUES
  ('Draft', 'employee_wf', 'Draft', 0, 1, 0, 0),
  ('Pending Approval', 'employee_wf', 'Pending Approval', 0, 0, 0, 1);
INSERT INTO sys_workflow_transition (name, workflow, from_state, to_state, action, idx) VALUES
  ('submit_draft', 'employee_wf', 'Draft', 'Pending Approval', 'submit', 0);
```

Khi custom workflow tồn tại và `is_active = 1`, engine ưu tiên dùng custom workflow thay vì implicit.

---

## 7. Models

### 7.1 VoltModel (abstract)

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
  → delete (soft delete hoặc cascade theo mode)
  → afterDelete → voltAfterDelete (audit)

beforeFind → voltBeforeFind (permission check)
```

**Soft delete (7.1a):**

Mọi bảng entity có cột `deleted_at TIMESTAMP NULL` (do `SchemaSync.baseColumns()` thêm, kể cả bảng cũ khi chạy `php spark volt:sync --all`).

- Mặc định: **xóa mềm** — `delete()` set `deleted_at`, `find()/findAll()` tự loại bỏ (CI4 `useSoftDeletes`).
- Entity Settings có checkbox **"Xóa thẳng"** (`custom_attributes.hard_delete = true`): khi bật, `delete()` xóa vật lý như trước. Setting lưu trong `sys_entity.custom_attributes` (JSONB), có hiệu lực ngay nhờ cache invalidation sẵn có.
- Child rows (mode separate table) xóa/khôi phục cùng parent khi bảng child có cột `deleted_at`.
- `restore(string $id): bool` — reset `deleted_at` (parent + children); trả `false` nếu entity đang ở chế độ xóa thẳng.
- CI4 built-in: `delete($id, true)` / `purgeDeleted()` xóa vật lý; `withDeleted()`/`onlyDeleted()` bao gồm bản ghi đã xóa mềm.
- Query Builder không tự filter `deleted_at` → đã thêm filter thủ công tại: `QueryParser::applySoftDeleteFilter()` (REST list), `VoltResourceController::applyDeletedFilter()` (`data()` + `linkOptions()`), `WorkspaceController::applyDeletedFilter()` (count/list).
- API: `POST api/{entity}/restore/{id}`, `POST api/{entity}/delete/{id}?purge=1`.
- Lưu ý: CI4 không filter `deleted_at` khi `update()` — update bản ghi đã xóa mềm vẫn thành công.

**Workflow-protected fields (7.1b):**

Trong `update()`, `VoltModel` gọi `stripWorkflowProtectedFields()` để **loại bỏ** `workflow_state` và `docstatus` khỏi payload trước khi update — user không thể tự ý sửa state workflow qua REST save; state chỉ thay đổi qua transition hợp lệ của `WorkflowService`. Đây là guard bổ sung (defense-in-depth) bên cạnh permission check.

**Child table handling:**

`VoltModel` tự động:
- `extractChildData()` — tách child rows khỏi payload chính
- `stripChildData()` — loại bỏ child arrays trước khi ghi parent
- `saveChildRecords()` — delete cũ → batch insert mới (trong transaction)
- `attachChildRecords()` — load child rows khi `find()`
- `deleteChildRecords()` / `softDeleteChildRecords()` — cascade theo chế độ xóa của entity

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

### 7.2 FileModel

**File:** `core/Models/FileModel.php`
**Table:** `sys_file`

Chức năng:
- CRUD file records
- `findByEntity(entity, name, field?)` — tìm files theo entity binding
- `deleteByEntity(entity, name, field?)` — xóa files + file trên disk
- `deleteFileWithRecord(name)` — xóa record + unlink file

---

## 8. Controllers

### 8.1 VoltResourceController

**File:** `core/Metadata/Controllers/VoltResourceController.php`

Controller REST trung tâm, sinh tự động trong module route. Xử lý CRUD cho mọi entity qua `VoltModel`.

| Method | Route | Mô tả |
|--------|-------|-------|
| `restIndex` | GET `/{module}/api/{entity}` | List + filter + sort + pagination + field selection (xem query params bên dưới) |
| `restShow` | GET `/{module}/api/{entity}/load/{name}` | Load một record |
| `restStore` | POST `/{module}/api/{entity}/save` | Tạo mới |
| `restUpdate` | POST `/{module}/api/{entity}/save` | Cập nhật (nếu có name) |
| `restDestroy` | POST `/{module}/api/{entity}/delete/{name}` | Xóa |

**restIndex query parameters** (xử lý bởi `QueryParser`):

| Param | Format | Ví dụ |
|-------|--------|-------|
| `filters` | JSON array `[field, op, value]` | `[["status","=","Active"]]` |
| `fields` | Comma-separated hoặc JSON array | `name,status` |
| `order_by` | `field dir` | `creation desc` |
| `page` | Integer ≥ 1 | `2` |
| `per_page` | 10 / 20 / 50 / 100 / 200 / 500 / 1000 / 2500 | `20` |
| `q` | Free-text search | `john` |

Operators: `=`, `!=`, `>`, `>=`, `<`, `<=`, `like`, `not like`, `in`, `not in`, `between`.

Field selection tự động loại bỏ field không có quyền `read`. Tất cả giá trị được truyền qua parameterized query (chống SQL injection).

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

### 8.2 FileController

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

**Authorization:** mọi method (`upload`, `download`, `delete`, `listByEntity`) đều yêu cầu authenticated user (`currentUser()`), không còn method nào public. File attachment validation dựa trên field catalog (chống attach vào field không tồn tại).

### 8.3 HealthController

**File:** `core/System/Controllers/HealthController.php`

| Method | Route | Mô tả |
|--------|-------|-------|
| `index` | `GET /health` | Liveness: trả `{status: "ok"}` |
| `ping` | `GET /api/ping` | Liveness API (JSON) |
| `check` | `GET /api/health` | Readiness: kiểm tra DB + Redis + disk |
| `detail` | `GET /api/health/detail` | Readiness chi tiết JSON |

- **DB check**: query `SELECT 1` (hub DB).
- **Redis check**: `service('cache')->get/set/delete` key test (cache handler mặc định Redis).
- **Disk check**: `disk_free_space(writable)` — cảnh báo nếu dưới `diskWarningThreshold` (mặc định 20%), nguy hiểm nếu dưới 5%.
- Kết quả: `{"status": "ok"|"degraded"|"fail", "checks": {db, redis, disk}, "disk_free_mb": ...}`.
- Các route health được **except khỏi rate limit toàn cục** (kèm trong alias `ratelimit` config).

---

## 9. Auth & Security

### 9.1 AuthService

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

### 9.2 Filters

| Filter | File | Mô tả |
|--------|------|-------|
| `auth` | `core/Auth/Filters/PageAuthFilter.php` | Session auth → redirect /login nếu chưa login |
| `apiauth` | `core/Auth/Filters/ApiAuthFilter.php` | Bearer token auth cho API |
| `admin` | `core/Auth/Filters/AdminFilter.php` | Yêu cầu admin role |
| `guest` | `core/Auth/Filters/GuestFilter.php` | Chỉ guest mới được truy cập |
| `ratelimit` | `core/Security/Filters/RateLimitFilter.php` | Rate limit toàn cục theo IP (default 300 req / 60s) — global before filter, except `health/ping/api/health/api/ping/api/health/detail` |
| `platform` | `core/Auth/Filters/PlatformFilter.php` | Yêu cầu platform developer (admin hoặc role `platform_developer`) — JSON-aware 401/403 |
| `correlation` | `core/Auth/Filters/CorrelationFilter.php` | Sinh/nạp `request_id` (X-Request-ID) cho toàn bộ request |

#### Rate limiting toàn cục

`RateLimitFilter` chạy ở `$globals['before']` (đăng ký alias `ratelimit` trong `app/Config/Filters.php`), dùng CI4 `Throttler` (token bucket, Redis-backed):

- Mặc định **300 request / 60s / IP**; cấu hình qua `app/Config/Volt.php` → `rateLimitGlobalAttempts` / `rateLimitGlobalWindowSeconds` (env: `volt.rateLimitGlobalAttempts`, ...).
- Key: `volt_rl_{IP}_{METHOD}`. Khi vượt ngưỡng trả **HTTP 429** kèm header `Retry-After`; JSON-aware (payload JSON cho request `api/*` hoặc `Accept: application/json`).
- Login throttle riêng của AuthService (5 attempts → 15 min lock) **giữ nguyên**, không bị ảnh hưởng.

#### HTTPS/Cookie/CSRF hardening

- `app/Config/Cookie.php`: `secure` mặc định **true** (an toàn mặc định), override qua env `cookie.secure=false` cho dev HTTP local.
- `app/Config/Security.php`: `tokenRandomize = true` (CSRF token đổi mỗi request).
- `app/Config/Session.php`: `regenerateDestroy = true` (xóa session cũ khi regenerate).
- `app/Config/App.php`: `forceGlobalSecureRequests` đọc từ env `app.forceGlobalSecureRequests`; production nên set `true` (kèm filter `forcehttps`). Dev local set `false`.
- Mẫu `.env.production.example` kèm sẵn để copy sang `.env` khi deploy.

#### 9.2.1 Debug & stack trace

- `app/Config/Exceptions.php`: `sensitiveDataInTrace` che các key nhạy cảm (password, token, authorization, db_password, X-CSRF-TOKEN, client_secret, ...) khỏi trace.
- `core/System/Handlers/VoltMaskingExceptionHandler.php`: extends `CodeIgniter\Debug\ExceptionHandler`, vá lỗi framework khi `maskSensitiveData()` truy cập frame trace thiếu `args` (tránh crash khi render 404/500). Được dùng làm handler nội bộ trong `Exceptions::handler()`.

### 9.3 UserEntity

**File:** `core/Auth/Entities/UserEntity.php`
**Properties:** `name`, `password`, `roles`, `user_metadata`, `is_active`, `failed_login_attempts`, `locked_until`, `last_login_at`, `api_key`, `api_token_hash`, `api_token_expires_at`, `api_secret_hash`

Key methods:
| Method | Mô tả |
|--------|-------|
| `isAdmin()` | Kiểm tra admin role |
| `isActive()` | Kiểm tra active status |
| `hasRole(role)` | Kiểm tra có role cụ thể không |
| `isPlatformDeveloper()` | `true` nếu admin hoặc có role `platform_developer` (role system, seed qua migration `2026-08-06-000001_AddPlatformDeveloperRole`) |

### 9.4 User Management

Controllers: `AuthController` (login/logout/setup/profile/api) + `UserController` (CRUD users)
Routes trong `app/Config/Routes.php` — group `/desk/users` với filter `admin`.

---

## 10. Audit & Logging

### 10.1 AuditTrailWriter

**File:** `core/Audit/AuditTrailWriter.php`
**Table:** `sys_audit_trail`
**Chain:** `sys_audit_chain`
**Service alias:** `voltAuditTrailWriter`

Audit trail là nhật ký vận hành (Operations) của mọi sự kiện quan trọng: auth, role/permission, API key, file, export, tenant lifecycle, metadata/schema và workflow transition. Chi tiết taxonomy đầy đủ ở `core/docs/audit.md`.

Chức năng:
- Ghi đồng bộ vào `sys_audit_trail`. Khi **`volt.strictAudit = true`** (cấu hình mặc định trong `app/Config/Volt.php`, env `volt.strictAudit`): insert thất bại → ném `RuntimeException` (**fail-closed** — audit là bắt buộc, không fallback im lặng); ngoại lệ không phải `DataException` gốc sẽ được rethrow để bảo toàn thông tin. Ngược lại (không strict) → fallback vào `sys_error_log` (không throw để không làm hỏng luồng nghiệp vụ).
- **Append-only**: trigger DB chặn mọi `UPDATE`/`DELETE` trực tiếp (chỉ `volt_audit_purge()` — hàm `SECURITY DEFINER` — được phép xóa theo retention).
- **Hash-chain chống giả mạo**: mỗi dòng mang `prev_hash` + `hash` (SHA-256, do trigger tính). Dòng đầu nối vào genesis trong `sys_audit_chain`. Tính toàn vẹn được kiểm tra bằng command `volt:audit-verify`.
- Tự động diff `before` vs `after` → chỉ ghi các field thay đổi.
- Tự động resolve `changed_by`, `tenant`, `ip_address`, `user_agent`, `request_id` qua `core/Audit/RequestContext.php`. `request_id` được sinh/tái sử dụng từ header `X-Request-ID` bởi `core/Auth/Filters/CorrelationFilter.php` để truy vết hành vi end-to-end (khớp với `sys_error_log.request_id`).

**Signature:**
```php
write(
    string $category,      // CAT_DATA | CAT_AUTH | CAT_ROLE | CAT_PERMISSION | CAT_API | CAT_FILE | CAT_EXPORT | CAT_TENANT | CAT_METADATA | CAT_WORKFLOW | CAT_SYSTEM
    string $action,        // VD: 'workflow:submit', 'auth:login', 'file:download'
    string $entity = '',   // tên entity/đối tượng, VD: 'employee', 'sys_role', 'page_desk'
    string $docId = '',    // id tài liệu/record
    array $before = [],
    array $after = [],
    ?string $changedBy = null,
    array $context = [],   // operation, status, tenant, ip_address, user_agent, request_id
    ?BaseConnection $db = null, // chỉ định connection (VD: hub DB cho sự kiện tenant)
): bool
```

**Usage:**
```php
$writer = service('voltAuditTrailWriter');
$ok = $writer->write(
    AuditTrailWriter::CAT_DATA,
    'create',
    'employee',
    'E-2024-00001',
    [],
    $newData,
);
$ok = $writer->write(
    AuditTrailWriter::CAT_WORKFLOW,
    'workflow:approve',
    'employee',
    'E-2024-00001',
    $before,
    $after,
    $actorName,
);
```

**Categories (`category` column):** `data`, `auth`, `role`, `permission`, `api`, `file`, `export`, `tenant`, `metadata`, `workflow`, `system`.

**audit_payload format (cột `delta`):**
```json
{
  "before": {...},
  "after": {...},
  "changes": {
    "fieldname": {"before": "old", "after": "new"}
  }
}
```

**Retention:** mặc định 730 ngày; xóa qua `volt:clean-audit --days=N` (dùng `volt_audit_purge(N)`).

**Verify integrity:**
```bash
php spark volt:audit-verify            # hash/chain 100% khớp → VERIFIED
php spark volt:audit-verify --genesis <hash>   # ép kiểm tra anchor đầu
php spark volt:clean-audit --dry-run   # xem số dòng sắp purge mà không xóa
php spark volt:clean-audit --days 730  # purge theo retention
```

### 10.2 ErrorLogService

**File:** `core/System/Services/ErrorLogService.php`
**Table:** `sys_error_log`

Chức năng:
- Ghi lỗi runtime vào DB (bên cạnh CI4 logger)
- `write(level, message, context, channel?)` — lỗi đã chuẩn hóa
- `logException(Throwable, context?, channel?, code?)` — khi đang cầm exception
- Mỗi dòng lỗi mang `request_id` (khớp `sys_audit_trail.request_id`) để đối chiếu lỗi với luồng nghiệp vụ đã ghi audit.
- Sau khi ghi thành công, gọi `AlertService::dispatchAlert()` để push cảnh báo ra webhook (nếu lỗi đạt ngưỡng cấu hình).

Service alias: `voltErrorLog`

### 10.3 AlertService

**File:** `core/System/Services/AlertService.php`
**Service alias:** `voltAlert`

Cảnh báo vận hành đẩy ra webhook (Discord/Slack/... ) khi hệ thống gặp lỗi nghiêm trọng, tách rời khỏi luồng ghi log (fail-open — không làm ảnh hưởng ứng dụng nếu webhook lỗi):

- **Cấu hình** (`app/Config/Volt.php`, env tương ứng): `alertWebhookUrl`, `alertWebhookSecret` (HMAC-SHA256), `alertMinLevel` (mặc định `error`).
- **Thang mức** `LEVEL_ORDER`: `debug < info < notice < warning < error < critical < alert < emergency` — chỉ dispatch khi mức lỗi >= `alertMinLevel`.
- **Signature**: header `X-Volt-Signature = sha256(hmac(secret, payload))`, timestamp + `X-Volt-Timestamp` để tránh replay.
- **Fire-and-forget**: gửi qua curl timeout 3s, không block request.
- Hàm tĩnh `dispatchAlert(level, message, context)` cho phép gọi độc lập từ bất kỳ đâu.

---

## 11. Events & Event Bus

### 11.1 EventBus

**File:** `core/Events/EventBus.php`
**File (Event):** `core/Events/Event.php`

Event Bus nội bộ cho phép các module lắng nghe và phản hồi sự kiện phát sinh từ core mà không cần can thiệp vào core code.

```php
// Listener đăng ký trong app/Config/Events.php
\CodeIgniter\Events\Events::on('pre_system', function () {
    service('voltEventBus')->listen('volt.model.*', function (Event $e) {
        log_message('info', sprintf(
            '[%s] Entity=%s ID=%s',
            $e->getName(), $e->get('entity'), $e->get('id')
        ));
    });
});

// Dispatch
service('voltEventBus')->dispatch(new Event('volt.model.created', [
    'entity' => 'employee',
    'id'     => 'E-2024-00001',
]));
```

### 11.2 Events dispatched by VoltModel

| Event name | Trigger | Payload |
|---|---|---|
| `volt.model.created` | `voltAfterInsert` | `entity`, `id`, `data` |
| `volt.model.updated` | `voltAfterUpdate` | `entity`, `id`, `data` |
| `volt.model.deleted` | `voltAfterDelete` | `entity`, `id` |
| `volt.model.submitted` | `submit()` | `entity`, `id`, `result`, `comment` |
| `volt.model.approved` | `approve()` | `entity`, `id`, `result`, `comment` |
| `volt.model.cancelled` | `cancel()` | `entity`, `id`, `result`, `comment` |
| `volt.model.amended` | `amend()` | `entity`, `old_id`, `new_id`, `record` |

### 11.3 Pattern

- Listener đăng ký qua `EventBus::listen(name, callable)` — hỗ trợ wildcard `*` (VD: `volt.model.*`)
- Event payload được set/get qua `Event::get(key)` / `Event::set(key, value)`
- EventBus dùng instance singleton (service `voltEventBus`)

---

## 12. Commands

### volt:sync

Đồng bộ schema cho entity từ metadata (mặc định **dry-run** — chỉ in plan, không đổi DB):
```bash
php spark volt:sync Employee     # Dry-run: in plan thay đổi cho Employee
php spark volt:sync --all        # Dry-run: quét tất cả entities

# Apply thật (có chủ đích, phá hủy):
php spark volt:sync Employee --prune               # Thêm: xóa cột dư (breaking)
php spark volt:sync Employee --allow-type-change   # Thêm: cho phép đổi kiểu cột (breaking)
php spark volt:sync Employee --allow-rename --renames=old_col:new_col  # Đổi tên cột

# Kiểm tra dữ liệu thực tế (không sửa schema):
php spark volt:sync Employee --data-check          # Đếm dòng, phát hiện duplicate name + child mồ côi
php spark volt:sync --all --data-check             # Kiểm tra toàn bộ entities
```
- Không có cờ phá hủy nào → chỉ tạo bảng / thêm cột an toàn.
- Mỗi thao tác được apply sẽ ghi log vào `sys_schema_migration`.
- Khi có thay đổi schema thực sự được apply, `volt:sync` tự dispatch job `rebuild_metadata_cache` vào queue để warm lại cache Redis (chạy `volt:queue-work` để xử lý).

### volt:queue-work

Xử lý job trong hàng đợi `sys_queue_job`:
```bash
php spark volt:queue-work                 # Chạy liên tục, sleep 3s khi rảnh
php spark volt:queue-work --once          # Chỉ xử lý 1 job rồi thoát
php spark volt:queue-work --queue high    # Chỉ xử lý queue "high"
php spark volt:queue-work --max-jobs 50   # Xử lý tối đa 50 job rồi thoát
php spark volt:queue-work --max-time 300  # Chạy tối đa 300 giây
php spark volt:queue-work --status        # In số job theo trạng thái
php spark volt:queue-work --retry 42      # Reset job 42 (failed/dead) về queued
php spark volt:queue-work --purge-dead --days 30  # Xóa job dead quá 30 ngày
php spark volt:queue-work --stale-requeue # Đưa job running bị treo về lại hàng đợi
```
- Dispatch: `service('voltQueue')->dispatch('job_type', $payload, ['queue' => ..., 'priority' => ...])`.
- Handler: class implement `Volt\Core\Queue\JobHandlerInterface` trong `app/QueueHandlers/` (tự discovery qua `is_subclass_of`).
- Retry backoff: `base * 2^(attempts-1)`; quá `maxAttempts` → dead-letter. Cấu hình ở `app/Config/Queue.php`.

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

### volt:register-entities

Đọc file JSON trong `app/Modules/*/Entities/*/` và đăng ký entity vào `sys_entity`, `sys_entity_field`, `sys_entity_custom`, cùng workflow (`sys_workflow`, `sys_workflow_state`, `sys_workflow_transition`):

```bash
php spark volt:register-entities
```

Chỉ dùng cho lần đầu deploy hoặc import entity từ JSON đã compiled. Bỏ qua entity đã tồn tại.

### volt:backup

Backup & restore database bằng `pg_dump` (binary format `-Fc`, nén `-Z5`):

```bash
php spark volt:backup                 # Backup hub DB (volt_enterprise)
php spark volt:backup <tenant>        # Backup tenant DB (volt_<tenant>)
php spark volt:backup --verify        # Backup + restore thử vào DB tạm để xác thực
php spark volt:backup --prune         # Backup + xóa file cũ theo retention
```

- File backup: `writable/backups/{db}_{YYYYMMDD_HHMMSS}.dump`.
- `--verify`: restore vào DB tạm `db_restoretest_<ts>` (với `--clean --if-exists --no-owner --no-privileges`), kiểm tra thành công rồi drop DB tạm.
- `--prune`: giữ tối đa `backupRetentionDays` (mặc định 30 ngày) file backup.
- Implementation: `core/System/Services/BackupService.php`, command `core/Commands/VoltBackup.php`.

### volt:purge-tenants

Xóa hẳn (hard-delete) tenant đã soft-delete quá thời gian grace:

```bash
php spark volt:purge-tenants                  # Dry-run: liệt kê tenant đủ điều kiện purge
php spark volt:purge-tenants --force          # Thực hiện purge thật
php spark volt:purge-tenants --force --name=<tenant>  # Chỉ purge 1 tenant cụ thể
```

Với mỗi tenant: **backup DB trước** (vào `writable/backups/`) → `DROP DATABASE ... WITH (FORCE)` → hard-delete record. Bỏ qua nếu backup fail.

---

## 13. File/Attachment System

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

## 14. Awesome Bar

**Namespace:** `Volt\Core\AwesomeBar`

Chức năng: Quick search và navigation cho Desk UI.

**Components:**
- `AwesomeBarController` — endpoint `/api/awesome-bar/search`
- `AwesomeBarModel` — query `sys_awesome_bar` index
- `SyncAwesomeBar` command — rebuild index từ entities

**Integration:**
- Core pages (Desk, Entity List, Entity Builder, Pages, System Status, Error Logs, ...) được seed bởi `AwesomeBarModel::seedCorePages()`
- Custom pages tự động đăng ký vào `sys_awesome_bar` khi save (`PageService::save()`) và xóa khi delete (`PageService::delete()`)

Route: `GET /api/awesome-bar/search` (filter `auth`)

---

## 15. Multilingual

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

## 16. Role & Permission

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

## 17. Routes

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
| `/` | WorkspaceController::index | auth |
| `/desk` | WorkspaceController::index | auth |
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
| `/health` | HealthController::index | — |
| `/api/health` | HealthController::check | — |
| `/api/health/detail` | HealthController::detail | — |
| `/api/ping` | HealthController::ping | — |
| `/api/awesome-bar/search` | AwesomeBarController | auth |
| `/api/file/upload` | FileController::upload | auth |
| `/api/file/download/{uuid}` | FileController::download | auth |
| `/api/file/delete/{uuid}` | FileController::delete | auth |
| `/api/file/list/{entity}/{name}/{field?}` | FileController::listByEntity | auth |
| `/api/workspace/load` | WorkspaceController::load | auth |
| `POST /api/workspace/block/save` | WorkspaceController::saveBlock | auth |
| `POST /api/workspace/block/delete` | WorkspaceController::deleteBlock | auth |
| `POST /api/workspace/block/reorder` | WorkspaceController::reorderBlocks | auth |
| `POST /api/workspace/save` | WorkspaceController::save | auth |

### Tenant lifecycle

| Route | Controller | Filter |
|-------|------------|--------|
| `/desk/tenants` (GET) | TenantController::index | admin |
| `/desk/tenants/create` (GET) | TenantController::create | admin |
| `/desk/tenants/store` (POST) | TenantController::store | admin |
| `/desk/tenants/edit/{name}` (GET) | TenantController::edit | admin |
| `/desk/tenants/update/{name}` (POST) | TenantController::update | admin |
| `/desk/tenants/delete/{name}` (POST) | TenantController::delete → soft-delete | admin |
| `/desk/tenants/trash` (GET) | TenantController::trash | admin |
| `/desk/tenants/restore/{name}` (POST) | TenantController::restore | admin |
| `/desk/tenants/purge/{name}` (POST) | TenantController::purge | admin |

### Page routes (auto-generated)

Custom pages được đăng ký động qua file `app/Config/PageRoutes.php`, sinh bởi `PageService::regeneratePageRoutes()`. File này được `require` ở cuối `app/Config/Routes.php`.

```
Route: /{route} → PageController::serve/{route}
```

Các route reserved không được dùng làm page route: `health`, `ping`, `login`, `logout`, `setup`, `desk`, `api`.

### Page management routes (platform developer)

| Route | Controller | Mô tả |
|-------|------------|-------|
| `/desk/pages` | PageController::index | Danh sách pages |
| `/desk/pages/create` | PageController::create | Tạo page mới |
| `/desk/pages/edit/{name}` | PageController::edit | Sửa page |
| `POST /api/pages/save` | PageController::save | API lưu page |
| `POST /api/pages/delete/{name}` | PageController::delete | API xóa page |

Các route trên nằm trong group `platform` (admin hoặc role `platform_developer`).

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

## 18. Entity Builder — UI

### Pages

| Route | Mô tả |
|-------|-------|
| `/desk/entity-builder` | Entity builder (admin) |
| `/desk/create-module` | Tạo module mới |
| `/desk/entities` | Entity list (auth) |
| `/` + `/desk` | Workspace home (auth) |

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

---

## 19. Pages (Custom Pages)

### 19.1 Tổng quan

Pages cho phép admin tạo custom page với HTML/CSS/JS tùy chỉnh, phục vụ qua route riêng (`/pagename`). Mỗi page có thể giới hạn quyền truy cập theo role.

**Flow:**
```
Admin UI (/desk/pages) → PageController::save()
  ├─ Upsert sys_page (DB)
  ├─ Scaffold file artifacts (app/Modules/{Module}/Pages/{name}.html/.css/.js)
  └─ Regenerate PageRoutes.php
```

### 19.2 Database

**Table:** `sys_page`

| Column | Type | Mô tả |
|--------|------|-------|
| `name` | VARCHAR(100) PK | Page name (slug, lowercase underscore) |
| `module` | VARCHAR(50) | Module chứa page |
| `label` | VARCHAR(200) | Tên hiển thị |
| `icon` | VARCHAR(100) | Icon class |
| `route` | VARCHAR(200) | URL path (unique) |
| `html_content` | TEXT | Nội dung HTML |
| `css_content` | TEXT | CSS inline |
| `js_content` | TEXT | JavaScript inline |
| `roles` | JSONB | Role access control (`[]` = all authenticated users) |
| `is_active` | SMALLINT | 0/1 |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

### 19.3 Components

| Component | File | Mô tả |
|-----------|------|-------|
| `PageModel` | `core/Metadata/Models/PageModel.php` | CRUD `sys_page` |
| `PageService` | `core/Metadata/Services/PageService.php` | Business logic: save, scaffold files, regenerate routes, route validation, awesome bar |
| `PageController` | `core/Metadata/Controllers/PageController.php` | 6 endpoints: index, create, edit, save, delete, serve |
| `page_list` | `core/Metadata/Views/pages/page_list.php` | List view (table + Create button) |
| `page_form` | `core/Metadata/Views/pages/page_form.php` | Create/edit form (name, label, module, route, HTML/CSS/JS editors, role checkboxes) |

### 19.4 Route strategy

- **Builder routes** (`/desk/pages`, `/desk/pages/create`, `/desk/pages/edit/{name}`) trong admin group
- **API routes** (`POST /api/pages/save`, `POST /api/pages/delete/{name}`) trong admin group
- **Serve routes** (auto-generated `PageRoutes.php`): `/{route}` → `PageController::serve/{route}`
- Route động được require ở cuối `Routes.php`: `require __DIR__ . '/PageRoutes.php'`
- Reserved routes (`health`, `ping`, `login`, `logout`, `setup`, `desk`, `api`) bị chặn khi tạo page

### 19.5 Access control

- `roles` JSONB column: empty array → tất cả authenticated users
- Specific roles → user phải có ít nhất một role
- Admin luôn bypass được role check

**Builder/management (B3):** các endpoint desk (`index/create/edit/save/delete`) và API (`save/delete`) được bọc bởi `PlatformFilter` (`platform`) — chỉ admin hoặc user có role `platform_developer` mới quản lý được custom pages. Việc **serve page** (route `/pagename`) vẫn theo `roles` của page như cũ, không bị giới hạn thêm.

### 19.6 File scaffolding

Khi save, `PageService::scaffoldPageFiles()` tạo 3 files trong `app/Modules/{Module}/Pages/`:
- `{name}.html` — HTML content
- `{name}.css` — CSS content
- `{name}.js` — JavaScript content

Khi delete hoặc đổi module, file cũ được xóa tự động.

### 19.7 Desk integration

| Integration | Location |
|-------------|----------|
| Desk card | Workspace — "Pages" shortcut trong workspace admin seed (`WorkspaceBlockModel::seedDefaults()`) |
| Topbar nav | Admin topbar — "Pages" link (`desk_topbar.php`) |
| AwesomeBar | `sys_awesome_bar` — "Pages" entry (`seedCorePages()`) + individual pages on save |

---

## 20. Multi-tenancy

Volt sử dụng kiến trúc **Database-per-Tenant**: mỗi tenant có một PostgreSQL database riêng biệt.

### 20.1 Hub DB vs Tenant DB

| Database | Mục đích | Bảng |
|----------|----------|------|
| `volt_enterprise` (hub) | Quản lý tenant, route auth | `sys_tenant` |
| `volt_{tenant_name}` (tenant) | Dữ liệu của tenant | `sys_user`, `sys_role`, `sys_permission`, `sys_awesome_bar`, ... (tất cả bảng core còn lại) |

### 20.2 Domain-based resolution

Tenant được xác định từ `HTTP_HOST` chứ không dùng dropdown:

1. **Exact match**: `sys_tenant.domain` = hostname → dùng tenant đó
2. **Subdomain extraction**: `ilsungtech.localhost` → tên tenant = `ilsungtech`
3. Nếu host khớp hub host (`localhost`) → hub mode (không tenant)

Implementation: `AuthController::resolveTenantFromDomain()` → `TenantService::resolveByDomain()` → `TenantService::getByName()`

### 20.3 VoltDatabase class

**File:** `core/Database/VoltDatabase.php`

Class tĩnh quản lý kết nối DB:

| Method | Chức năng |
|--------|-----------|
| `connection()` | Auto-route: nếu session có tenant → kết nối tenant DB, else hub DB |
| `hubConnection()` | Luôn kết nối hub DB (`volt_enterprise`) |
| `tenantConnection(name)` | Đọc `sys_tenant` → kết nối tenant DB |
| `resolveTenant(name?)` | Lấy tenant từ session hoặc tham số |
| `createTenantDatabase(name, host, port)` | `exec(psql)` → `CREATE DATABASE ... OWNER ...` |
| `dropTenantDatabase(name, host, port)` | `exec(psql)` → `DROP DATABASE IF EXISTS ... WITH (FORCE)` |
| `migrateTenantDatabase(name, host, port, user, password)` | Chạy `MigrationRunner` namespace `Volt\Core` trên tenant DB |

**Caching**: `VoltDatabase` cache connection instances trong `self::$instances` để tránh tạo kết nối mới mỗi request.

### 20.4 Auth flow

```
Request → HTTP_HOST = ilsungtech.localhost
  → App::__construct() set baseURL động
  → resolveTenantFromDomain() → 'ilsungtech'
  → AuthService::login(name, password, 'ilsungtech')
    → VoltDatabase::tenantConnection('ilsungtech') → kết nối tenant DB
    → UserModel::findByName(name) trên tenant DB
    → startSession(user, 'ilsungtech') — lưu tenant vào session
  → Các request sau: VoltDatabase::connection() auto-route nhờ session tenant
```

**Fallback**: Nếu tenant không tồn tại (đã xoá), `resolveTenantFromDomain()` set flashdata error và trả về `null` → login fallback hub DB → user không có trong hub → login fail.

**Session cleanup**: `currentUser()` try-catch `RuntimeException` từ `tenantConnection()`, nếu tenant không còn active → clear session + return null.

### 20.5 Tenant management UI

| Route | Method | Chức năng |
|-------|--------|-----------|
| `/desk/tenants` | GET | List tenants |
| `/desk/tenants/create` | GET | Form tạo tenant |
| `/desk/tenants/store` | POST | Lưu tenant + tự động tạo DB + chạy migrations |
| `/desk/tenants/edit/{name}` | GET | Form sửa tenant |
| `/desk/tenants/update/{name}` | POST | Cập nhật tenant |
| `/desk/tenants/delete/{name}` | POST | **Soft-delete** (không drop DB ngay) |
| `/desk/tenants/trash` | GET | List tenant đã soft-delete |
| `/desk/tenants/restore/{name}` | POST | Khôi phục tenant đã soft-delete |
| `/desk/tenants/purge/{name}` | POST | Purge hẳn (chỉ khi hết grace) |

Controllers: `TenantController` (`core/Tenant/Controllers/`)
Service: `TenantService` (`core/Tenant/Services/`)
Model: `TenantModel` (`core/Tenant/Models/` — dùng `hubConnection()`)

### 20.6 Tenant lifecycle (soft-delete / restore / purge)

Tenant không bị drop DB ngay khi xóa — đi qua **lifecycle an toàn** để tránh mất dữ liệu vô tình:

| Giai đoạn | Hành động | DB |
|-----------|-----------|----|
| **Active** | Hoạt động bình thường | còn nguyên |
| **Trashed (soft-deleted)** | `deleted_at`, `deleted_by` set; bị chặn đăng nhập & resolve domain | **chưa drop** |
| **Purged** | Sau `tenantDeleteGraceDays` (mặc định 30) → backup + drop DB + hard-delete | bị drop |

- Cột: `deleted_at`, `deleted_by`, `purge_at` (migration `2026-08-06-000002_AddTenantSoftDeleteColumns`).
- `TenantModel` bật CI4 `useSoftDeletes`; `getTrashed()` = trashed, `getDuePurge()` = trashed quá grace.
- `TenantService::softDelete()` / `restore()` / `purge()`:
  - `purge()` bắt buộc **backup DB trước** (`BackupService`), fail → chặn purge; sau đó `DROP DATABASE ... WITH (FORCE)` rồi hard-delete.
  - `isPurgeDue()` kiểm tra grace đã hết.
- `currentActor()` lấy từ session để ghi `deleted_by` / audit.
- Purge tự động qua CLI: `php spark volt:purge-tenants` (cron nên chạy định kỳ).
- `resolveByDomain()` / `resolveTenantFromDomain()` bỏ qua tenant đã soft-delete → user không thể login vào tenant đã xóa.

### 20.7 CLI commands

| Command | Chức năng |
|---------|-----------|
| `php spark volt:tenant-create <name>` | Tạo tenant + DB (manual) |
| `php spark volt:tenant-migrate <name>` | Chạy migrations trên tenant DB |
| `php spark volt:backup [tenant] [--verify] [--prune]` | Backup DB (xem mục 12) |
| `php spark volt:purge-tenants [--force]` | Purge tenant đã trashed quá grace (xem mục 12) |

Files: `core/Commands/TenantCreate.php`, `core/Commands/TenantMigrate.php`, `core/Commands/VoltBackup.php`, `core/Commands/VoltPurgeTenants.php`

### 20.8 Config

**`app/Config/App.php`**: `__construct()` tự động set `baseURL` từ `$_SERVER['HTTP_HOST']` để `site_url()` sinh đúng host cho mọi tenant domain.

### 20.9 Các file liên quan

- `core/Database/VoltDatabase.php`
- `core/Tenant/`
- `core/Auth/Controllers/AuthController.php` — `resolveTenantFromDomain()`
- `core/Auth/Services/AuthService.php` — `login($tenantName)`, `resolveUserModel($tenantName)`
- `core/Auth/Filters/PageAuthFilter.php` — redirect về login qua `site_url()`
- `app/Config/App.php` — dynamic baseURL
- `core/Database/Migrations/2026-07-27-000002_CreateSysTenantTable.php`
- `core/Database/Migrations/2026-08-06-000002_AddTenantSoftDeleteColumns.php`
- `core/System/Services/BackupService.php`

---

## 21. Workspace

**Namespace:** `Volt\Core\Workspace`

Desk home (`/` và `/desk`) là **Workspace cá nhân** của từng user: một grid các block có thể sắp xếp, mỗi user có workspace riêng (auto-create + seed khi truy cập lần đầu).

### 21.1 Database

**Bảng:** `sys_workspace`, `sys_workspace_block` (migration `2026-07-31-000001_CreateSysWorkspaceTables.php`)

`sys_workspace`:

| Column | Type | Mô tả |
|--------|------|-------|
| `id` | SERIAL PK | |
| `user_name` | VARCHAR(100) | User sở hữu (PK `sys_user` là `name`, unique index `uq_sys_workspace_user`) |
| `title` | VARCHAR(100) | Tiêu đề hiển thị |
| `columns` | SMALLINT | Số cột grid (1–4, default 3) |
| `is_active` | SMALLINT | 0/1 |
| `created_at` / `updated_at` | TIMESTAMP | |

`sys_workspace_block`:

| Column | Type | Mô tả |
|--------|------|-------|
| `id` | SERIAL PK | |
| `workspace_id` | INTEGER | FK → `sys_workspace.id` |
| `block_type` | VARCHAR(20) | `shortcut` / `note` / `entity_list` / `count` |
| `title` | VARCHAR(255) | Tiêu đề block |
| `data` | JSONB | Payload theo type (xem 21.3) |
| `size` | SMALLINT | Số cột chiếm (1–3, clamp vào `columns`) |
| `sort` | INTEGER | Thứ tự hiển thị (0-based) |
| `is_visible` | SMALLINT | 0/1 |
| `created_at` / `updated_at` | TIMESTAMP | |

### 21.2 Components

| Component | File | Chức năng |
|-----------|------|-----------|
| `WorkspaceController` | `core/Workspace/Controllers/WorkspaceController.php` | Trang `/` + `/desk`, API `api/workspace/*`, resolve live data |
| `WorkspaceModel` | `core/Workspace/Models/WorkspaceModel.php` | CRUD `sys_workspace`, `getOrCreateForUser()` (auto-create + seed) |
| `WorkspaceBlockModel` | `core/Workspace/Models/WorkspaceBlockModel.php` | CRUD `sys_workspace_block`, reorder, seed defaults |
| `workspace.php` | `core/Workspace/Views/workspace.php` | Alpine.js component + SortableJS drag-drop + dialog picker |

**Frontend:** view dùng Alpine.js (`workspaceApp()`) + SortableJS (`public/assets/vendor/sortablejs/`) + các class `.claro-workspace-*` trong `public/assets/volt/claro.css` (section 24).

### 21.3 Block types & data

| Type | `data` | Hiển thị |
|------|--------|----------|
| `shortcut` | `{url, icon}` | Link card + icon (inline SVG, x-show theo `icon`) |
| `note` | `{text}` | Card ghi chú |
| `entity_list` | `{entity, max_rows}` (≤5) | Bảng live data (3 cột đầu tiên theo field catalog) |
| `count` | `{entity}` | Số bản ghi live |

Icon pool: `doc, user, shield, server, chart, folder, link, star`.

### 21.4 UX flow

- **Xem:** block render sạch, không có toolbar (ẩn `opacity:0` + `visibility:hidden`).
- **Customize:** nút header bật `editMode` → hiện toolbar từng block (kéo thả ⠿, ✎ sửa, 🗑 xóa), thanh "Add block" dashed, selector cột (1–4), dòng hint.
- **Drag-drop:** SortableJS chỉ enable trong `editMode` (`sortable.option('disabled', !editMode)`), `onEnd` ánh xạ DOM order qua `data-block-id` → `persistOrder()`.
- **Add/Edit dialog:** 4 type-card, quick-pick trang (admin thấy nhiều trang hơn), icon picker, width chips (1–3 col).
- **Empty state:** user mới chưa có block → nút "Add your first block" (luôn hiển thị cả 2 mode).

### 21.5 API

| Endpoint | Method | Chức năng |
|----------|--------|-----------|
| `/api/workspace/load` | GET | Workspace + blocks (đã resolve live data) + entities |
| `/api/workspace/block/save` | POST | Upsert block (`{id, block_type, title, size, data}`) |
| `/api/workspace/block/delete` | POST | Xóa block (`{id}`) |
| `/api/workspace/block/reorder` | POST | Sắp thứ tự (`{ids}`) |
| `/api/workspace/save` | POST | Cập nhật columns / title |

- Lỗi validate (type không hợp lệ, block không thuộc workspace) → HTTP 422 JSON.
- Tất cả API đều cần filter `auth`; block ownership được kiểm tra bằng `WHERE id = ? AND workspace_id = ?`.

### 21.6 Performance

- **Batch resolve:** `resolveBlocks()` gom block theo `(entity, max_rows)` cho `entity_list` và theo `entity` cho `count` → **1 query cho mỗi nhóm** thay vì 1 query/block (tránh N+1 khi nhiều block cùng entity).
- **Memoize per-request:** `entityOptions()` (danh sách entity) và `fieldCatalog()` (toàn bộ field) query 1 lần/request, dùng lại cho mọi block — bỏ việc query lại `sys_entity` / `sys_entity_field` từng block.
- **Reorder 1 câu SQL:** `UPDATE ... SET sort = CASE id ... END ... WHERE id IN (...)` gộp N UPDATE.
- **Upsert ít round-trip:** check ownership bằng `affectedRows()` sau `UPDATE ... WHERE id AND workspace_id` thay vì `findById` trước.

### 21.7 Multilingual

Tất cả chuỗi hiển thị trong view và `quickPickPages()` đều qua `core/Config/Lang/{en,vi}.php` (section `workspace`): title, nút Customize/Done, hint, type-card, width, quick-pick labels, cột `name_label` (Name/Tên), welcome text. Default seed blocks dùng `LangService::get()` nên workspace mới tự chọn đúng ngôn ngữ theo session/setting.

### 21.8 Files

- `core/Workspace/Controllers/WorkspaceController.php`
- `core/Workspace/Models/WorkspaceModel.php`
- `core/Workspace/Models/WorkspaceBlockModel.php`
- `core/Workspace/Views/workspace.php`
- `core/Database/Migrations/2026-07-31-000001_CreateSysWorkspaceTables.php`
- `public/assets/vendor/sortablejs/Sortable.min.js`
- `public/assets/volt/claro.css` (section 24)

---

## Tham khảo

- [VOLT_FRAMEWORK_RULES.md](VOLT_FRAMEWORK_RULES.md)
- [architecture.md](architecture.md)
- [claro-theme.md](claro-theme.md)
- [desc-project.md](desc-project.md)
- [entity-builder.md](entity-builder.md)
- [multilingual.md](multilingual.md)
- [roadmap.md](roadmap.md)
