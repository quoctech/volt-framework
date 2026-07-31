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
    <link rel="stylesheet" href="<?= base_url('assets/volt/claro.css') ?>">
</head>
<body class="claro-body">
    <main class="claro-page" style="display:flex;align-items:center;min-height:100vh;max-width:28rem">
        <div class="claro-card" style="width:100%">
            <div class="claro-card__content">
                <p style="font-size:var(--claro-font-size-xs);font-weight:700;text-transform:uppercase;letter-spacing:0.2em;color:var(--claro-color-text-light);margin:0 0 var(--claro-space-xs)"><?= esc($a['brand'] ?? 'Volt Framework') ?></p>
                <h1 style="font-size:var(--claro-font-size-h3);margin:0 0 var(--claro-space-xs)">
                    <?= $isSetup ? esc($a['setup_title'] ?? 'Create first admin') : esc($a['login_title'] ?? 'Sign in') ?>
                </h1>
                <p style="font-size:var(--claro-font-size-s);color:var(--claro-color-text-light);margin:0 0 var(--claro-space-l)">
                    <?= $isSetup ? esc($a['setup_desc'] ?? '') : esc($a['login_desc'] ?? '') ?>
                </p>

                <?php if (! empty($error)): ?>
                    <div class="claro-message claro-message--error">
                        <div class="claro-message__content"><?= esc((string) $error) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (! empty($success)): ?>
                    <div class="claro-message claro-message--status">
                        <div class="claro-message__content"><?= esc((string) $success) ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($isSetup): ?>
                    <form action="<?= site_url('setup') ?>" method="post">
                        <?= csrf_field() ?>
                        <div class="claro-form-item">
                            <label class="claro-form-item__label" for="setup_name"><?= esc($a['admin_name_label'] ?? 'Admin name') ?></label>
                            <input id="setup_name" name="name" type="text" autocomplete="username" required minlength="3" class="claro-input" placeholder="admin">
                        </div>
                        <div class="claro-form-item">
                            <label class="claro-form-item__label" for="setup_password"><?= esc($a['password_label'] ?? 'Password') ?></label>
                            <input id="setup_password" name="password" type="password" autocomplete="new-password" required minlength="8" class="claro-input" placeholder="<?= esc($a['password_min_hint'] ?? 'At least 8 characters') ?>">
                        </div>
                        <div class="claro-form-item">
                            <label class="claro-form-item__label" for="setup_password_confirmation"><?= esc($a['confirm_password_label'] ?? 'Confirm password') ?></label>
                            <input id="setup_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="8" class="claro-input" placeholder="<?= esc($a['retype_password_placeholder'] ?? 'Re-type password') ?>">
                        </div>
                        <button type="submit" class="claro-button claro-button--primary" style="width:100%"><?= esc($a['setup_button'] ?? 'Create admin') ?></button>
                    </form>
                <?php else: ?>
                    <form action="<?= site_url('login') ?>" method="post">
                        <?= csrf_field() ?>
                        <div class="claro-form-item">
                            <label class="claro-form-item__label" for="login_name"><?= esc($a['username_label'] ?? 'Username') ?></label>
                            <input id="login_name" name="name" type="text" autocomplete="username" required class="claro-input" placeholder="admin">
                        </div>
                        <div class="claro-form-item">
                            <label class="claro-form-item__label" for="login_password"><?= esc($a['password_label'] ?? 'Password') ?></label>
                            <input id="login_password" name="password" type="password" autocomplete="current-password" required class="claro-input" placeholder="••••••••">
                        </div>
                        <button type="submit" class="claro-button claro-button--primary" style="width:100%"><?= esc($a['login_button'] ?? 'Sign in') ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
