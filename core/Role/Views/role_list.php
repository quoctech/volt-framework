<?php

/**
 * @var array<int, \Volt\Core\Role\Entities\RoleEntity> $roles
 */
?><div>
    <div class="mb-6 flex items-end justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Role List</h1>
            <p class="mt-1 text-sm text-slate-500">Quản lý các role (vai trò) trong hệ thống và phân quyền truy cập entity.</p>
        </div>
        <a href="<?= site_url('desk/roles/create') ?>" class="inline-flex items-center gap-1.5 border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Role
        </a>
    </div>

    <div class="overflow-hidden border border-slate-300 bg-white">
        <table class="min-w-full border-collapse text-sm">
            <thead>
                <tr class="border-b border-slate-300 bg-slate-50 text-left">
                    <th class="py-2.5 pl-4 pr-4 text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Role</th>
                    <th class="py-2.5 pr-4 text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Label</th>
                    <th class="py-2.5 pr-4 text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Description</th>
                    <th class="py-2.5 pr-4 text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">System</th>
                    <th class="py-2.5 pr-4 text-xs font-semibold uppercase tracking-[0.12em] text-slate-500"></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($roles === []): ?>
                    <tr>
                        <td colspan="5" class="py-8 pl-4 text-center text-sm text-slate-400">Chưa có role nào. <a href="<?= site_url('desk/roles/create') ?>" class="text-sky-700 underline">Tạo role đầu tiên</a>.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($roles as $role): ?>
                    <tr class="border-b border-slate-200 transition hover:bg-slate-50">
                        <td class="py-2.5 pl-4 pr-4">
                            <span class="font-mono text-sm font-medium text-slate-900"><?= esc($role->name) ?></span>
                        </td>
                        <td class="py-2.5 pr-4 text-slate-700"><?= esc($role->label) ?></td>
                        <td class="py-2.5 pr-4 text-slate-500"><?= esc($role->description ?? '') ?></td>
                        <td class="py-2.5 pr-4">
                            <?php if ($role->is_system): ?>
                                <span class="inline-flex rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-600">Yes</span>
                            <?php else: ?>
                                <span class="text-slate-400">No</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-2.5 pr-4">
                            <div class="flex items-center gap-2">
                                <a href="<?= site_url("desk/roles/permissions/{$role->name}") ?>" class="rounded border border-sky-300 bg-sky-50 px-2.5 py-1 text-xs font-medium text-sky-700 hover:bg-sky-100">Permissions</a>
                                <a href="<?= site_url("desk/roles/edit/{$role->name}") ?>" class="rounded border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50">Edit</a>
                                <?php if (! $role->is_system): ?>
                                    <form action="<?= site_url("desk/roles/delete/{$role->name}") ?>" method="post" class="inline" onsubmit="return confirm('Delete role «<?= esc($role->label) ?>»?')">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="rounded border border-red-200 px-2.5 py-1 text-xs font-medium text-red-600 hover:bg-red-50">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
