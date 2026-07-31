<?php

/** @var \Volt\Core\Auth\Entities\UserEntity $user */
/** @var bool $isAdmin */
/** @var string $currentUserName */
/** @var string|null $error */
/** @var string|null $success */
$isAdmin = $isAdmin ?? false;
$currentUserName = $currentUserName ?? (string) ($user->name ?? '');
$deskActive = 'profile';

$roles = $user->roles ?? [];
if (is_string($roles)) {
    $decoded = json_decode($roles, true);
    if (is_array($decoded)) {
        $roles = $decoded;
    } else {
        $unserialized = @unserialize($roles, ['allowed_classes' => false]);
        $roles = is_array($unserialized) ? $unserialized : [];
    }
}
$roles = array_values(array_map('strval', is_array($roles) ? $roles : []));

$lang = \Volt\Core\Config\Lang\LangService::load();
$p = $lang['profile'] ?? [];
$c = $lang['common'] ?? [];
$htmlLang = $lang['code'] ?? 'en';
?>
<!doctype html>
<html lang="<?= esc($htmlLang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($p['title'] ?? 'Edit profile') ?> · Volt Desk</title>
    <link rel="stylesheet" href="<?= base_url('assets/vendor/tailwindcss/tailwind.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/volt/claro.css') ?>">
    <script defer src="<?= base_url('assets/volt/claro.js') ?>"></script>
    <script defer src="<?= base_url('assets/vendor/alpinejs/alpine.min.js') ?>"></script>
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="claro-body">
    <?= view('Volt\\Core\\Metadata\\Views\\partials\\desk_topbar', compact('currentUserName', 'isAdmin', 'deskActive')) ?>

    <main class="claro-page" style="max-width:36rem">
        <div class="claro-page-header">
            <h1 class="claro-page-header__title"><?= esc($p['edit'] ?? 'Edit profile') ?></h1>
            <p class="claro-page-header__subtitle"><?= esc($p['description'] ?? '') ?></p>
        </div>

        <?php if (! empty($error)): ?>
            <div class="claro-message claro-message--error"><?= esc((string) $error) ?></div>
        <?php endif; ?>
        <?php if (! empty($success)): ?>
            <div class="claro-message claro-message--status"><?= esc((string) $success) ?></div>
        <?php endif; ?>

        <div class="claro-card" style="margin-bottom:var(--claro-space-l)">
            <div class="claro-card__content">
                <dl style="display:grid;gap:var(--claro-space-m);font-size:var(--claro-font-size-s)">
                    <div>
                        <dt style="font-size:var(--claro-font-size-xs);font-weight:700;text-transform:uppercase;letter-spacing:0.18em;color:var(--claro-color-text-light)"><?= esc($p['username'] ?? 'Username') ?></dt>
                        <dd style="margin:var(--claro-space-xs) 0 0;font-weight:500"><?= esc((string) $user->name) ?></dd>
                    </div>
                    <div>
                        <dt style="font-size:var(--claro-font-size-xs);font-weight:700;text-transform:uppercase;letter-spacing:0.18em;color:var(--claro-color-text-light)"><?= esc($p['roles'] ?? 'Roles') ?></dt>
                        <dd style="margin:var(--claro-space-xs) 0 0"><?= esc($roles !== [] ? implode(', ', $roles) : '—') ?></dd>
                    </div>
                    <div>
                        <dt style="font-size:var(--claro-font-size-xs);font-weight:700;text-transform:uppercase;letter-spacing:0.18em;color:var(--claro-color-text-light)"><?= esc($p['status'] ?? 'Status') ?></dt>
                        <dd style="margin:var(--claro-space-xs) 0 0"><?= $user->isActive() ? esc($c['active'] ?? 'Active') : esc($c['inactive'] ?? 'Inactive') ?></dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="claro-card" style="margin-bottom:var(--claro-space-l)">
            <div class="claro-card__content">
                <h2 style="font-size:var(--claro-font-size-s);font-weight:700;margin:0 0 var(--claro-space-xs)"><?= esc($p['api_key'] ?? 'API Key') ?></h2>
                <p style="font-size:var(--claro-font-size-xs);color:var(--claro-color-text-light);margin:0 0 var(--claro-space-m)"><?= $p['api_instruction'] ?? '' ?></p>

                <?php if (! empty($apiKey) && ! empty($newSecret)): ?>
                    <div class="claro-message claro-message--warning" style="margin-bottom:0">
                        <div class="claro-message__content">
                            <p class="claro-message__title"><?= esc($p['api_new_key_message'] ?? '') ?></p>
                            <dl style="display:grid;gap:var(--claro-space-xs);font-size:var(--claro-font-size-xs);margin:var(--claro-space-xs) 0 0">
                                <dt style="font-weight:700"><?= esc($p['api_key_label'] ?? 'API Key') ?></dt>
                                <dd style="font-family:monospace;margin:0"><?= esc($apiKey) ?></dd>
                                <dt style="font-weight:700;margin-top:var(--claro-space-xs)"><?= esc($p['api_secret_label'] ?? 'API Secret') ?></dt>
                                <dd style="font-family:monospace;margin:0"><?= esc($newSecret) ?></dd>
                            </dl>
                        </div>
                    </div>
                <?php elseif (! empty($apiKey)): ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;border:1px solid var(--claro-gray-200);border-radius:var(--claro-border-radius);padding:var(--claro-space-s) var(--claro-space-m);font-size:var(--claro-font-size-s)">
                        <span style="font-family:monospace"><?= esc($apiKey) ?></span>
                        <form method="post" action="<?= site_url('desk/profile/generate-api-key') ?>" style="display:inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="claro-button claro-button--small" onclick="return confirm('<?= esc($p['generate_confirm'] ?? '') ?>')"><?= esc($p['generate_new'] ?? 'Generate new') ?></button>
                        </form>
                    </div>
                <?php else: ?>
                    <form method="post" action="<?= site_url('desk/profile/generate-api-key') ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="claro-button claro-button--primary"><?= esc($p['generate_api_key'] ?? 'Generate API Key') ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="claro-card">
            <div class="claro-card__content">
                <h2 style="font-size:var(--claro-font-size-s);font-weight:700;margin:0 0 var(--claro-space-m)"><?= esc($p['change_password'] ?? 'Change password') ?></h2>
                <form method="post" action="<?= site_url('desk/profile') ?>">
                    <?= csrf_field() ?>
                    <div class="claro-form-item">
                        <label class="claro-form-item__label"><?= esc($p['current_password'] ?? 'Current password') ?></label>
                        <input type="password" name="current_password" required autocomplete="current-password" class="claro-input">
                    </div>
                    <div class="claro-form-item">
                        <label class="claro-form-item__label"><?= esc($p['new_password'] ?? 'New password') ?></label>
                        <input type="password" name="password" required minlength="8" autocomplete="new-password" class="claro-input">
                    </div>
                    <div class="claro-form-item">
                        <label class="claro-form-item__label"><?= esc($p['confirm_password'] ?? 'Confirm new password') ?></label>
                        <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" class="claro-input">
                    </div>
                    <div class="claro-form-actions">
                        <a href="<?= site_url('desk') ?>" class="claro-button"><?= esc($c['cancel'] ?? 'Cancel') ?></a>
                        <button type="submit" class="claro-button claro-button--primary"><?= esc($c['save'] ?? 'Save') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
