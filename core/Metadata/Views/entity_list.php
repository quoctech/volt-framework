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
$lang = \Volt\Core\Config\Lang\LangService::load();
$el = $lang['entity_list'] ?? [];
$c = $lang['common'] ?? [];
?><div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900"><?= esc($el['title'] ?? 'Entity List') ?></h1>
            <div class="mt-1 text-sm text-gray-600"><?= esc($el['description'] ?? '') ?></div>
        </div>

        <form method="get" action="<?= site_url('desk/entities') ?>" class="flex gap-2">
            <select name="module" class="rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 outline-none focus:border-gray-500 focus:ring-1 focus:ring-gray-500">
                <option value=""><?= esc($el['all_modules'] ?? 'All modules') ?></option>
                <?php foreach ($modules as $module): ?>
                    <option value="<?= esc($module) ?>" <?= $moduleFilter === $module ? 'selected' : '' ?>><?= esc($module) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-1"><?= esc($el['filter'] ?? 'Filter') ?></button>
        </form>
    </div>

    <div class="overflow-x-auto rounded border border-gray-300 bg-white">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-gray-300 bg-gray-100">
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-600"><?= esc($el['table_entity'] ?? 'Entity') ?></th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-600"><?= esc($el['table_label'] ?? 'Label') ?></th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-600"><?= esc($el['table_module'] ?? 'Module') ?></th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-600"><?= esc($el['table_autoname'] ?? 'Autoname') ?></th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-600"><?= esc($el['table_submittable'] ?? 'Submittable') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($entities === []): ?>
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500"><?= esc($el['empty'] ?? 'No entities match the current filter.') ?></td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($entities as $i => $entity): ?>
                    <?php
                    $entityName = (string) ($entity['name'] ?? '');
                    $moduleSnake = (string) ($entity['module'] ?? '');
                    $recordListUrl = $moduleSnake !== '' && $entityName !== ''
                        ? site_url("{$moduleSnake}/{$entityName}")
                        : '';
                    $hasAccess = $isAdmin || ($entityName !== '' && $resolver->hasEntityPermission($entityName));
                    ?>
                    <tr class="border-b border-gray-200 <?= $i % 2 === 0 ? 'bg-white' : 'bg-gray-50' ?>">
                        <td class="px-4 py-3">
                            <?php if ($hasAccess && $recordListUrl !== ''): ?>
                                <a href="<?= $recordListUrl ?>" class="font-semibold text-gray-900 underline hover:text-gray-700">
                                    <?= esc($entityName) ?>
                                </a>
                            <?php elseif ($isAdmin): ?>
                                <a href="<?= site_url('desk/entity-builder?entity=' . rawurlencode($entityName)) ?>" class="font-semibold text-gray-900 underline hover:text-gray-700">
                                    <?= esc($entityName) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-gray-700"><?= esc($entityName) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-gray-700"><?= esc((string) ($entity['label'] ?? '')) ?></td>
                        <td class="px-4 py-3 text-gray-600"><?= esc($moduleSnake) ?></td>
                        <td class="px-4 py-3 text-gray-600"><?= esc((string) ($entity['autoname'] ?? '')) ?></td>
                        <td class="px-4 py-3"><?= ! empty($entity['is_submittable']) ? esc($c['yes'] ?? 'Yes') : esc($c['no'] ?? 'No') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
