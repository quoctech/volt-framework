<?php

/**
 * @var array<int, \Volt\Core\Role\Entities\RoleEntity> $roles
 */
$lang = \Volt\Core\Config\Lang\LangService::load();
$r = $lang['roles'] ?? [];
$c = $lang['common'] ?? [];
?><div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900"><?= esc($r['title'] ?? 'Role List') ?></h1>
            <div class="mt-1 text-sm text-gray-600"><?= esc($r['description'] ?? '') ?></div>
        </div>
        <a href="<?= site_url('desk/roles/create') ?>" class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded bg-gray-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            <?= esc($r['add_role'] ?? 'Add role') ?>
        </a>
    </div>

    <div class="overflow-x-auto rounded border border-gray-300 bg-white">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-gray-300 bg-gray-100">
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-600"><?= esc($r['table_role'] ?? 'Role') ?></th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-600"><?= esc($r['table_label'] ?? 'Label') ?></th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-600"><?= esc($r['table_description'] ?? 'Description') ?></th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-600"><?= esc($r['table_system'] ?? 'System') ?></th>
                    <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-gray-600"><?= esc($r['table_actions'] ?? 'Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($roles === []): ?>
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500"><?= esc($r['empty'] ?? 'No roles yet.') ?> <a href="<?= site_url('desk/roles/create') ?>" class="font-semibold underline hover:text-gray-800"><?= esc($r['create_first'] ?? 'Create first role') ?></a>.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($roles as $i => $role): ?>
                    <tr class="border-b border-gray-200 <?= $i % 2 === 0 ? 'bg-white' : 'bg-gray-50' ?>">
                        <td class="px-4 py-3">
                            <span class="font-mono text-sm font-semibold text-gray-900"><?= esc($role->name) ?></span>
                        </td>
                        <td class="px-4 py-3 text-gray-700"><?= esc($role->label) ?></td>
                        <td class="px-4 py-3 text-gray-600"><?= esc($role->description ?? '') ?></td>
                        <td class="px-4 py-3">
                            <?php if ($role->is_system): ?>
                                <span class="rounded bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-700"><?= esc($r['yes'] ?? 'Yes') ?></span>
                            <?php else: ?>
                                <span class="text-gray-500"><?= esc($r['no'] ?? 'No') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <a href="<?= site_url("desk/roles/permissions/{$role->name}") ?>" class="rounded px-2.5 py-1 text-sm font-medium text-gray-700 transition hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-1"><?= esc($r['permissions'] ?? 'Permissions') ?></a>
                                <a href="<?= site_url("desk/roles/edit/{$role->name}") ?>" class="rounded px-2.5 py-1 text-sm font-medium text-gray-700 transition hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-1"><?= esc($r['edit'] ?? 'Edit') ?></a>
                                <?php if (! $role->is_system): ?>
                                    <form action="<?= site_url("desk/roles/delete/{$role->name}") ?>" method="post" class="inline" onsubmit="return confirm('<?= esc(str_replace('{label}', $role->label, $r['delete_confirm'] ?? 'Delete role?')) ?>')">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="rounded px-2.5 py-1 text-sm font-medium text-gray-700 transition hover:bg-red-100 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-1"><?= esc($r['delete'] ?? 'Delete') ?></button>
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
