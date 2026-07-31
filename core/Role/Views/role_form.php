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
    <div style="margin-bottom:var(--claro-space-m)">
        <a href="<?= site_url('desk/roles') ?>" class="claro-button claro-button--link" style="gap:var(--claro-space-xs)">
            &larr; <?= esc($rf['back'] ?? 'Back to Role List') ?>
        </a>
    </div>

    <div class="claro-page-header">
        <h1 class="claro-page-header__title"><?= $isEdit ? esc($rf['edit_title'] ?? 'Edit Role') : esc($rf['new_title'] ?? 'New Role') ?></h1>
        <p class="claro-page-header__subtitle"><?= $isEdit ? esc($rf['edit_desc'] ?? '') : esc($rf['new_desc'] ?? '') ?></p>
    </div>

    <?php if ($errors !== []): ?>
        <div class="claro-message claro-message--error">
            <div class="claro-message__content">
                <p class="claro-message__title"><?= esc($rf['errors_title'] ?? 'Please fix the following errors:') ?></p>
                <ul style="margin:var(--claro-space-xs) 0 0;padding-left:var(--claro-space-m)">
                    <?php foreach ($errors as $error): ?>
                        <li><?= esc($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <div class="claro-card">
        <div class="claro-card__content">
            <form action="<?= $isEdit ? site_url("desk/roles/update/{$role->name}") : site_url('desk/roles/store') ?>" method="post">
                <?= csrf_field() ?>

                <div class="claro-form-item">
                    <label class="claro-form-item__label" for="label"><?= esc($rf['label_field'] ?? 'Label') ?></label>
                    <input id="label" name="label" type="text" required maxlength="255" value="<?= esc($role->label ?? '') ?>" placeholder="HR Manager" class="claro-input">
                </div>

                <div class="claro-form-item">
                    <label class="claro-form-item__label" for="name"><?= esc($rf['name_field'] ?? 'Name') ?></label>
                    <input id="name" name="name" type="text" required maxlength="100" value="<?= esc($role->name ?? '') ?>" placeholder="hr_manager" class="claro-input" <?= $isEdit ? 'readonly' : '' ?> pattern="[a-z0-9_]+" style="<?= $isEdit ? 'background:var(--claro-gray-050);color:var(--claro-gray-600)' : '' ?>">
                    <?php if (! $isEdit): ?>
                        <div class="claro-form-item__description"><?= esc($rf['name_hint'] ?? '') ?></div>
                    <?php endif; ?>
                </div>

                <div class="claro-form-item">
                    <label class="claro-form-item__label" for="description"><?= esc($rf['description_field'] ?? 'Description') ?></label>
                    <textarea id="description" name="description" rows="3" placeholder="<?= esc($rf['description_placeholder'] ?? '') ?>" class="claro-textarea"><?= esc($role->description ?? '') ?></textarea>
                </div>

                <div class="claro-form-actions" style="border-top:1px solid var(--claro-gray-100);padding-top:var(--claro-space-l);margin-top:var(--claro-space-m)">
                    <button type="submit" class="claro-button claro-button--primary">
                        <?= $isEdit ? esc($rf['update_button'] ?? 'Update Role') : esc($rf['create_button'] ?? 'Create Role') ?>
                    </button>
                    <a href="<?= site_url('desk/roles') ?>" class="claro-button"><?= esc($rf['cancel'] ?? 'Cancel') ?></a>
                </div>
            </form>
        </div>
    </div>
</div>
