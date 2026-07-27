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
    <div class="mb-6">
        <a href="<?= site_url('desk/tenants') ?>" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            <?= esc($tf['back'] ?? 'Back to Tenant List') ?>
        </a>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900"><?= $isEdit ? esc($tf['edit_title'] ?? 'Edit Tenant') : esc($tf['new_title'] ?? 'New Tenant') ?></h1>
        <p class="mt-1 text-sm text-slate-500"><?= $isEdit ? esc($tf['edit_desc'] ?? '') : esc($tf['new_desc'] ?? '') ?></p>
    </div>

    <?php if ($errors !== []): ?>
        <div class="mb-4 border border-red-300 bg-red-50 p-4 text-sm text-red-800">
            <p class="mb-1 font-medium"><?= esc($tf['errors_title'] ?? 'Please fix the following errors:') ?></p>
            <ul class="ml-4 list-disc space-y-1">
                <?php foreach ($errors as $error): ?>
                    <li><?= esc($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="border border-slate-300 bg-white p-6">
        <form action="<?= $isEdit ? site_url("desk/tenants/update/{$tenant['name']}") : site_url('desk/tenants/store') ?>" method="post">
            <?= csrf_field() ?>

            <div class="grid gap-5">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500" for="label"><?= esc($tf['label_field'] ?? 'Label') ?></label>
                    <input
                        id="label"
                        name="label"
                        type="text"
                        maxlength="255"
                        value="<?= esc($tenant['label'] ?? '') ?>"
                        placeholder="My Company"
                        class="w-full border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-slate-600"
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500" for="name"><?= esc($tf['name_field'] ?? 'Name') ?></label>
                    <input
                        id="name"
                        name="name"
                        type="text"
                        required
                        maxlength="100"
                        value="<?= esc($tenant['name'] ?? '') ?>"
                        placeholder="my_company"
                        class="w-full border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-slate-600 <?= $isEdit ? 'bg-slate-100 text-slate-500' : '' ?>"
                        <?= $isEdit ? 'readonly' : '' ?>
                        pattern="[a-z0-9_]+"
                    >
                    <?php if (! $isEdit): ?>
                        <p class="mt-1 text-xs text-slate-500"><?= esc($tf['name_hint'] ?? 'Lowercase letters, numbers, underscores') ?></p>
                    <?php endif; ?>
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500" for="domain"><?= esc($tf['domain_field'] ?? 'Domain') ?></label>
                    <input
                        id="domain"
                        name="domain"
                        type="text"
                        maxlength="255"
                        value="<?= esc($tenant['domain'] ?? '') ?>"
                        placeholder="mycompany.example.com"
                        class="w-full border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-slate-600"
                    >
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500" for="db_host"><?= esc($tf['db_host_field'] ?? 'DB Host') ?></label>
                        <input
                            id="db_host"
                            name="db_host"
                            type="text"
                            maxlength="255"
                            value="<?= esc($tenant['db_host'] ?? 'localhost') ?>"
                            placeholder="localhost"
                            class="w-full border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-slate-600"
                        >
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500" for="db_port"><?= esc($tf['db_port_field'] ?? 'DB Port') ?></label>
                        <input
                            id="db_port"
                            name="db_port"
                            type="number"
                            maxlength="10"
                            value="<?= esc((string) ($tenant['db_port'] ?? 5432)) ?>"
                            placeholder="5432"
                            class="w-full border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-slate-600"
                        >
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500" for="db_name"><?= esc($tf['db_name_field'] ?? 'Database Name') ?></label>
                    <input
                        id="db_name"
                        name="db_name"
                        type="text"
                        required
                        maxlength="255"
                        value="<?= esc($tenant['db_name'] ?? '') ?>"
                        placeholder="volt_tenant_my_company"
                        class="w-full border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-slate-600"
                    >
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500" for="db_username"><?= esc($tf['db_username_field'] ?? 'DB Username') ?></label>
                        <input
                            id="db_username"
                            name="db_username"
                            type="text"
                            maxlength="255"
                            value="<?= esc($tenant['db_username'] ?? 'volt_admin') ?>"
                            placeholder="volt_admin"
                            class="w-full border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-slate-600"
                        >
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500" for="db_password"><?= esc($tf['db_password_field'] ?? 'DB Password') ?></label>
                        <input
                            id="db_password"
                            name="db_password"
                            type="password"
                            maxlength="255"
                            value="<?= esc($tenant['db_password'] ?? '') ?>"
                            class="w-full border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-slate-600"
                        >
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.12em] text-slate-500" for="is_active">
                        <input
                            id="is_active"
                            name="is_active"
                            type="checkbox"
                            value="1"
                            class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500"
                            <?= ((int) ($tenant['is_active'] ?? 1) === 1) ? 'checked' : '' ?>
                        >
                        <?= esc($tf['is_active'] ?? 'Active') ?>
                    </label>
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3 border-t border-slate-200 pt-6">
                <button type="submit" class="border border-slate-900 bg-slate-900 px-5 py-2 text-sm font-medium text-white transition hover:bg-slate-700">
                    <?= $isEdit ? esc($tf['update_button'] ?? 'Update Tenant') : esc($tf['create_button'] ?? 'Create Tenant') ?>
                </button>
                <a href="<?= site_url('desk/tenants') ?>" class="border border-slate-300 px-5 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50"><?= esc($tf['cancel'] ?? 'Cancel') ?></a>
            </div>
        </form>
    </div>
</div>
