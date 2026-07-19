<?php

/** @var bool $setupRequired */
/** @var string $mode */
/** @var string|null $error */
/** @var string|null $success */

$setupRequired = (bool) ($setupRequired ?? false);
$mode = ($mode ?? '') === 'setup' || $setupRequired ? 'setup' : 'login';
$isSetup = $mode === 'setup';

$lang = \Volt\Core\Config\Lang\LangService::load();
$a = $lang['auth'] ?? [];
$c = $lang['common'] ?? [];
$htmlLang = $lang['code'] ?? 'en';
?>
<!doctype html>
<html lang="<?= esc($htmlLang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $isSetup ? esc($a['title_setup'] ?? 'Setup admin · Volt') : esc($a['title_login'] ?? 'Login · Volt') ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/vendor/tailwindcss/tailwind.min.css') ?>">
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <main class="mx-auto flex min-h-screen max-w-md items-center px-4 py-10">
        <div class="w-full border border-slate-300 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500"><?= esc($a['brand'] ?? 'Volt Framework') ?></p>
            <h1 class="mt-2 text-2xl font-semibold">
                <?= $isSetup ? esc($a['setup_title'] ?? 'Create first admin') : esc($a['login_title'] ?? 'Sign in') ?>
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                <?= $isSetup ? esc($a['setup_desc'] ?? '') : esc($a['login_desc'] ?? '') ?>
            </p>

            <?php if (! empty($error)): ?>
                <div class="mt-4 border border-rose-300 bg-rose-50 px-3 py-2 text-sm text-rose-800">
                    <?= esc((string) $error) ?>
                </div>
            <?php endif; ?>

            <?php if (! empty($success)): ?>
                <div class="mt-4 border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    <?= esc((string) $success) ?>
                </div>
            <?php endif; ?>

            <?php if ($isSetup): ?>
                <form action="<?= site_url('setup') ?>" method="post" class="mt-6 space-y-4">
                    <?= csrf_field() ?>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-slate-700"><?= esc($a['admin_name_label'] ?? 'Admin name') ?></span>
                        <input
                            id="setup_name"
                            name="name"
                            type="text"
                            autocomplete="username"
                            required
                            minlength="3"
                            class="w-full border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-slate-500"
                            placeholder="admin"
                        >
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-slate-700"><?= esc($a['password_label'] ?? 'Password') ?></span>
                        <input
                            id="setup_password"
                            name="password"
                            type="password"
                            autocomplete="new-password"
                            required
                            minlength="8"
                            class="w-full border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-slate-500"
                            placeholder="<?= esc($a['password_min_hint'] ?? 'At least 8 characters') ?>"
                        >
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-slate-700"><?= esc($a['confirm_password_label'] ?? 'Confirm password') ?></span>
                        <input
                            id="setup_password_confirmation"
                            name="password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            required
                            minlength="8"
                            class="w-full border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-slate-500"
                            placeholder="<?= esc($a['retype_password_placeholder'] ?? 'Re-type password') ?>"
                        >
                    </label>
                    <button
                        type="submit"
                        class="w-full border border-slate-900 bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800"
                    ><?= esc($a['setup_button'] ?? 'Create admin') ?></button>
                </form>
            <?php else: ?>
                <form action="<?= site_url('login') ?>" method="post" class="mt-6 space-y-4">
                    <?= csrf_field() ?>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-slate-700"><?= esc($a['username_label'] ?? 'Username') ?></span>
                        <input
                            id="login_name"
                            name="name"
                            type="text"
                            autocomplete="username"
                            required
                            class="w-full border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-slate-500"
                            placeholder="admin"
                        >
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-slate-700"><?= esc($a['password_label'] ?? 'Password') ?></span>
                        <input
                            id="login_password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            required
                            class="w-full border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-slate-500"
                            placeholder="••••••••"
                        >
                    </label>
                    <button
                        type="submit"
                        class="w-full border border-slate-900 bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800"
                    ><?= esc($a['login_button'] ?? 'Sign in') ?></button>
                </form>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
