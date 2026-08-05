<?php

$page = $page ?? null;
$modules = $modules ?? [];
$roles = $roles ?? [];
$isEdit = $page !== null;
$pageRoles = $isEdit ? json_decode($page['roles'] ?? '[]', true) : [];
$isPlatformDeveloper = $isPlatformDeveloper ?? false;
$pagesJsEnabled = $pagesJsEnabled ?? true;
$canEditJs = $pagesJsEnabled && $isPlatformDeveloper;
?>
<div>
    <div style="margin-bottom:var(--claro-space-m)">
        <a href="<?= site_url('desk/pages') ?>" class="claro-button claro-button--link" style="gap:var(--claro-space-xs)">
            &larr; Back to Pages
        </a>
    </div>

    <div class="claro-page-header">
        <h1 class="claro-page-header__title"><?= $isEdit ? 'Edit Page' : 'Create Page' ?></h1>
        <?php if ($isEdit && !empty($page['route'])): ?>
        <a href="<?= site_url($page['route']) ?>" target="_blank" class="claro-button" style="margin:var(--claro-space-xs) 0 0">Go to Page &rarr;</a>
        <?php endif; ?>
    </div>

    <form id="pageForm" class="claro-card">
        <div class="claro-card__content">
            <input type="hidden" name="original_name" value="<?= esc($page['name'] ?? '') ?>">

            <div style="display:grid;gap:var(--claro-space-l);grid-template-columns:repeat(auto-fit,minmax(14rem,1fr))">
                <div class="claro-form-item" style="margin-bottom:0">
                    <label class="claro-form-item__label" for="name">Page Name</label>
                    <input type="text" id="name" name="name" value="<?= esc($page['name'] ?? '') ?>" <?= $isEdit ? 'readonly' : '' ?> required class="claro-input" placeholder="my_dashboard" style="<?= $isEdit ? 'background:var(--claro-gray-050);color:var(--claro-gray-600)' : '' ?>">
                    <div class="claro-form-item__description">Lowercase, underscores only. Used for file names.</div>
                </div>
                <div class="claro-form-item" style="margin-bottom:0">
                    <label class="claro-form-item__label" for="label">Label</label>
                    <input type="text" id="label" name="label" value="<?= esc($page['label'] ?? '') ?>" required class="claro-input" placeholder="My Dashboard">
                </div>
            </div>

            <div class="claro-form-item">
                <label class="claro-form-item__label" for="module">Module</label>
                <select id="module" name="module" required class="claro-select">
                    <option value="">-- Select Module --</option>
                    <?php foreach ($modules as $mod): ?>
                    <option value="<?= esc($mod['name'] ?? '') ?>" <?= ($mod['name'] ?? '') === ($page['module'] ?? '') ? 'selected' : '' ?>><?= esc($mod['label'] ?? $mod['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:grid;gap:var(--claro-space-l);grid-template-columns:repeat(auto-fit,minmax(14rem,1fr))">
                <div class="claro-form-item" style="margin-bottom:0">
                    <label class="claro-form-item__label" for="icon">Icon</label>
                    <input type="text" id="icon" name="icon" value="<?= esc($page['icon'] ?? '') ?>" class="claro-input" placeholder="chart-bar">
                </div>
                <div class="claro-form-item" style="margin-bottom:0">
                    <label class="claro-form-item__label" for="route">Route</label>
                    <input type="text" id="route" name="route" value="<?= esc($page['route'] ?? '') ?>" required class="claro-input" placeholder="my-dashboard">
                    <div class="claro-form-item__description">URL path. Auto-filled from Page Name if empty.</div>
                </div>
            </div>

            <div class="claro-form-item">
                <label class="claro-checkbox">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" <?= $page === null || (int) ($page['is_active'] ?? 1) ? 'checked' : '' ?>>
                    Active
                </label>
            </div>
        </div>

        <div style="border-top:1px solid var(--claro-gray-100);padding:var(--claro-space-l)">
            <div class="claro-form-item">
                <label class="claro-form-item__label" for="html_content">HTML</label>
                <textarea id="html_content" name="html_content" rows="16" class="claro-textarea" style="font-family:monospace" placeholder="<div>Page content here</div>"><?= esc($page['html_content'] ?? '') ?></textarea>
            </div>
        </div>

        <div style="border-top:1px solid var(--claro-gray-100);padding:var(--claro-space-l)">
            <div class="claro-form-item" style="margin-bottom:var(--claro-space-l)">
                <label class="claro-form-item__label" for="css_content">CSS</label>
                <textarea id="css_content" name="css_content" rows="10" class="claro-textarea" style="font-family:monospace" placeholder="/* Page styles */"><?= esc($page['css_content'] ?? '') ?></textarea>
            </div>
            <div class="claro-form-item" style="margin-bottom:0">
                <label class="claro-form-item__label" for="js_content">JavaScript</label>
                <?php if ($canEditJs): ?>
                <textarea id="js_content" name="js_content" rows="10" class="claro-textarea" style="font-family:monospace" placeholder="// Page JavaScript"><?= esc($page['js_content'] ?? '') ?></textarea>
                <div class="claro-form-item__description">Chỉ platform developer mới được chỉnh sửa JavaScript.</div>
                <?php else: ?>
                <div class="claro-readonly" style="padding:var(--claro-space-s) var(--claro-space-m);border:1px solid var(--claro-gray-100);border-radius:var(--claro-radius, 4px);color:var(--claro-color-text-light);font-size:var(--claro-font-size-s)">
                    JavaScript bị vô hiệu hóa hoặc bạn không có quyền platform developer.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div style="border-top:1px solid var(--claro-gray-100);padding:var(--claro-space-l)">
            <label class="claro-form-item__label">Role Access</label>
            <div class="claro-form-item__description" style="margin-top:0;margin-bottom:var(--claro-space-s)">Leave empty for all authenticated users. Otherwise, only selected roles can access.</div>
            <div style="display:flex;flex-wrap:wrap;gap:var(--claro-space-s)">
                <?php foreach ($roles as $role): ?>
                <label class="claro-checkbox">
                    <input type="checkbox" name="roles[]" value="<?= esc($role['name'] ?? '') ?>" <?= in_array($role['name'] ?? '', $pageRoles, true) ? 'checked' : '' ?>>
                    <?= esc($role['label'] ?? $role['name'] ?? '') ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="claro-form-actions" style="justify-content:flex-end;border-top:1px solid var(--claro-gray-100);padding:var(--claro-space-m) var(--claro-space-l)">
            <a href="<?= site_url('desk/pages') ?>" class="claro-button">Cancel</a>
            <button type="submit" class="claro-button claro-button--primary">Save Page</button>
        </div>
    </form>
</div>

<script>
document.getElementById('pageForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var form = this;
    var data = {};
    new FormData(form).forEach(function(value, key) {
        if (key === 'roles[]') {
            if (!data['roles']) data['roles'] = [];
            data['roles'].push(value);
        } else {
            data[key] = value;
        }
    });
    if (!data['roles']) data['roles'] = [];

    fetch('<?= site_url('api/pages/save') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (result.success) {
            window.location.href = '<?= site_url('desk/pages') ?>';
        } else {
            alert('Error: ' + (result.error || 'Save failed.'));
        }
    })
    .catch(function() { alert('Save failed.'); });
});
</script>
