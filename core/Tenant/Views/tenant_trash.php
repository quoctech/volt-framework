<?php

/**
 * @var array<int, array> $tenants
 */
$lang = \Volt\Core\Config\Lang\LangService::load();
$t = $lang['tenants'] ?? [];
$c = $lang['common'] ?? [];
?><div>
    <div class="claro-table-toolbar">
        <div class="claro-table-toolbar__left">
            <div class="claro-page-header" style="margin-bottom:0">
                <h1 class="claro-page-header__title"><?= esc($t['trash_title'] ?? 'Tenant Trash') ?></h1>
                <p class="claro-page-header__subtitle"><?= esc($t['trash_description'] ?? 'Tenants xóa mềm, chờ purge sau grace period') ?></p>
            </div>
        </div>
        <div class="claro-table-toolbar__right">
            <a href="<?= site_url('desk/tenants') ?>" class="claro-button">
                <?= esc($t['back_to_list'] ?? 'Back to list') ?>
            </a>
        </div>
    </div>

    <table class="claro-table">
        <thead>
            <tr>
                <th><?= esc($t['table_name'] ?? 'Name') ?></th>
                <th><?= esc($t['table_label'] ?? 'Label') ?></th>
                <th><?= esc($t['table_database'] ?? 'Database') ?></th>
                <th><?= esc($t['trash_deleted_at'] ?? 'Deleted at') ?></th>
                <th><?= esc($t['trash_purge_at'] ?? 'Purge at') ?></th>
                <th class="claro-table__actions"><?= esc($t['table_actions'] ?? 'Actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($tenants === []): ?>
                <tr>
                    <td colspan="6" style="text-align:center;padding:var(--claro-space-xl) var(--claro-space-m);color:var(--claro-color-text-light)">
                        <?= esc($t['trash_empty'] ?? 'Thùng rác trống.') ?>
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($tenants as $tenant): ?>
                <tr>
                    <td style="font-family:monospace;font-weight:600"><?= esc($tenant['name']) ?></td>
                    <td><?= esc($tenant['label'] ?? $tenant['name']) ?></td>
                    <td style="font-family:monospace;font-size:var(--claro-font-size-xs);color:var(--claro-gray-600)"><?= esc($tenant['db_name']) ?></td>
                    <td><?= esc((string) ($tenant['deleted_at'] ?? '')) ?></td>
                    <td><?= esc((string) ($tenant['purge_at'] ?? '')) ?></td>
                    <td class="claro-table__actions">
                        <form action="<?= site_url("desk/tenants/restore/{$tenant['name']}") ?>" method="post" style="display:inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="claro-button claro-button--small"><?= esc($t['restore'] ?? 'Restore') ?></button>
                        </form>
                        <form action="<?= site_url("desk/tenants/purge/{$tenant['name']}") ?>" method="post" style="display:inline" onsubmit="return confirm('<?= esc($t['purge_confirm'] ?? 'Xóa vĩnh viễn tenant? Backup sẽ được tạo trước khi xóa.') ?>')">
                            <?= csrf_field() ?>
                            <button type="submit" class="claro-button claro-button--small claro-button--danger"><?= esc($t['purge'] ?? 'Purge') ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
