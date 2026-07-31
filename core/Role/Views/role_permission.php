<?php

/**
 * @var \Volt\Core\Role\Entities\RoleEntity $role
 * @var array<string, array<string, mixed>> $permissions
 * @var array<int, string> $entityNames
 */
$actions = ['read', 'write', 'create', 'delete', 'submit', 'import', 'amend', 'report', 'export', 'print', 'email'];
$lang = \Volt\Core\Config\Lang\LangService::load();
$rp = $lang['role_permission'] ?? [];
$c = $lang['common'] ?? [];
$actionLabels = [
    'read'   => $c['read'] ?? 'Read',
    'write'  => $c['write'] ?? 'Write',
    'create' => $c['create'] ?? 'Create',
    'delete' => $c['delete'] ?? 'Delete',
    'submit' => $c['submit'] ?? 'Submit',
    'import' => $c['import'] ?? 'Import',
    'amend'  => $c['amend'] ?? 'Amend',
    'report' => $c['report'] ?? 'Report',
    'export' => $c['export'] ?? 'Export',
    'print'  => $c['print'] ?? 'Print',
    'email'  => $c['email'] ?? 'Email',
];
?><div>
    <div style="margin-bottom:var(--claro-space-m)">
        <a href="<?= site_url('desk/roles') ?>" class="claro-button claro-button--link" style="gap:var(--claro-space-xs)">
            &larr; <?= esc($rp['back'] ?? 'Back to Role List') ?>
        </a>
    </div>

    <div class="claro-page-header">
        <h1 class="claro-page-header__title"><?= esc($role->label) ?></h1>
        <p class="claro-page-header__subtitle"><?= esc($rp['description'] ?? '') ?></p>
    </div>

    <form method="post" action="<?= site_url("desk/roles/permissions/{$role->name}") ?>">
        <?= csrf_field() ?>

        <table class="claro-table claro-permission-matrix" x-data="permissionGrid()">
            <thead>
                <tr>
                    <th style="text-align:left"><?= esc($rp['entity'] ?? 'Entity') ?></th>
                    <?php foreach ($actions as $action): ?>
                        <th style="text-align:center">
                            <label style="display:flex;flex-direction:column;align-items:center;gap:4px;cursor:pointer;font-size:var(--claro-font-size-xs);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--claro-color-text-light)">
                                <?= esc($actionLabels[$action] ?? $action) ?>
                                <input type="checkbox" style="accent-color:var(--claro-color-primary)" @click="toggleAll('<?= $action ?>', $event.target.checked)">
                            </label>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($entityNames === []): ?>
                    <tr>
                        <td colspan="<?= count($actions) + 1 ?>" style="text-align:center;padding:var(--claro-space-xl) var(--claro-space-m);color:var(--claro-color-text-light)">
                            <?= esc($rp['empty'] ?? 'No entities yet.') ?> <a href="<?= site_url('desk/entity-builder') ?>" style="font-weight:700"><?= esc($rp['create_entity'] ?? 'Create entity') ?></a>.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($entityNames as $entity): ?>
                    <?php $perm = $permissions[$entity] ?? null; ?>
                    <tr>
                        <td style="text-align:left;font-weight:500"><?= esc($entity) ?></td>
                        <?php foreach ($actions as $action): ?>
                            <td style="text-align:center">
                                <input type="checkbox" name="entities[<?= esc($entity) ?>][<?= $action ?>]" value="1" <?= ($perm !== null && (int) ($perm[$action] ?? 0) === 1) ? 'checked' : '' ?> style="accent-color:var(--claro-color-primary);cursor:pointer">
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="claro-form-actions" style="margin-top:var(--claro-space-l)">
            <button type="submit" class="claro-button claro-button--primary"><?= esc($rp['save'] ?? 'Save Permissions') ?></button>
            <a href="<?= site_url('desk/roles') ?>" class="claro-button"><?= esc($rp['cancel'] ?? 'Cancel') ?></a>
        </div>
    </form>
</div>

<script>
function permissionGrid() {
    return {
        toggleAll(action, checked) {
            document.querySelectorAll(`input[name$="[${action}]"]`).forEach(cb => cb.checked = checked);
        }
    };
}
</script>
