<?php

/** @var array<int, string> $modules */
/** @var string $moduleFilter */
/** @var array<int, array<string, mixed>> $entities */
/** @var bool $isAdmin */
/** @var string $currentUserName */
$isAdmin = $isAdmin ?? false;
$currentUserName = $currentUserName ?? '';
$moduleFilter = $moduleFilter ?? '';
$modules = $modules ?? [];
$entities = $entities ?? [];
$resolver = service('voltPermissionResolver');
?><div>
    <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Entity List</h1>
            <p class="mt-1 text-sm text-slate-500">Danh sách các entity trong hệ thống. Click vào tên entity để xem dữ liệu.</p>
        </div>

        <form method="get" action="<?= site_url('desk/entities') ?>" class="flex gap-2">
            <select name="module" class="border border-slate-300 bg-white px-3 py-2 text-sm outline-none">
                <option value="">All modules</option>
                <?php foreach ($modules as $module): ?>
                    <option value="<?= esc($module) ?>" <?= $moduleFilter === $module ? 'selected' : '' ?>><?= esc($module) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="border border-slate-300 bg-white px-4 py-2 text-sm hover:bg-slate-50">Filter</button>
        </form>
    </div>

    <div class="overflow-hidden border border-slate-300 bg-white">
        <table class="min-w-full border-collapse text-sm">
            <thead>
                <tr class="border-b border-slate-300 bg-slate-50 text-left">
                    <th class="py-2.5 pl-4 pr-4 text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Entity</th>
                    <th class="py-2.5 pr-4 text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Label</th>
                    <th class="py-2.5 pr-4 text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Module</th>
                    <th class="py-2.5 pr-4 text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Autoname</th>
                    <th class="py-2.5 pr-4 text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Submittable</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($entities === []): ?>
                    <tr>
                        <td colspan="5" class="py-8 pl-4 text-center text-sm text-slate-400">Không có entity nào khớp bộ lọc hiện tại.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($entities as $entity): ?>
                    <?php
                    $entityName = (string) ($entity['name'] ?? '');
                    $moduleSnake = (string) ($entity['module'] ?? '');
                    $recordListUrl = $moduleSnake !== '' && $entityName !== ''
                        ? site_url("{$moduleSnake}/{$entityName}")
                        : '';
                    $hasAccess = $isAdmin || ($entityName !== '' && $resolver->hasEntityPermission($entityName));
                    ?>
                    <tr class="border-b border-slate-200 transition hover:bg-slate-50">
                        <td class="py-2.5 pl-4 pr-4">
                            <?php if ($hasAccess && $recordListUrl !== ''): ?>
                                <a href="<?= $recordListUrl ?>" class="font-medium text-sky-700 underline hover:text-sky-800">
                                    <?= esc($entityName) ?>
                                </a>
                            <?php elseif ($isAdmin): ?>
                                <a href="<?= site_url('desk/entity-builder?entity=' . rawurlencode($entityName)) ?>" class="font-medium text-sky-700 underline hover:text-sky-800">
                                    <?= esc($entityName) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-slate-700"><?= esc($entityName) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="py-2.5 pr-4 text-slate-700"><?= esc((string) ($entity['label'] ?? '')) ?></td>
                        <td class="py-2.5 pr-4 text-slate-600"><?= esc($moduleSnake) ?></td>
                        <td class="py-2.5 pr-4 text-slate-600"><?= esc((string) ($entity['autoname'] ?? '')) ?></td>
                        <td class="py-2.5 pr-4"><?= ! empty($entity['is_submittable']) ? 'Yes' : 'No' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
