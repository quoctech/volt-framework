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
?><div>
    <div class="claro-table-toolbar">
        <div class="claro-table-toolbar__left">
            <div class="claro-page-header" style="margin-bottom:0">
                <h1 class="claro-page-header__title"><?= esc($el['title'] ?? 'Entity List') ?></h1>
                <p class="claro-page-header__subtitle"><?= esc($el['description'] ?? '') ?></p>
            </div>
        </div>
        <div class="claro-table-toolbar__right">
            <form method="get" action="<?= site_url('desk/entities') ?>" style="display:flex;gap:var(--claro-space-xs);align-items:center">
                <select name="module" class="claro-select" style="width:auto">
                    <option value=""><?= esc($el['all_modules'] ?? 'All modules') ?></option>
                    <?php foreach ($modules as $module): ?>
                        <option value="<?= esc($module) ?>" <?= $moduleFilter === $module ? 'selected' : '' ?>><?= esc($module) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="claro-button claro-button--small"><?= esc($el['filter'] ?? 'Filter') ?></button>
            </form>
        </div>
    </div>

    <table class="claro-table">
        <thead>
            <tr>
                <th><?= esc($el['table_entity'] ?? 'Entity') ?></th>
                <th><?= esc($el['table_label'] ?? 'Label') ?></th>
                <th><?= esc($el['table_module'] ?? 'Module') ?></th>
                <th><?= esc($el['table_autoname'] ?? 'Autoname') ?></th>
                <th><?= esc($el['table_submittable'] ?? 'Submittable') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($entities === []): ?>
                <tr>
                    <td colspan="5" style="text-align:center;padding:var(--claro-space-xl) var(--claro-space-m);color:var(--claro-color-text-light)"><?= esc($el['empty'] ?? 'No entities match the current filter.') ?></td>
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
                <tr>
                    <td style="font-weight:600">
                        <?php if ($hasAccess && $recordListUrl !== ''): ?>
                            <a href="<?= $recordListUrl ?>" style="color:var(--claro-color-primary);text-decoration:underline"><?= esc($entityName) ?></a>
                        <?php elseif ($isAdmin): ?>
                            <a href="<?= site_url('desk/entity-builder?entity=' . rawurlencode($entityName)) ?>" style="color:var(--claro-color-primary);text-decoration:underline"><?= esc($entityName) ?></a>
                        <?php else: ?>
                            <?= esc($entityName) ?>
                        <?php endif; ?>
                    </td>
                    <td><?= esc((string) ($entity['label'] ?? '')) ?></td>
                    <td style="color:var(--claro-gray-600)"><?= esc($moduleSnake) ?></td>
                    <td style="color:var(--claro-gray-600)"><?= esc((string) ($entity['autoname'] ?? '')) ?></td>
                    <td><?= ! empty($entity['is_submittable']) ? '<span class="claro-badge claro-badge--success">' . esc($c['yes'] ?? 'Yes') . '</span>' : '<span style="color:var(--claro-gray-500)">' . esc($c['no'] ?? 'No') . '</span>' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
