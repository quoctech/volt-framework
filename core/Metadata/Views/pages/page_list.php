<?php

$pages = $pages ?? [];
?>
<div class="space-y-4">
    <div class="flex items-center justify-between gap-4 rounded border border-slate-200 bg-white px-5 py-4">
        <h1 class="text-xl font-semibold text-slate-900">Pages</h1>
        <a href="<?= site_url('desk/pages/create') ?>" class="inline-flex items-center gap-1 rounded border border-slate-600 bg-white px-3 py-1.5 text-sm font-semibold text-slate-900">
            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor"><path d="M8 2a.75.75 0 01.75.75v4.5h4.5a.75.75 0 010 1.5h-4.5v4.5a.75.75 0 01-1.5 0v-4.5h-4.5a.75.75 0 010-1.5h4.5v-4.5A.75.75 0 018 2z"/></svg>
            Create Page
        </a>
    </div>

    <div class="overflow-x-auto rounded border border-slate-200 bg-white">
        <?php if ($pages === []): ?>
            <div class="px-5 py-12 text-center text-sm text-slate-500">No pages yet. Create your first page.</div>
        <?php else: ?>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                        <th class="px-5 py-3">Label</th>
                        <th class="px-5 py-3">Name</th>
                        <th class="px-5 py-3">Module</th>
                        <th class="px-5 py-3">Route</th>
                        <th class="px-5 py-3">Active</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pages as $page): ?>
                    <tr class="border-b border-slate-100 last:border-b-0">
                        <td class="px-5 py-3 font-medium text-slate-900"><?= esc($page['label'] ?? '') ?></td>
                        <td class="px-5 py-3 text-slate-600"><?= esc($page['name'] ?? '') ?></td>
                        <td class="px-5 py-3 text-slate-600"><?= esc($page['module'] ?? '') ?></td>
                        <td class="px-5 py-3 text-slate-600">/<code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs"><?= esc($page['route'] ?? '') ?></code></td>
                        <td class="px-5 py-3">
                            <?php if ((int) ($page['is_active'] ?? 0)): ?>
                                <span class="inline-flex rounded bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Active</span>
                            <?php else: ?>
                                <span class="inline-flex rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <a href="<?= site_url('desk/pages/edit/' . urlencode($page['name'] ?? '')) ?>" class="inline-flex items-center gap-1 rounded border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700">Edit</a>
                            <button onclick="deletePage('<?= esc($page['name'] ?? '') ?>')" class="inline-flex items-center gap-1 rounded border border-red-300 bg-white px-2.5 py-1 text-xs font-medium text-red-600">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
function deletePage(name) {
    if (!confirm('Delete page "' + name + '"?')) return;
    fetch('<?= site_url('api/pages/delete') ?>/' + encodeURIComponent(name), { method: 'POST' })
        .then(r => r.json())
        .then(data => { if (data.success) location.reload(); })
        .catch(() => alert('Delete failed.'));
}
</script>
