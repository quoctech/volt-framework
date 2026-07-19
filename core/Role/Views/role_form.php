<?php

/**
 * @var \Volt\Core\Role\Entities\RoleEntity|null $role
 * @var array<string, string> $errors
 */
$isEdit = $role !== null;
$lang = \Volt\Core\Config\Lang\LangService::load();
$rf = $lang['role_form'] ?? [];
$c = $lang['common'] ?? [];
?><div>
    <div class="mb-6">
        <a href="<?= site_url('desk/roles') ?>" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            <?= esc($rf['back'] ?? 'Back to Role List') ?>
        </a>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900"><?= $isEdit ? esc($rf['edit_title'] ?? 'Edit Role') : esc($rf['new_title'] ?? 'New Role') ?></h1>
        <p class="mt-1 text-sm text-slate-500"><?= $isEdit ? esc($rf['edit_desc'] ?? '') : esc($rf['new_desc'] ?? '') ?></p>
    </div>

    <?php if ($errors !== []): ?>
        <div class="mb-4 border border-red-300 bg-red-50 p-4 text-sm text-red-800">
            <p class="mb-1 font-medium"><?= esc($rf['errors_title'] ?? 'Please fix the following errors:') ?></p>
            <ul class="ml-4 list-disc space-y-1">
                <?php foreach ($errors as $error): ?>
                    <li><?= esc($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="border border-slate-300 bg-white p-6">
        <form action="<?= $isEdit ? site_url("desk/roles/update/{$role->name}") : site_url('desk/roles/store') ?>" method="post">
            <?= csrf_field() ?>

            <div class="grid gap-5">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500" for="label"><?= esc($rf['label_field'] ?? 'Label') ?></label>
                    <input
                        id="label"
                        name="label"
                        type="text"
                        required
                        maxlength="255"
                        value="<?= esc($role->label ?? '') ?>"
                        placeholder="HR Manager"
                        class="w-full border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-slate-600"
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500" for="name"><?= esc($rf['name_field'] ?? 'Name') ?></label>
                    <input
                        id="name"
                        name="name"
                        type="text"
                        required
                        maxlength="100"
                        value="<?= esc($role->name ?? '') ?>"
                        placeholder="hr_manager"
                        class="w-full border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-slate-600 <?= $isEdit ? 'bg-slate-100 text-slate-500' : '' ?>"
                        <?= $isEdit ? 'readonly' : '' ?>
                        pattern="[a-z0-9_]+"
                    >
                    <?php if (! $isEdit): ?>
                        <p class="mt-1 text-xs text-slate-500"><?= esc($rf['name_hint'] ?? '') ?></p>
                    <?php endif; ?>
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500" for="description"><?= esc($rf['description_field'] ?? 'Description') ?></label>
                    <textarea
                        id="description"
                        name="description"
                        rows="3"
                        placeholder="<?= esc($rf['description_placeholder'] ?? '') ?>"
                        class="w-full border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-slate-600"
                    ><?= esc($role->description ?? '') ?></textarea>
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3 border-t border-slate-200 pt-6">
                <button type="submit" class="border border-slate-900 bg-slate-900 px-5 py-2 text-sm font-medium text-white transition hover:bg-slate-700">
                    <?= $isEdit ? esc($rf['update_button'] ?? 'Update Role') : esc($rf['create_button'] ?? 'Create Role') ?>
                </button>
                <a href="<?= site_url('desk/roles') ?>" class="border border-slate-300 px-5 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50"><?= esc($rf['cancel'] ?? 'Cancel') ?></a>
            </div>
        </form>
    </div>
</div>
