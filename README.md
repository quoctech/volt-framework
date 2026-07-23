# Volt Framework

> Metadata-driven ERP engine trên CodeIgniter 4 + PostgreSQL + Redis + Alpine.js

Volt là framework `metadata-driven`: thay vì code CRUD thủ công cho từng bảng, bạn định nghĩa entity và field qua Entity Builder UI, engine tự động đồng bộ schema, sinh controller/model/view, và cung cấp REST API.

## Tech Stack

| Layer | Công nghệ |
|-------|-----------|
| Backend | PHP 8.5+, CodeIgniter 4.7 |
| Database | PostgreSQL 15+ |
| Cache | Redis (metadata, permission) |
| Frontend | Server-rendered HTML + Alpine.js + Tailwind CSS |

## Quick Start

```bash
# 1. Clone & cài đặt
composer install
cp env .env          # Cấu hình database (PostgreSQL) + Redis

# 2. Chạy core migration (tạo sys_* tables)
php spark volt:core-migrate

# 3. Đồng bộ schema cho tất cả entities
php spark volt:sync --all

# 4. Sinh artifact (controller, model, view, JS) cho entities
php spark volt:scaffold --all

# 5. Khởi động dev server
php spark serve
```

## Architecture

Volt được tổ chức theo 4-layer architecture:

```
Delivery ──→ Application ──→ Core Engine ──→ Persistence
  (CI4 Views,     (Controllers,     (Metadata Compiler,    (PostgreSQL,
   Alpine.js)      Services)          SchemaSync,            Redis)
                                       WorkflowEngine,
                                       PermissionResolver)
```

Chi tiết: [`core/docs/architecture.md`](core/docs/architecture.md)

## Core Documentation

| File | Mô tả |
|------|-------|
| [`core/docs/VOLT_FRAMEWORK.md`](core/docs/VOLT_FRAMEWORK.md) | Toàn tập framework — 17 sections |
| [`core/docs/VOLT_FRAMEWORK_RULES.md`](core/docs/VOLT_FRAMEWORK_RULES.md) | Quy tắc code bắt buộc cho AI và developer |
| [`core/docs/architecture.md`](core/docs/architecture.md) | Kiến trúc 4-layer, metadata flow, caching |
| [`core/docs/roadmap.md`](core/docs/roadmap.md) | Lộ trình phát triển (Phases 0-9) |
| [`core/docs/desc-project.md`](core/docs/desc-project.md) | Mô tả dự án, hiện trạng, cấu trúc thư mục |
| [`core/docs/multilingual.md`](core/docs/multilingual.md) | Hệ thống đa ngôn ngữ (LangService) |
| [`core/docs/entity-builder.md`](core/docs/entity-builder.md) | Entity Builder UI, field types, schema sync |

## CLI Commands

| Command | Mô tả |
|---------|-------|
| `php spark volt:core-migrate` | Chạy migration core (sys_* tables) |
| `php spark volt:core-migrate-status` | Kiểm tra trạng thái migration |
| `php spark volt:sync {EntityName}` | Đồng bộ schema vật lý cho entity |
| `php spark volt:sync --all` | Đồng bộ tất cả entities |
| `php spark volt:scaffold {EntityName}` | Sinh artifact code cho entity |
| `php spark volt:scaffold --all` | Sinh cho tất cả entities |
| `php spark volt:clean-entities` | Xoá entity artifact dư thừa |
| `php spark volt:sync-awesome-bar` | Đồng bộ awesome bar index |

## Server Requirements

- PHP 8.2+ (khuyến nghị 8.5)
- PostgreSQL 15+
- Redis (khuyến nghị)
- Extensions: `intl`, `mbstring`, `json`, `curl`, `pdo_pgsql`

## Project Structure

```
volt-project/
├── app/                # Application layer (Config, Controllers, Views, Modules)
│   ├── Config/         # App configuration, routes, services
│   └── Modules/        # Business modules (Hrms, Stock, ...)
│       └── Hrms/
│           ├── Config/ # Module routes
│           ├── Controllers/
│           ├── Entities/ (auto-generated)
│           ├── Models/ (auto-generated)
│           └── Views/ (auto-generated)
├── core/               # Volt Framework engine
│   ├── Audit/          # Audit trail
│   ├── Auth/           # Authentication, filters, user management
│   ├── AwesomeBar/     # Quick search & navigation
│   ├── Commands/       # CLI spark commands
│   ├── Config/         # System config, language packs
│   ├── Database/       # DB connection, migrations, TableNameResolver
│   ├── docs/           # Documentation
│   ├── Engine/         # SchemaSync, MetadataCompiler, WorkflowEngine
│   ├── Metadata/       # Entity builder, artifact scaffolder, resource controller
│   ├── Models/         # VoltModel, FileModel
│   ├── Role/           # Role management
│   ├── Security/       # PermissionResolver
│   ├── System/         # System status, settings, error logs
│   └── Validation/     # MetadataValidator
├── public/             # Web server root
├── tests/              # PHPUnit tests
└── vendor/             # Composer dependencies
```

## Development

```bash
# Chạy PHPUnit tests
composer test

# Kiểm tra syntax
find core/ -name '*.php' -exec php -l {} \;
```

## License

Volt Framework — Internal project.
Built on [CodeIgniter 4](https://codeigniter.com).
