<?php

$page = $page ?? null;
$modules = $modules ?? [];
$roles = $roles ?? [];
$isEdit = $page !== null;
$pageRoles = $isEdit ? json_decode($page['roles'] ?? '[]', true) : [];
?>
<div class="space-y-4">
    <div class="rounded border border-slate-200 bg-white px-5 py-4">
        <a href="<?= site_url('desk/pages') ?>" class="mb-2 inline-flex items-center gap-1 text-sm text-slate-500">
            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor"><path d="M7.78 12.53a.75.75 0 01-1.06 0L2.47 8.28a.75.75 0 010-1.06l4.25-4.25a.75.75 0 011.06 1.06L4.81 7h8.44a.75.75 0 010 1.5H4.81l2.97 2.97a.75.75 0 010 1.06z"/></svg>
            Back to Pages
        </a>
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold text-slate-900"><?= $isEdit ? 'Edit Page' : 'Create Page' ?></h1>
            <?php if ($isEdit && !empty($page['route'])): ?>
            <a href="<?= site_url($page['route']) ?>" target="_blank" class="inline-flex items-center gap-1 rounded border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700">
                Go to Page
                <svg class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor"><path d="M6.22 2.22a.75.75 0 011.06 0l4.25 4.25a.75.75 0 010 1.06l-4.25 4.25a.75.75 0 01-1.06-1.06L9.94 7 6.22 3.28a.75.75 0 010-1.06z"/></svg>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <form id="pageForm" class="rounded border border-slate-200 bg-white">
        <input type="hidden" name="original_name" value="<?= esc($page['name'] ?? '') ?>">

        <div class="p-5">
            <div class="mb-4">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="name">Page Name</label>
                <input type="text" id="name" name="name" value="<?= esc($page['name'] ?? '') ?>" <?= $isEdit ? 'readonly' : '' ?> required
                    class="w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-900 <?= $isEdit ? 'bg-slate-50 text-slate-500' : '' ?>"
                    placeholder="my_dashboard">
                <p class="mt-1 text-xs text-slate-400">Lowercase, underscores only. Used for file names.</p>
            </div>
            <div class="mb-4">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="label">Label</label>
                <input type="text" id="label" name="label" value="<?= esc($page['label'] ?? '') ?>" required
                    class="w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-900"
                    placeholder="My Dashboard">
            </div>
            <div class="mb-4">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="module">Module</label>
                <select id="module" name="module" required
                    class="w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-900">
                    <option value="">-- Select Module --</option>
                    <?php foreach ($modules as $mod): ?>
                    <option value="<?= esc($mod['name'] ?? '') ?>" <?= ($mod['name'] ?? '') === ($page['module'] ?? '') ? 'selected' : '' ?>>
                        <?= esc($mod['label'] ?? $mod['name'] ?? '') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="icon">Icon</label>
                <input type="text" id="icon" name="icon" value="<?= esc($page['icon'] ?? '') ?>"
                    class="w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-900"
                    placeholder="chart-bar">
            </div>
            <div class="mb-4">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="route">Route</label>
                <input type="text" id="route" name="route" value="<?= esc($page['route'] ?? '') ?>" required
                    class="w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-900"
                    placeholder="my-dashboard">
                <p class="mt-1 text-xs text-slate-400">URL path. Auto-filled from Page Name if empty.</p>
            </div>
            <div class="mb-4">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" <?= $page === null || (int) ($page['is_active'] ?? 1) ? 'checked' : '' ?>>
                    Active
                </label>
            </div>
        </div>

        <div class="border-t border-slate-200 p-5">
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="html_content">HTML</label>
            <textarea id="html_content" name="html_content" rows="16"
                class="w-full rounded border border-slate-300 px-3 py-2 font-mono text-sm text-slate-900"
                placeholder="<div>Page content here</div>"><?= esc($page['html_content'] ?? '') ?></textarea>
        </div>

        <div class="border-t border-slate-200 p-5">
            <div class="mb-4">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="css_content">CSS</label>
                <textarea id="css_content" name="css_content" rows="10"
                    class="w-full rounded border border-slate-300 px-3 py-2 font-mono text-sm text-slate-900"
                    placeholder="/* Page styles */"><?= esc($page['css_content'] ?? '') ?></textarea>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="js_content">JavaScript</label>
                <textarea id="js_content" name="js_content" rows="10"
                    class="w-full rounded border border-slate-300 px-3 py-2 font-mono text-sm text-slate-900"
                    placeholder="// Page JavaScript"><?= esc($page['js_content'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="border-t border-slate-200 p-5">
            <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Role Access</label>
            <p class="mb-3 text-xs text-slate-400">Leave empty for all authenticated users. Otherwise, only selected roles can access.</p>
            <div class="flex flex-wrap gap-4">
                <?php foreach ($roles as $role): ?>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="roles[]" value="<?= esc($role['name'] ?? '') ?>" <?= in_array($role['name'] ?? '', $pageRoles, true) ? 'checked' : '' ?>>
                    <?= esc($role['label'] ?? $role['name'] ?? '') ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="flex justify-end gap-3 border-t border-slate-200 px-5 py-4">
            <a href="<?= site_url('desk/pages') ?>" class="rounded border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">Cancel</a>
            <button type="submit" class="rounded border border-slate-600 bg-white px-4 py-2 text-sm font-semibold text-slate-900">Save Page</button>
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
