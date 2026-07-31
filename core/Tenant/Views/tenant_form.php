<?php

/**
 * @var array|null $tenant
 * @var array<string, string> $errors
 */
$isEdit = $tenant !== null;
$lang = \Volt\Core\Config\Lang\LangService::load();
$tf = $lang['tenant_form'] ?? [];
$c = $lang['common'] ?? [];
?><div>
    <div style="margin-bottom:var(--claro-space-m)">
        <a href="<?= site_url('desk/tenants') ?>" class="claro-button claro-button--link" style="gap:var(--claro-space-xs)">
            &larr; <?= esc($tf['back'] ?? 'Back to Tenant List') ?>
        </a>
    </div>

    <div class="claro-page-header">
        <h1 class="claro-page-header__title"><?= $isEdit ? esc($tf['edit_title'] ?? 'Edit Tenant') : esc($tf['new_title'] ?? 'New Tenant') ?></h1>
        <p class="claro-page-header__subtitle"><?= $isEdit ? esc($tf['edit_desc'] ?? '') : esc($tf['new_desc'] ?? '') ?></p>
    </div>

    <?php if ($errors !== []): ?>
        <div class="claro-message claro-message--error">
            <div class="claro-message__content">
                <p class="claro-message__title"><?= esc($tf['errors_title'] ?? 'Please fix the following errors:') ?></p>
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
            <form action="<?= $isEdit ? site_url("desk/tenants/update/{$tenant['name']}") : site_url('desk/tenants/store') ?>" method="post">
                <?= csrf_field() ?>

                <div class="claro-form-item">
                    <label class="claro-form-item__label" for="label"><?= esc($tf['label_field'] ?? 'Label') ?></label>
                    <input id="label" name="label" type="text" maxlength="255" value="<?= esc($tenant['label'] ?? '') ?>" placeholder="My Company" class="claro-input">
                </div>

                <div class="claro-form-item">
                    <label class="claro-form-item__label" for="name"><?= esc($tf['name_field'] ?? 'Name') ?></label>
                    <input id="name" name="name" type="text" required maxlength="100" value="<?= esc($tenant['name'] ?? '') ?>" placeholder="my_company" class="claro-input <?= $isEdit ? '' : '' ?>" <?= $isEdit ? 'readonly' : '' ?> pattern="[a-z0-9_]+" style="<?= $isEdit ? 'background:var(--claro-gray-050);color:var(--claro-gray-600)' : '' ?>">
                    <?php if (! $isEdit): ?>
                        <div class="claro-form-item__description"><?= esc($tf['name_hint'] ?? 'Lowercase letters, numbers, underscores') ?></div>
                    <?php endif; ?>
                </div>

                <div class="claro-form-item">
                    <label class="claro-form-item__label" for="domain"><?= esc($tf['domain_field'] ?? 'Domain') ?></label>
                    <input id="domain" name="domain" type="text" maxlength="255" value="<?= esc($tenant['domain'] ?? '') ?>" placeholder="mycompany.example.com" class="claro-input">
                </div>

                <div style="display:grid;gap:var(--claro-space-l);grid-template-columns:repeat(auto-fit,minmax(14rem,1fr))">
                    <div class="claro-form-item" style="margin-bottom:0">
                        <label class="claro-form-item__label" for="db_host"><?= esc($tf['db_host_field'] ?? 'DB Host') ?></label>
                        <input id="db_host" name="db_host" type="text" maxlength="255" value="<?= esc($tenant['db_host'] ?? 'localhost') ?>" placeholder="localhost" class="claro-input">
                    </div>
                    <div class="claro-form-item" style="margin-bottom:0">
                        <label class="claro-form-item__label" for="db_port"><?= esc($tf['db_port_field'] ?? 'DB Port') ?></label>
                        <input id="db_port" name="db_port" type="number" maxlength="10" value="<?= esc((string) ($tenant['db_port'] ?? 5432)) ?>" placeholder="5432" class="claro-input">
                    </div>
                </div>

                <div class="claro-form-item">
                    <label class="claro-form-item__label" for="db_name"><?= esc($tf['db_name_field'] ?? 'Database Name') ?></label>
                    <input id="db_name" name="db_name" type="text" required maxlength="255" value="<?= esc($tenant['db_name'] ?? '') ?>" placeholder="volt_tenant_my_company" class="claro-input">
                </div>

                <div style="display:grid;gap:var(--claro-space-l);grid-template-columns:repeat(auto-fit,minmax(14rem,1fr))">
                    <div class="claro-form-item" style="margin-bottom:0">
                        <label class="claro-form-item__label" for="db_username"><?= esc($tf['db_username_field'] ?? 'DB Username') ?></label>
                        <input id="db_username" name="db_username" type="text" maxlength="255" value="<?= esc($tenant['db_username'] ?? 'volt_admin') ?>" placeholder="volt_admin" class="claro-input">
                    </div>
                    <div class="claro-form-item" style="margin-bottom:0">
                        <label class="claro-form-item__label" for="db_password"><?= esc($tf['db_password_field'] ?? 'DB Password') ?></label>
                        <input id="db_password" name="db_password" type="password" maxlength="255" value="<?= esc($tenant['db_password'] ?? '') ?>" class="claro-input">
                    </div>
                </div>

                <div class="claro-form-item" style="margin-bottom:0">
                    <label class="claro-checkbox" for="is_active">
                        <input id="is_active" name="is_active" type="checkbox" value="1" <?= ((int) ($tenant['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>
                        <?= esc($tf['is_active'] ?? 'Active') ?>
                    </label>
                </div>

                <div class="claro-form-actions" style="border-top:1px solid var(--claro-gray-100);padding-top:var(--claro-space-l);margin-top:var(--claro-space-l)">
                    <button type="submit" class="claro-button claro-button--primary">
                        <?= $isEdit ? esc($tf['update_button'] ?? 'Update Tenant') : esc($tf['create_button'] ?? 'Create Tenant') ?>
                    </button>
                    <a href="<?= site_url('desk/tenants') ?>" class="claro-button"><?= esc($tf['cancel'] ?? 'Cancel') ?></a>
                </div>
            </form>
        </div>
    </div>
</div>
