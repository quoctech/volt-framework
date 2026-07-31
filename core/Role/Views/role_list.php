<?php

/**
 * @var array<int, \Volt\Core\Role\Entities\RoleEntity> $roles
 */
$lang = \Volt\Core\Config\Lang\LangService::load();
$r = $lang['roles'] ?? [];
$c = $lang['common'] ?? [];
?><div>
    <div class="claro-table-toolbar">
        <div class="claro-table-toolbar__left">
            <div class="claro-page-header" style="margin-bottom:0">
                <h1 class="claro-page-header__title"><?= esc($r['title'] ?? 'Role List') ?></h1>
                <p class="claro-page-header__subtitle"><?= esc($r['description'] ?? '') ?></p>
            </div>
        </div>
        <div class="claro-table-toolbar__right">
            <a href="<?= site_url('desk/roles/create') ?>" class="claro-button claro-button--primary">
                + <?= esc($r['add_role'] ?? 'Add role') ?>
            </a>
        </div>
    </div>

    <table class="claro-table">
        <thead>
            <tr>
                <th><?= esc($r['table_role'] ?? 'Role') ?></th>
                <th><?= esc($r['table_label'] ?? 'Label') ?></th>
                <th><?= esc($r['table_description'] ?? 'Description') ?></th>
                <th><?= esc($r['table_system'] ?? 'System') ?></th>
                <th class="claro-table__actions"><?= esc($r['table_actions'] ?? 'Actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($roles === []): ?>
                <tr>
                    <td colspan="5" style="text-align:center;padding:var(--claro-space-xl) var(--claro-space-m);color:var(--claro-color-text-light)">
                        <?= esc($r['empty'] ?? 'No roles yet.') ?> <a href="<?= site_url('desk/roles/create') ?>" style="font-weight:700"><?= esc($r['create_first'] ?? 'Create first role') ?></a>.
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($roles as $role): ?>
                <tr>
                    <td style="font-family:monospace;font-weight:600"><?= esc($role->name) ?></td>
                    <td><?= esc($role->label) ?></td>
                    <td style="color:var(--claro-gray-600)"><?= esc($role->description ?? '') ?></td>
                    <td>
                        <?php if ($role->is_system): ?>
                            <span class="claro-badge"><?= esc($r['yes'] ?? 'Yes') ?></span>
                        <?php else: ?>
                            <span style="color:var(--claro-gray-500)"><?= esc($r['no'] ?? 'No') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="claro-table__actions">
                        <a href="<?= site_url("desk/roles/permissions/{$role->name}") ?>" class="claro-button claro-button--small"><?= esc($r['permissions'] ?? 'Permissions') ?></a>
                        <a href="<?= site_url("desk/roles/edit/{$role->name}") ?>" class="claro-button claro-button--small"><?= esc($r['edit'] ?? 'Edit') ?></a>
                        <?php if (! $role->is_system): ?>
                            <form action="<?= site_url("desk/roles/delete/{$role->name}") ?>" method="post" style="display:inline" onsubmit="return confirm('<?= esc(str_replace('{label}', $role->label, $r['delete_confirm'] ?? 'Delete role?')) ?>')">
                                <?= csrf_field() ?>
                                <button type="submit" class="claro-button claro-button--small claro-button--danger"><?= esc($r['delete'] ?? 'Delete') ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
