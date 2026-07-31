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
                <h1 class="claro-page-header__title"><?= esc($t['title'] ?? 'Tenant List') ?></h1>
                <p class="claro-page-header__subtitle"><?= esc($t['description'] ?? 'Manage databases and tenant connections') ?></p>
            </div>
        </div>
        <div class="claro-table-toolbar__right">
            <a href="<?= site_url('desk/tenants/create') ?>" class="claro-button claro-button--primary">
                + <?= esc($t['add_tenant'] ?? 'Add tenant') ?>
            </a>
        </div>
    </div>

    <table class="claro-table">
        <thead>
            <tr>
                <th><?= esc($t['table_name'] ?? 'Name') ?></th>
                <th><?= esc($t['table_label'] ?? 'Label') ?></th>
                <th><?= esc($t['table_database'] ?? 'Database') ?></th>
                <th><?= esc($t['table_active'] ?? 'Active') ?></th>
                <th class="claro-table__actions"><?= esc($t['table_actions'] ?? 'Actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($tenants === []): ?>
                <tr>
                    <td colspan="5" style="text-align:center;padding:var(--claro-space-xl) var(--claro-space-m);color:var(--claro-color-text-light)">
                        <?= esc($t['empty'] ?? 'No tenants yet.') ?> <a href="<?= site_url('desk/tenants/create') ?>" style="font-weight:700"><?= esc($t['create_first'] ?? 'Create first tenant') ?></a>.
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($tenants as $tenant): ?>
                <tr>
                    <td style="font-family:monospace;font-weight:600"><?= esc($tenant['name']) ?></td>
                    <td><?= esc($tenant['label'] ?? $tenant['name']) ?></td>
                    <td style="font-family:monospace;font-size:var(--claro-font-size-xs);color:var(--claro-gray-600)"><?= esc($tenant['db_name']) ?></td>
                    <td>
                        <?php if ((int) ($tenant['is_active'] ?? 0) === 1): ?>
                            <span class="claro-badge claro-badge--success"><?= esc($t['active'] ?? 'Active') ?></span>
                        <?php else: ?>
                            <span class="claro-badge"><?= esc($t['inactive'] ?? 'Inactive') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="claro-table__actions">
                        <a href="<?= site_url("desk/tenants/edit/{$tenant['name']}") ?>" class="claro-button claro-button--small"><?= esc($t['edit'] ?? 'Edit') ?></a>
                        <form action="<?= site_url("desk/tenants/delete/{$tenant['name']}") ?>" method="post" style="display:inline" onsubmit="return confirm('<?= esc(str_replace('{label}', $tenant['label'] ?? $tenant['name'], $t['delete_confirm'] ?? 'Delete tenant?')) ?>')">
                            <?= csrf_field() ?>
                            <button type="submit" class="claro-button claro-button--small claro-button--danger"><?= esc($t['delete'] ?? 'Delete') ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
