<?php

/**
 * @var \Volt\Core\Auth\Entities\UserEntity|null $user
 * @var array<int, \Volt\Core\Role\Entities\RoleEntity> $allRoles
 * @var array<string, string> $errors
 */
$isEdit = $user !== null;

$currentRoles = [];
if ($isEdit) {
    $raw = $user->roles;
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $currentRoles = is_array($decoded) ? $decoded : [];
    } elseif (is_array($raw)) {
        $currentRoles = $raw;
    }
}

$lang = \Volt\Core\Config\Lang\LangService::load();
$uf = $lang['user_form'] ?? [];
$c = $lang['common'] ?? [];
?><div>
    <div class="mb-6">
        <a href="<?= site_url('desk/users') ?>" class="inline-flex items-center gap-1 text-sm text-slate-600 transition hover:text-slate-900">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <?= esc($uf['back'] ?? 'User list') ?>
        </a>
    </div>

    <div class="rounded border border-slate-300 bg-white">
        <div class="border-b border-slate-300 bg-slate-100 px-5 py-4">
            <h1 class="text-lg font-bold text-slate-900"><?= $isEdit ? esc($uf['edit_title'] ?? 'Edit user') : esc($uf['new_title'] ?? 'New user') ?></h1>
            <div class="mt-0.5 text-sm text-slate-600"><?= $isEdit ? esc($uf['edit_desc'] ?? '') : esc($uf['new_desc'] ?? '') ?></div>
        </div>

        <?php if ($errors !== []): ?>
            <div class="m-5 rounded border border-red-300 bg-red-50 px-4 py-3">
                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div class="min-w-0">
                        <p class="font-bold text-red-700"><?= esc($uf['errors_title'] ?? 'Please fix the following errors:') ?></p>
                        <ul class="mt-1.5 list-disc space-y-1 pl-5 text-sm text-red-600">
                            <?php foreach ($errors as $error): ?>
                                <li><?= esc($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <form action="<?= $isEdit ? site_url("desk/users/update/{$user->name}") : site_url('desk/users/store') ?>" method="post" class="px-5 py-5">
            <?= csrf_field() ?>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-800" for="name"><?= esc($uf['username_label'] ?? 'Username') ?></label>
                    <input
                        id="name"
                        name="name"
                        type="text"
                        required
                        maxlength="100"
                        value="<?= esc($user->name ?? '') ?>"
                        placeholder="john_doe"
                        class="w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-slate-500 <?= $isEdit ? 'bg-slate-100 text-slate-600' : '' ?>"
                        <?= $isEdit ? 'readonly' : '' ?>
                    >
                    <?php if ($isEdit): ?>
                        <div class="mt-1 text-xs text-slate-500"><?= esc($uf['username_readonly'] ?? '') ?></div>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-800" for="password">
                        <?= esc($uf['password_label'] ?? 'Password') ?>
                        <?php if ($isEdit): ?>
                            <span class="font-normal text-slate-500"><?= esc($uf['password_optional'] ?? '') ?></span>
                        <?php endif; ?>
                    </label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        maxlength="255"
                        <?= $isEdit ? '' : 'required' ?>
                        placeholder="<?= $isEdit ? esc($uf['password_keep_placeholder'] ?? '') : esc($uf['password_new_placeholder'] ?? '') ?>"
                        class="w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-slate-500"
                    >
                </div>
            </div>

            <div class="mt-6">
                <span class="mb-2 block text-sm font-semibold text-slate-800"><?= esc($uf['roles_label'] ?? 'Roles') ?></span>
                <div class="grid gap-1.5 sm:grid-cols-2">
                    <?php foreach ($allRoles as $role): ?>
                        <label class="flex cursor-pointer items-center gap-2.5 rounded border border-slate-300 bg-white px-3 py-2 text-sm transition hover:bg-slate-50">
                            <input
                                type="checkbox"
                                name="roles[]"
                                value="<?= esc($role->name) ?>"
                                <?= in_array($role->name, $currentRoles, true) ? 'checked' : '' ?>
                                class="h-4 w-4 rounded border-slate-400 text-slate-800 outline-none transition"
                            >
                            <div>
                                <span class="font-medium text-slate-800"><?= esc($role->label) ?></span>
                                <?php if ($role->description): ?>
                                    <div class="text-xs text-slate-500"><?= esc($role->description) ?></div>
                                <?php endif; ?>
                            </div>
                        </label>
                    <?php endforeach; ?>
                    <?php if ($allRoles === []): ?>
                        <p class="col-span-full text-sm text-slate-500"><?= esc($uf['roles_empty'] ?? 'No roles yet.') ?> <a href="<?= site_url('desk/roles/create') ?>" class="font-semibold underline hover:text-slate-900"><?= esc($uf['roles_create'] ?? 'Create role') ?></a>.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-5">
                <label class="flex cursor-pointer items-center gap-2.5 rounded border border-slate-300 bg-white px-3 py-2 text-sm transition hover:bg-slate-50">
                    <input
                        id="is_active"
                        type="checkbox"
                        name="is_active"
                        value="1"
                        <?= ($isEdit ? ($user->is_active ? true : false) : true) ? 'checked' : '' ?>
                        class="h-4 w-4 rounded border-slate-400 text-slate-800 outline-none transition"
                    >
                    <div>
                        <span class="font-medium text-slate-800"><?= esc($uf['active_label'] ?? 'Activate account') ?></span>
                        <div class="text-xs text-slate-500"><?= esc($uf['active_desc'] ?? '') ?></div>
                    </div>
                </label>
            </div>

            <div class="mt-8 flex flex-col-reverse items-center gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
                <a href="<?= site_url('desk/users') ?>" class="inline-flex w-full items-center justify-center rounded border border-slate-300 bg-white px-5 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none sm:w-auto"><?= esc($uf['cancel'] ?? 'Cancel') ?></a>
                <button type="submit" class="inline-flex w-full items-center justify-center rounded border border-slate-900 bg-slate-900 px-5 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none sm:w-auto">
                    <?= $isEdit ? esc($uf['save_changes'] ?? 'Save changes') : esc($uf['create_user'] ?? 'Create user') ?>
                </button>
            </div>
        </form>
    </div>
</div>
