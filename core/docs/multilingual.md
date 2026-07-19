# Đa ngôn ngữ (Multilingual)

Volt hỗ trợ đa ngôn ngữ với cơ chế **file-based language pack** + **System Settings**.

## Kiến trúc

```
core/Config/Lang/
├── LangService.php      # Service load ngôn ngữ, cache, resolve
├── en.php               # Tiếng Anh (mặc định)
└── vi.php               # Tiếng Việt
```

Luồng hoạt động:

1. `LangService::load()` được gọi mà không có tham số → `resolveLang()` xác định ngôn ngữ theo thứ tự ưu tiên:
   - Session `volt_language` (nếu có)
   - DB `sys_setting` → `language`
   - Mặc định: `'en'`
2. File ngôn ngữ tương ứng được load, cache vào static variable.
3. Các view dùng `LangService::get('section.key')` hoặc load cả mảng bằng `LangService::load()`.

## Cách dùng trong View

```php
// Load toàn bộ strings
$lang = \Volt\Core\Config\Lang\LangService::load();
$nav = $lang['nav'] ?? [];
echo $nav['system_settings'];
```

```php
// Lấy một chuỗi cụ thể (dot notation)
\Volt\Core\Config\Lang\LangService::get('common.save');
\Volt\Core\Config\Lang\LangService::get('desk.entity_count', ['count' => 5]);
```

## Cấu trúc Language File

```php
return [
    'code' => 'en',           // Mã ngôn ngữ
    'name' => 'English',      // Tên hiển thị

    'common' => [
        'save'   => 'Save',
        'cancel' => 'Cancel',
        // ...
    ],
    'nav' => [
        'profile' => 'Edit Profile',
        // ...
    ],
    'roles' => [
        'title' => 'Role List',
        // ...
    ],
    // ... mỗi nhóm view một key riêng
];
```

Support interpolation: `'entity_count' => '{count} entity(ies) available.'`

## Thêm ngôn ngữ mới

1. Tạo file `core/Config/Lang/{code}.php` (VD: `fr.php` cho French)
2. Thêm `'{code}'` vào mảng `SUPPORTED_LANGS` trong `LangService.php`
3. Định nghĩa đầy đủ các key giống `en.php`

```php
// core/Config/Lang/LangService.php
private const SUPPORTED_LANGS = ['en', 'vi', 'fr'];
```

## System Settings

Trang **Desk → System Settings** (Admin) cho phép chọn:
- **Language** — ngôn ngữ giao diện
- **Timezone** — múi giờ hệ thống

Settings được lưu vào:
1. DB `sys_setting` (bền vững)
2. Session (`volt_language`, `volt_timezone`) — fallback khi DB chưa có

## Quy tắc cho Feature mới

**Mọi text hiển thị trong UI đều phải qua LangService.**

### PHP
```php
// ❌ Sai: hardcode tiếng Việt
echo 'Xin chào';
echo 'Hello';

// ✅ Đúng
\Volt\Core\Config\Lang\LangService::get('common.hello');
```

### Alpine.js / JavaScript
Truyền lang strings qua `json_encode` vào `x-data`:
```php
<main x-data="myApp(<?= esc(json_encode([
    'lang' => [
        'save' => \Volt\Core\Config\Lang\LangService::get('common.save'),
    ],
], JSON_UNESCAPED_UNICODE), 'attr') ?>)">
```

Trong JS:
```javascript
function myApp(boot) {
    return {
        saveLabel: boot.lang.save,
        // ...
    };
}
```

### Thêm key mới
Nếu feature mới cần text chưa có trong language file:
1. Thêm vào `en.php` và `vi.php` (và các file ngôn ngữ khác nếu có)
2. Dùng key đó qua `LangService::get()`

### i18n Checklist
- [ ] Không hardcode text tiếng Việt hay tiếng Anh trong view
- [ ] Dùng `LangService::get()` hoặc `LangService::load()`
- [ ] Key mới được thêm vào `en.php` và `vi.php`
- [ ] JS strings được truyền qua `json_encode` + `data-*` attribute
- [ ] Interpolation dùng `{param}` syntax
- [ ] `html lang` attribute dùng `$lang['code']`
