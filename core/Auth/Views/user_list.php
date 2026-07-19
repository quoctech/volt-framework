<?php

/**
 * @var array<int, \Volt\Core\Auth\Entities\UserEntity> $users
 */
?><div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Người dùng</h1>
            <div class="mt-1 text-sm text-gray-600">Quản lý tài khoản người dùng trong hệ thống.</div>
        </div>
        <a href="<?= site_url('desk/users/create') ?>" class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded bg-gray-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Thêm người dùng
        </a>
    </div>

    <?php if ($users === []): ?>
        <div class="rounded border border-gray-300 bg-white px-6 py-12 text-center">
            <p class="text-gray-600">Chưa có người dùng nào.</p>
            <a href="<?= site_url('desk/users/create') ?>" class="mt-4 inline-flex items-center gap-1.5 rounded bg-gray-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-600">Thêm người dùng</a>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto rounded border border-gray-300 bg-white">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-300 bg-gray-100">
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-600">Người dùng</th>
                        <th class="hidden px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-600 md:table-cell">Vai trò</th>
                        <th class="hidden px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-600 sm:table-cell">Trạng thái</th>
                        <th class="hidden px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-600 lg:table-cell">Lần cuối</th>
                        <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-gray-600">Thao tác</th>
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
                        <tr class="border-b border-gray-200 <?= $i % 2 === 0 ? 'bg-white' : 'bg-gray-50' ?>">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-300 text-sm font-semibold text-gray-700"><?= esc(mb_strtoupper(mb_substr($user->name, 0, 1))) ?></span>
                                    <div class="min-w-0">
                                        <div class="truncate font-semibold text-gray-900"><?= esc($user->name) ?></div>
                                        <div class="truncate text-xs text-gray-500 md:hidden">
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
                                        <span class="rounded bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-700"><?= esc((string) $role) ?></span>
                                    <?php endforeach; ?>
                                    <?php if ($userRoles === []): ?>
                                        <span class="text-gray-400">—</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="hidden px-4 py-3 sm:table-cell">
                                <?php if ($user->is_active): ?>
                                    <span class="text-gray-800">Kích hoạt</span>
                                <?php else: ?>
                                    <span class="text-gray-500">Vô hiệu</span>
                                <?php endif; ?>
                            </td>
                            <td class="hidden px-4 py-3 text-gray-600 lg:table-cell"><?= esc($user->last_login_at ?? '—') ?></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="<?= site_url("desk/users/edit/{$user->name}") ?>" class="rounded px-2.5 py-1 text-sm font-medium text-gray-700 transition hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-1">Sửa</a>
                                    <form action="<?= site_url("desk/users/delete/{$user->name}") ?>" method="post" class="inline" onsubmit="return confirm('Xoá người dùng «<?= esc($user->name) ?>»?')">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="rounded px-2.5 py-1 text-sm font-medium text-gray-700 transition hover:bg-red-100 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-1">Xoá</button>
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
