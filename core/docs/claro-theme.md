# Claro Theme — Giao diện admin hệ thống

**Files:**
- `public/assets/volt/claro.css` — design tokens + component system
- `public/assets/volt/claro.js` — Alpine.js components

Claro là admin theme mặc định của toàn bộ hệ thống Volt, lấy cảm hứng từ **Drupal Claro admin theme**: gọn gàng, độ tương phản cao, focus ring màu xanh lá, dùng CSS variables để tùy biến màu không cần sửa component.

## 1. Cách tích hợp

Mọi view hệ thống (desk, auth, entity builder, entity form/list, create module, workspace) đều include theo thứ tự sau:

```html
<link rel="stylesheet" href="<?= base_url('assets/vendor/tailwindcss/tailwind.min.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/volt/claro.css') ?>">
<script defer src="<?= base_url('assets/volt/claro.js') ?>"></script>
<script defer src="<?= base_url('assets/vendor/alpinejs/alpine.min.js') ?>"></script>
<style>[x-cloak]{display:none!important}</style>
```

- `<body>` phải mang class `claro-body` (reset + font + màu nền trang).
- `claro.js` phải load **trước** `alpine.min.js` (dùng `defer` trên cả 2, đúng thứ tự) vì nó đăng ký component qua sự kiện `alpine:init`.
- Layout dùng chung: `core/Metadata/Views/layouts/desk.php` (topbar `.claro-topbar__*` + `.claro-page--wide`).
- View độc lập (login, entity builder) tự include 4 file như trên.

### 1.1 Nơi sử dụng

| View | Ghi chú |
|------|---------|
| `app/Views/auth/login.php`, `dashboard.php`, `profile.php` | Trang auth, không cần Alpine |
| `core/Metadata/Views/layouts/desk.php` | Layout desk (topbar) |
| `core/Metadata/Views/entity_builder.php` | Entity Builder (Alpine `entityBuilderApp`) |
| `core/Metadata/Views/create_module.php` | Tạo module |
| `core/Metadata/Views/templates/entity_form.php`, `entity_list.php` | Template form/list generic (sinh bởi `ArtifactScaffolder`) |
| `app/Modules/*/Views/*.php` | View Hrms (employee, leave, ...) — scaffold từ template |
| `core/Workspace/Views/workspace.php` | Workspace (`.claro-workspace-*`) |

## 2. Design tokens

Định nghĩa tại `:root` (section 1 của claro.css). Toàn bộ component dùng token, nên **thay token = đổi cả theme**.

### Màu

| Nhóm | Token | Giá trị mặc định |
|------|-------|------------------|
| Primary | `--claro-color-primary` (+ `-hover`, `-active`, `-light`, `-dark`) | `#003ecc` |
| Text | `--claro-color-text`, `-text-light`, `-text-subtle` | `#232429` / `#919297` / `#55565b` |
| Surface | `--claro-color-bg`, `-bg-page`, `-bg-hover`, `-bg-subtle` | trắng / `#f3f4f9` / `#f5f8ff` / `#f9faff` |
| Gray | `--claro-gray-900` → `-025` | thang xám 10 bậc |
| Semantic | `--claro-color-success/-warning/-error` (+ `-bg`, `-border`) | xanh lá / vàng / đỏ |
| Focus | `--claro-color-focus` | `#26a769` (focus ring xanh lá) |

### Typography & spacing

| Token | Giá trị |
|-------|---------|
| `--claro-font-family` | system font stack |
| `--claro-font-size-xxs/xs/s/base/h6…h1/xl` | thang 0.702rem → 2.25rem |
| `--claro-line-height` / `-heading` | 1.5 / 1.3 |
| `--claro-space-xs/s/m/l/xl` | 0.5 / 0.75 / 1.125 / 2 / 3rem |
| `--claro-border-radius` | 2px |

### Khác

| Token | Giá trị |
|-------|---------|
| `--claro-shadow-card` / `-dialog` / `-details` / `-button` | 4 bóng |
| `--claro-layout-max-width` / `-extended-width` | 1280px / 1440px |
| `--claro-sidebar-width` | 20em |
| `--claro-topbar-height` | 3.25rem |
| `--claro-transition` | `all 0.2s ease-out` |

## 3. CSS components (claro.css)

