<?php

/**
 * @var array<int, \Volt\Core\Auth\Entities\UserEntity> $users
 */
?><div>
    <div class="mb-6 flex items-end justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">User List</h1>
            <p class="mt-1 text-sm text-slate-500">Quản lý người dùng trong hệ thống.</p>
        </div>
        <a href="<?= site_url('desk/users/create') ?>" class="inline-flex items-center gap-1.5 border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New User
        </a>
    </div>

    <div class="overflow-hidden border border-slate-300 bg-white">
        <table class="min-w-full border-collapse text-sm">
            <thead>
                <tr class="border-b border-slate-300 bg-slate-50 text-left">
                    <th class="py-2.5 pl-4 pr-4 text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Name</th>
                    <th class="py-2.5 pr-4 text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Roles</th>
                    <th class="py-2.5 pr-4 text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Status</th>
                    <th class="py-2.5 pr-4 text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Last Login</th>
                    <th class="py-2.5 pr-4 text-xs font-semibold uppercase tracking-[0.12em] text-slate-500"></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($users === []): ?>
                    <tr>
                        <td colspan="5" class="py-8 pl-4 text-center text-sm text-slate-400">Chưa có người dùng nào.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($users as $user): ?>
                    <tr class="border-b border-slate-200 transition hover:bg-slate-50">
                        <td class="py-2.5 pl-4 pr-4">
                            <span class="font-medium text-slate-900"><?= esc($user->name) ?></span>
                        </td>
                        <td class="py-2.5 pr-4">
                            <?php
                            $userRoles = $user->roles;
                            if (is_string($userRoles)) {
                                $decoded = json_decode($userRoles, true);
                                $userRoles = is_array($decoded) ? $decoded : [];
                            }
                            $userRoles = is_array($userRoles) ? $userRoles : [];
                            ?>
                            <div class="flex flex-wrap gap-1">
                                <?php foreach ($userRoles as $role): ?>
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600"><?= esc((string) $role) ?></span>
                                <?php endforeach; ?>
                                <?php if ($userRoles === []): ?>
                                    <span class="text-xs text-slate-400">—</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="py-2.5 pr-4">
                            <?php if ($user->is_active): ?>
                                <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Active</span>
                            <?php else: ?>
                                <span class="inline-flex rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-700">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-2.5 pr-4 text-slate-500">
                            <?= esc($user->last_login_at ?? '—') ?>
                        </td>
                        <td class="py-2.5 pr-4">
                            <div class="flex items-center gap-2">
                                <a href="<?= site_url("desk/users/edit/{$user->name}") ?>" class="rounded border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50">Edit</a>
                                <form action="<?= site_url("desk/users/delete/{$user->name}") ?>" method="post" class="inline" onsubmit="return confirm('Delete user «<?= esc($user->name) ?>»?')">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="rounded border border-red-200 px-2.5 py-1 text-xs font-medium text-red-600 hover:bg-red-50">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
