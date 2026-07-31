<?php

/**
 * @var array<int, \Volt\Core\Auth\Entities\UserEntity> $users
 */
$lang = \Volt\Core\Config\Lang\LangService::load();
$u = $lang['users'] ?? [];
$c = $lang['common'] ?? [];
?><div>
    <div class="claro-table-toolbar">
        <div class="claro-table-toolbar__left">
            <div class="claro-page-header" style="margin-bottom:0">
                <h1 class="claro-page-header__title"><?= esc($u['title'] ?? 'Users') ?></h1>
                <p class="claro-page-header__subtitle"><?= esc($u['description'] ?? '') ?></p>
            </div>
        </div>
        <div class="claro-table-toolbar__right">
            <a href="<?= site_url('desk/users/create') ?>" class="claro-button claro-button--primary">
                + <?= esc($u['add_user'] ?? 'Add user') ?>
            </a>
        </div>
    </div>

    <?php if ($users === []): ?>
        <div class="claro-empty">
            <p class="claro-empty__text"><?= esc($u['empty'] ?? 'No users yet.') ?></p>
            <a href="<?= site_url('desk/users/create') ?>" class="claro-button claro-button--primary" style="margin-top:var(--claro-space-m)"><?= esc($u['add_user'] ?? 'Add user') ?></a>
        </div>
    <?php else: ?>
        <table class="claro-table">
            <thead>
                <tr>
                    <th><?= esc($u['table_user'] ?? 'User') ?></th>
                    <th class="hidden md:table-cell"><?= esc($u['table_roles'] ?? 'Roles') ?></th>
                    <th class="hidden sm:table-cell"><?= esc($u['table_status'] ?? 'Status') ?></th>
                    <th class="hidden lg:table-cell"><?= esc($u['table_last_login'] ?? 'Last login') ?></th>
                    <th class="claro-table__actions"><?= esc($u['table_actions'] ?? 'Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <?php
                    $userRoles = $user->roles;
                    if (is_string($userRoles)) {
                        $decoded = json_decode($userRoles, true);
                        $userRoles = is_array($decoded) ? $decoded : [];
                    }
                    $userRoles = is_array($userRoles) ? $userRoles : [];
                    ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:var(--claro-space-s)">
                                <span style="display:flex;align-items:center;justify-content:center;width:2rem;height:2rem;border-radius:50%;background:var(--claro-gray-200);font-size:var(--claro-font-size-s);font-weight:700;flex-shrink:0"><?= esc(mb_strtoupper(mb_substr($user->name, 0, 1))) ?></span>
                                <div style="min-width:0">
                                    <div style="font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= esc($user->name) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="hidden md:table-cell">
                            <div style="display:flex;flex-wrap:wrap;gap:4px">
                                <?php foreach ($userRoles as $role): ?>
                                    <span class="claro-badge"><?= esc((string) $role) ?></span>
                                <?php endforeach; ?>
                                <?php if ($userRoles === []): ?>
                                    <span style="color:var(--claro-gray-400)">—</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="hidden sm:table-cell">
                            <?php if ($user->is_active): ?>
                                <span class="claro-badge claro-badge--success"><?= esc($u['active'] ?? 'Active') ?></span>
                            <?php else: ?>
                                <span class="claro-badge"><?= esc($u['inactive'] ?? 'Inactive') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="hidden lg:table-cell" style="color:var(--claro-gray-600)"><?= esc($user->last_login_at ?? $u['never'] ?? '—') ?></td>
                        <td class="claro-table__actions">
                            <a href="<?= site_url("desk/users/edit/{$user->name}") ?>" class="claro-button claro-button--small"><?= esc($u['edit'] ?? 'Edit') ?></a>
                            <form action="<?= site_url("desk/users/delete/{$user->name}") ?>" method="post" style="display:inline" onsubmit="return confirm('<?= esc(str_replace('{name}', $user->name, $u['delete_confirm'] ?? 'Delete user?')) ?>')">
                                <?= csrf_field() ?>
                                <button type="submit" class="claro-button claro-button--small claro-button--danger"><?= esc($u['delete'] ?? 'Delete') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
