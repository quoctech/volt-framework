<?php

$pages = $pages ?? [];
?>
<div>
    <div class="claro-table-toolbar">
        <div class="claro-table-toolbar__left">
            <div class="claro-page-header" style="margin-bottom:0">
                <h1 class="claro-page-header__title">Pages</h1>
            </div>
        </div>
        <div class="claro-table-toolbar__right">
            <a href="<?= site_url('desk/pages/create') ?>" class="claro-button claro-button--primary">
                + Create Page
            </a>
        </div>
    </div>

    <?php if ($pages === []): ?>
        <div class="claro-empty">
            <p class="claro-empty__text">No pages yet. Create your first page.</p>
        </div>
    <?php else: ?>
        <table class="claro-table">
            <thead>
                <tr>
                    <th>Label</th>
                    <th>Name</th>
                    <th>Module</th>
                    <th>Route</th>
                    <th>Active</th>
                    <th class="claro-table__actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pages as $page): ?>
                <tr>
                    <td style="font-weight:600"><?= esc($page['label'] ?? '') ?></td>
                    <td style="color:var(--claro-gray-600)"><?= esc($page['name'] ?? '') ?></td>
                    <td style="color:var(--claro-gray-600)"><?= esc($page['module'] ?? '') ?></td>
                    <td style="color:var(--claro-gray-600)">/<code style="border-radius:var(--claro-border-radius);background:var(--claro-gray-050);padding:1px 6px;font-size:var(--claro-font-size-xs)"><?= esc($page['route'] ?? '') ?></code></td>
                    <td>
                        <?php if ((int) ($page['is_active'] ?? 0)): ?>
                            <span class="claro-badge claro-badge--success">Active</span>
                        <?php else: ?>
                            <span class="claro-badge">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td class="claro-table__actions">
                        <a href="<?= site_url('desk/pages/edit/' . urlencode($page['name'] ?? '')) ?>" class="claro-button claro-button--small">Edit</a>
                        <button onclick="deletePage('<?= esc($page['name'] ?? '') ?>')" class="claro-button claro-button--small claro-button--danger">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
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