| # | Component | Class chính | Ghi chú |
|---|-----------|-------------|---------|
| 2 | Reset | `claro-body` | font, màu, background |
| 3 | Focus ring | `claro-focus-ring` | auto áp cho button/a/input/select/textarea khi `:focus-visible` |
| 4 | Page header | `claro-page-header__title/__subtitle` | |
| 5 | Button | `claro-button` (+ `--primary`, `--danger`, `--small`, `--extrasmall`, `--link`) | mặc định gray; disabled có `aria-disabled` |
| 6 | Input | `claro-input`, `claro-select`, `claro-textarea` (+ `--error`) | select có mũi tên SVG inline; checkbox/radio: `claro-checkbox`, `claro-radio` |
| 7 | Form layout | `claro-form-item` (+ `__label`, `__description`, `__error`, `--inline`), `claro-form-actions` | |
| 8 | Table | `claro-table` (+ `--striped`), `claro-table__actions` | |
| 8b | Child table | `claro-child-table` (+ `__header`, `__title`, `__cell-check`, `__cell-text`, `__remove`, `__empty`) | grid inline editable trong form entity |
| 9 | Card | `claro-card` (+ `__content`, `__title`, `__subtitle`, `__footer`), `claro-card-grid`, `claro-readonly` / `claro-card--readonly` | **card có `overflow: hidden` — xem lưu ý §5.1** |
| 10 | Link card | `claro-link-card` (+ `__badge`, `__title`, `__desc`, `__meta`) | card grid trên desk |
| 11 | Message | `claro-message` (+ `--status`, `--warning`, `--error`, `__icon`, `__content`, `__title`) | mặc định là dark bar |
| 12 | Badge | `claro-badge` (+ `--success`, `--warning`, `--error`, `--info`, `--draft`, `--submitted`, `--approved`, `--cancelled`) | `--draft/...` dùng cho trạng thái workflow |
| 13 | Dropdown | `claro-dropdown`, `claro-dropdown__menu` (+ `--open`), `claro-dropdown__item` (+ `--danger`), `claro-dropdown__divider` | `z-index: 100` |
| 14 | Dialog | `claro-dialog` (+ `__overlay`, `__panel`, `__title`, `__close`, `__body`, `__actions`) | `z-index: 1000` |
| 15 | Tabs | `claro-tabs`, `claro-tabs__tab` (+ `--active`) | |
| 16 | Breadcrumb | `claro-breadcrumb` (+ `__item`) | |
| 17 | Progress | `claro-progress` (+ `__fill`) | |
| 18 | Topbar | `claro-topbar` (+ `__brand`, `__nav`, `__link` (+ `--active`), `__right`) | thanh admin tối |
| 19 | Page layout | `claro-page` (+ `--wide`) | |
| 20 | Table toolbar | `claro-table-toolbar` (+ `__left`, `__right`) | header của list view |
| 21 | Search | `claro-search` (+ `__input`, `__icon`) | |
| 22 | Empty state | `claro-empty` (+ `__icon`, `__text`) | |
| 23 | Permission matrix | `claro-permission-matrix` | |
| 23b | Awesome bar | `claro-awesome-bar__*` | command palette |
| 24 | Workspace | `claro-workspace-grid/block/...` | xem `VOLT_FRAMEWORK.md` §21.2 |
| 25 | Pagination | `claro-pagination` (+ `__controls`) | |
| 27 | Toast | `claro-toast` (+ `--success`, `--error`) | fixed bottom-right, `z-index: 200` |

## 4. Alpine.js components (claro.js)

Đăng ký qua `document.addEventListener('alpine:init', ...)`, dùng chung cho mọi view có Alpine:

| Component | Usage | Chức năng |
|-----------|-------|-----------|
| `claroDropdown` | `x-data="claroDropdown"`, `@click="toggle()"`, `@click.outside="close()"` | Menu dropdown với đóng khi click ngoài |
| `claroDialog` | `x-data="claroDialog"`, `x-show="open"`, `@keydown.escape.window="close()"` | Modal: khóa scroll body khi mở, focus phần tử đầu tiên, `destroy()` hồi phục |
| `claroTabs` | `x-data="claroTabs('tab1')"`, `:class="{ 'claro-tabs__tab--active': active === 'tab1' }"` | Tab đơn giản |
| `claroConfirm` | `x-data="claroConfirm(title, message)"`, `ask(msg, cb)` | Confirm dialog cho hành động xóa |

## 5. Lưu ý tích hợp

### 5.1 `.claro-card` có `overflow: hidden`

`.claro-card` (section 9) set `overflow: hidden` nên **mọi dropdown/absolute menu nằm trong card sẽ bị cắt tại mép card**. Khi cần popup tràn ra ngoài, ghi đè inline:

```html
<section class="claro-card" style="overflow:visible">
```

Đã áp dụng cho session card của Entity Builder (dropdown chọn data type khi bấm "Add Field"). Popup nên dùng `position:absolute` trong wrapper `.relative` thay vì `position:fixed` để không lệch khi scroll.

### 5.2 Thang z-index

- Dropdown thường: `z-20` (Tailwind) / `claro-dropdown__menu`: 100
- Toast: 200
- Dialog: 1000

### 5.3 Tailwind là bản build tĩnh

`public/assets/vendor/tailwindcss/tailwind.min.css` là build cố định (standard utilities có sẵn, ví dụ `w-56`, `top-12`, `z-20`, `-translate-x-1/2`, `max-h-64`). **Các class arbitrary-value (`max-w-[1720px]`, `grid-cols-[minmax(0,1fr)_320px]`, `min-w-[180px]`) không tồn tại trong build** — không dùng; thay bằng CSS variables + inline style hoặc class chuẩn.

### 5.4 Quy ước UI

- Khoảng cách/bo góc: luôn dùng token `--claro-space-*`, `--claro-border-radius`.
- Nút hành động chính: `claro-button--primary`; nguy hiểm: `--danger`; trong bảng: `--small` + reset margin (`style="margin:0"`).
- Trạng thái workflow: `claro-badge--draft/--submitted/--approved/--cancelled`.
- Message thông báo: `claro-message--status/--warning/--error`; toast ngắn: `claro-toast`.
- Chuỗi hiển thị đi qua `LangService` (xem `multilingual.md`), không hardcode.

### 5.5 Chế độ readonly

Entity form/list trong view-only mode dùng `claro-readonly` hoặc `claro-card--readonly` (opacity 0.6 + chặn pointer-events); child table input disable tự ẩn border.

## 6. Tùy biến theme

Chỉ cần ghi đè CSS variables trên `:root` hoặc `body` (ví dụ class dark mode), không cần đụng component:

```css
body.claro-theme-dark {
  --claro-color-bg-page: #1a1b1f;
  --claro-color-text: #f2f2f3;
  /* ... */
}
```
