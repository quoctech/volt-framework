<?php

/**
 * @var array<int, array> $tenants
 */
$lang = \Volt\Core\Config\Lang\LangService::load();
$t = $lang['tenants'] ?? [];
$c = $lang['common'] ?? [];
?><div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900"><?= esc($t['title'] ?? 'Tenant List') ?></h1>
            <div class="mt-1 text-sm text-gray-600"><?= esc($t['description'] ?? 'Manage databases and tenant connections') ?></div>
        </div>
        <a href="<?= site_url('desk/tenants/create') ?>" class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded bg-gray-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            <?= esc($t['add_tenant'] ?? 'Add tenant') ?>
        </a>
    </div>

    <div class="overflow-x-auto rounded border border-gray-300 bg-white">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-gray-300 bg-gray-100">
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-600"><?= esc($t['table_name'] ?? 'Name') ?></th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-600"><?= esc($t['table_label'] ?? 'Label') ?></th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-600"><?= esc($t['table_database'] ?? 'Database') ?></th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-600"><?= esc($t['table_active'] ?? 'Active') ?></th>
                    <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-gray-600"><?= esc($t['table_actions'] ?? 'Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($tenants === []): ?>
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500"><?= esc($t['empty'] ?? 'No tenants yet.') ?> <a href="<?= site_url('desk/tenants/create') ?>" class="font-semibold underline hover:text-gray-800"><?= esc($t['create_first'] ?? 'Create first tenant') ?></a>.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($tenants as $i => $tenant): ?>
                    <tr class="border-b border-gray-200 <?= $i % 2 === 0 ? 'bg-white' : 'bg-gray-50' ?>">
                        <td class="px-4 py-3">
                            <span class="font-mono text-sm font-semibold text-gray-900"><?= esc($tenant['name']) ?></span>
                        </td>
                        <td class="px-4 py-3 text-gray-700"><?= esc($tenant['label'] ?? $tenant['name']) ?></td>
                        <td class="px-4 py-3 text-gray-600 font-mono text-xs"><?= esc($tenant['db_name']) ?></td>
                        <td class="px-4 py-3">
                            <?php if ((int) ($tenant['is_active'] ?? 0) === 1): ?>
                                <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700"><?= esc($t['active'] ?? 'Active') ?></span>
                            <?php else: ?>
                                <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500"><?= esc($t['inactive'] ?? 'Inactive') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <a href="<?= site_url("desk/tenants/edit/{$tenant['name']}") ?>" class="rounded px-2.5 py-1 text-sm font-medium text-gray-700 transition hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-1"><?= esc($t['edit'] ?? 'Edit') ?></a>
                                <form action="<?= site_url("desk/tenants/delete/{$tenant['name']}") ?>" method="post" class="inline" onsubmit="return confirm('<?= esc(str_replace('{label}', $tenant['label'] ?? $tenant['name'], $t['delete_confirm'] ?? 'Delete tenant?')) ?>')">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="rounded px-2.5 py-1 text-sm font-medium text-gray-700 transition hover:bg-red-100 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-1"><?= esc($t['delete'] ?? 'Delete') ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
