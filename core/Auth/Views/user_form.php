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
    <div style="margin-bottom:var(--claro-space-m)">
        <a href="<?= site_url('desk/users') ?>" class="claro-button claro-button--link" style="gap:var(--claro-space-xs)">
            &larr; <?= esc($uf['back'] ?? 'User list') ?>
        </a>
    </div>

    <div class="claro-card">
        <div class="claro-card__content">
            <div class="claro-page-header" style="margin-bottom:var(--claro-space-l)">
                <h1 class="claro-page-header__title"><?= $isEdit ? esc($uf['edit_title'] ?? 'Edit user') : esc($uf['new_title'] ?? 'New user') ?></h1>
                <p class="claro-page-header__subtitle"><?= $isEdit ? esc($uf['edit_desc'] ?? '') : esc($uf['new_desc'] ?? '') ?></p>
            </div>

            <?php if ($errors !== []): ?>
                <div class="claro-message claro-message--error" style="margin-bottom:var(--claro-space-l)">
                    <div class="claro-message__content">
                        <p class="claro-message__title"><?= esc($uf['errors_title'] ?? 'Please fix the following errors:') ?></p>
                        <ul style="margin:var(--claro-space-xs) 0 0;padding-left:var(--claro-space-m)">
                            <?php foreach ($errors as $error): ?>
                                <li><?= esc($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <form action="<?= $isEdit ? site_url("desk/users/update/{$user->name}") : site_url('desk/users/store') ?>" method="post">
                <?= csrf_field() ?>

                <div style="display:grid;gap:var(--claro-space-l);grid-template-columns:repeat(auto-fit,minmax(14rem,1fr))">
                    <div class="claro-form-item" style="margin-bottom:0">
                        <label class="claro-form-item__label" for="name"><?= esc($uf['username_label'] ?? 'Username') ?></label>
                        <input id="name" name="name" type="text" required maxlength="100" value="<?= esc($user->name ?? '') ?>" placeholder="john_doe" class="claro-input <?= $isEdit ? 'claro-input' : '' ?>" <?= $isEdit ? 'readonly' : '' ?> style="<?= $isEdit ? 'background:var(--claro-gray-050);color:var(--claro-gray-600)' : '' ?>">
                        <?php if ($isEdit): ?>
                            <div class="claro-form-item__description"><?= esc($uf['username_readonly'] ?? '') ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="claro-form-item" style="margin-bottom:0">
                        <label class="claro-form-item__label" for="password">
                            <?= esc($uf['password_label'] ?? 'Password') ?>
                            <?php if ($isEdit): ?>
                                <span style="font-weight:400;color:var(--claro-color-text-light)"><?= esc($uf['password_optional'] ?? '') ?></span>
                            <?php endif; ?>
                        </label>
                        <input id="password" name="password" type="password" maxlength="255" <?= $isEdit ? '' : 'required' ?> placeholder="<?= $isEdit ? esc($uf['password_keep_placeholder'] ?? '') : esc($uf['password_new_placeholder'] ?? '') ?>" class="claro-input">
                    </div>
                </div>

                <div class="claro-form-item">
                    <span class="claro-form-item__label"><?= esc($uf['roles_label'] ?? 'Roles') ?></span>
                    <div style="display:grid;gap:var(--claro-space-xs);grid-template-columns:repeat(auto-fill,minmax(14rem,1fr));margin-top:var(--claro-space-xs)">
                        <?php foreach ($allRoles as $role): ?>
                            <label class="claro-checkbox" style="border:1px solid var(--claro-gray-200);border-radius:var(--claro-border-radius);padding:var(--claro-space-s) var(--claro-space-m);transition:var(--claro-transition);cursor:pointer;align-items:flex-start">
                                <input type="checkbox" name="roles[]" value="<?= esc($role->name) ?>" <?= in_array($role->name, $currentRoles, true) ? 'checked' : '' ?> style="margin-top:2px">
                                <div>
                                    <span style="font-weight:500"><?= esc($role->label) ?></span>
                                    <?php if ($role->description): ?>
                                        <div class="claro-form-item__description"><?= esc($role->description) ?></div>
                                    <?php endif; ?>
                                </div>
                            </label>
                        <?php endforeach; ?>
                        <?php if ($allRoles === []): ?>
                            <p style="font-size:var(--claro-font-size-s);color:var(--claro-color-text-light)"><?= esc($uf['roles_empty'] ?? 'No roles yet.') ?> <a href="<?= site_url('desk/roles/create') ?>" style="font-weight:700"><?= esc($uf['roles_create'] ?? 'Create role') ?></a>.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="claro-form-item">
                    <label class="claro-checkbox" style="border:1px solid var(--claro-gray-200);border-radius:var(--claro-border-radius);padding:var(--claro-space-s) var(--claro-space-m);cursor:pointer">
                        <input id="is_active" type="checkbox" name="is_active" value="1" <?= ($isEdit ? ($user->is_active ? true : false) : true) ? 'checked' : '' ?>>
                        <div>
                            <span style="font-weight:500"><?= esc($uf['active_label'] ?? 'Activate account') ?></span>
                            <div class="claro-form-item__description"><?= esc($uf['active_desc'] ?? '') ?></div>
                        </div>
                    </label>
                </div>

                <div class="claro-form-actions" style="justify-content:flex-end;border-top:1px solid var(--claro-gray-100);padding-top:var(--claro-space-l);margin-top:var(--claro-space-m)">
                    <a href="<?= site_url('desk/users') ?>" class="claro-button"><?= esc($uf['cancel'] ?? 'Cancel') ?></a>
                    <button type="submit" class="claro-button claro-button--primary">
                        <?= $isEdit ? esc($uf['save_changes'] ?? 'Save changes') : esc($uf['create_user'] ?? 'Create user') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
