<?php

/**
 * @var array<int, \Volt\Core\Auth\Entities\UserEntity> $users
 */
$lang = \Volt\Core\Config\Lang\LangService::load();
$u = $lang['users'] ?? [];
$c = $lang['common'] ?? [];
?><div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900"><?= esc($u['title'] ?? 'Users') ?></h1>
            <div class="mt-1 text-sm text-slate-600"><?= esc($u['description'] ?? '') ?></div>
        </div>
        <a href="<?= site_url('desk/users/create') ?>" class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            <?= esc($u['add_user'] ?? 'Add user') ?>
        </a>
    </div>

    <?php if ($users === []): ?>
        <div class="rounded border border-slate-300 bg-white px-6 py-12 text-center">
            <p class="text-slate-600"><?= esc($u['empty'] ?? 'No users yet.') ?></p>
            <a href="<?= site_url('desk/users/create') ?>" class="mt-4 inline-flex items-center gap-1.5 rounded border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800"><?= esc($u['add_user'] ?? 'Add user') ?></a>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto rounded border border-slate-300 bg-white">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-300 bg-slate-100">
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-600"><?= esc($u['table_user'] ?? 'User') ?></th>
                        <th class="hidden px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-600 md:table-cell"><?= esc($u['table_roles'] ?? 'Roles') ?></th>
                        <th class="hidden px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-600 sm:table-cell"><?= esc($u['table_status'] ?? 'Status') ?></th>
                        <th class="hidden px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-600 lg:table-cell"><?= esc($u['table_last_login'] ?? 'Last login') ?></th>
                        <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-600"><?= esc($u['table_actions'] ?? 'Actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $i => $user): ?>
                        <?php
                        $userRoles = $user->roles;
                        if (is_string($userRoles)) {
                            $decoded = json_decode($userRoles, true);
                            $userRoles = is_array($decoded) ? $decoded : [];
                        }
                        $userRoles = is_array($userRoles) ? $userRoles : [];
                        ?>
                        <tr class="border-b border-slate-200 <?= $i % 2 === 0 ? 'bg-white' : 'bg-slate-50' ?>">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-300 text-sm font-semibold text-slate-700"><?= esc(mb_strtoupper(mb_substr($user->name, 0, 1))) ?></span>
                                    <div class="min-w-0">
                                        <div class="truncate font-semibold text-slate-900"><?= esc($user->name) ?></div>
                                        <div class="truncate text-xs text-slate-500 md:hidden">
                                            <?php
                                            $roleNames = array_map(static fn (string $r): string => esc($r), $userRoles);
                                            echo $roleNames !== [] ? implode(', ', $roleNames) : '—';
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden px-4 py-3 md:table-cell">
                                <div class="flex flex-wrap gap-1">
                                    <?php foreach ($userRoles as $role): ?>
                                        <span class="rounded bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-700"><?= esc((string) $role) ?></span>
                                    <?php endforeach; ?>
                                    <?php if ($userRoles === []): ?>
                                        <span class="text-slate-400">—</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="hidden px-4 py-3 sm:table-cell">
                                <?php if ($user->is_active): ?>
                                    <span class="text-slate-800"><?= esc($u['active'] ?? 'Active') ?></span>
                                <?php else: ?>
                                    <span class="text-slate-500"><?= esc($u['inactive'] ?? 'Inactive') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="hidden px-4 py-3 text-slate-600 lg:table-cell"><?= esc($user->last_login_at ?? $u['never'] ?? '—') ?></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="<?= site_url("desk/users/edit/{$user->name}") ?>" class="rounded px-2.5 py-1 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus:outline-none"><?= esc($u['edit'] ?? 'Edit') ?></a>
                                    <form action="<?= site_url("desk/users/delete/{$user->name}") ?>" method="post" class="inline" onsubmit="return confirm('<?= esc(str_replace('{name}', $user->name, $u['delete_confirm'] ?? 'Delete user?')) ?>')">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="rounded px-2.5 py-1 text-sm font-medium text-slate-700 transition hover:bg-red-50 hover:text-red-700 focus:outline-none"><?= esc($u['delete'] ?? 'Delete') ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
