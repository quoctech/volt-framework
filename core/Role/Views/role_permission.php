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
    <div class="mb-6">
        <a href="<?= site_url('desk/roles') ?>" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            <?= esc($rp['back'] ?? 'Back to Role List') ?>
        </a>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900"><?= esc($role->label) ?></h1>
        <p class="mt-1 text-sm text-slate-500"><?= esc($rp['description'] ?? '') ?></p>
    </div>

    <form method="post" action="<?= site_url("desk/roles/permissions/{$role->name}") ?>">
        <?= csrf_field() ?>

        <div class="overflow-x-auto border border-slate-300 bg-white">
            <table class="min-w-full border-collapse text-sm" x-data="permissionGrid()">
                <thead>
                    <tr class="border-b border-slate-300 bg-slate-50 text-left">
                        <th class="sticky left-0 z-10 bg-slate-50 py-2.5 pl-4 pr-4 text-xs font-semibold uppercase tracking-[0.12em] text-slate-500"><?= esc($rp['entity'] ?? 'Entity') ?></th>
                        <?php foreach ($actions as $action): ?>
                            <th class="py-2.5 pr-4 text-center">
                                <label class="flex cursor-pointer flex-col items-center gap-1">
                                    <span class="text-xs font-semibold uppercase tracking-[0.08em] text-slate-500"><?= esc($actionLabels[$action] ?? $action) ?></span>
                                    <input
                                        type="checkbox"
                                        class="h-3.5 w-3.5 border-slate-400 text-slate-600"
                                        @click="toggleAll('<?= $action ?>', $event.target.checked)"
                                    >
                                </label>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($entityNames === []): ?>
                        <tr>
                            <td colspan="<?= count($actions) + 1 ?>" class="py-8 pl-4 text-center text-sm text-slate-400">
                                <?= esc($rp['empty'] ?? 'No entities yet.') ?> <a href="<?= site_url('desk/entity-builder') ?>" class="text-sky-700 underline"><?= esc($rp['create_entity'] ?? 'Create entity') ?></a>.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($entityNames as $entity): ?>
                        <?php $perm = $permissions[$entity] ?? null; ?>
                        <tr class="border-b border-slate-200 transition hover:bg-slate-50">
                            <td class="sticky left-0 bg-white py-2.5 pl-4 pr-4 font-medium text-slate-700"><?= esc($entity) ?></td>
                            <?php foreach ($actions as $action): ?>
                                <td class="py-2.5 pr-4 text-center">
                                    <input
                                        type="checkbox"
                                        name="entities[<?= esc($entity) ?>][<?= $action ?>]"
                                        value="1"
                                        <?= ($perm !== null && (int) ($perm[$action] ?? 0) === 1) ? 'checked' : '' ?>
                                        class="h-4 w-4 border-slate-400 text-slate-700 transition hover:border-slate-600"
                                    >
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-6 flex items-center gap-3">
            <button type="submit" class="border border-slate-900 bg-slate-900 px-5 py-2 text-sm font-medium text-white transition hover:bg-slate-700">
                <?= esc($rp['save'] ?? 'Save Permissions') ?>
            </button>
            <a href="<?= site_url('desk/roles') ?>" class="border border-slate-300 px-5 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50"><?= esc($rp['cancel'] ?? 'Cancel') ?></a>
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
