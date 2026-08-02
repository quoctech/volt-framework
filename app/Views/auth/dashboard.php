<?php

/** @var \Volt\Core\Auth\Entities\UserEntity $user */

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

$lang = \Volt\Core\Config\Lang\LangService::load();
$d = $lang['dashboard'] ?? [];
$c = $lang['common'] ?? [];
$htmlLang = $lang['code'] ?? 'en';
?>
<!doctype html>
<html lang="<?= esc($htmlLang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($d['title'] ?? 'Volt Core Dashboard') ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/vendor/tailwindcss/tailwind.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/volt/claro.css') ?>">
    <script defer src="<?= base_url('assets/vendor/alpinejs/alpine.min.js') ?>"></script>
</head>
<body class="claro-body">
<main class="claro-page" style="display:flex;align-items:center;min-height:100vh;max-width:48rem">
    <div class="claro-card" style="width:100%">
        <div class="claro-card__content">
            <p style="font-size:var(--claro-font-size-xs);font-weight:700;text-transform:uppercase;letter-spacing:0.2em;color:var(--claro-color-text-light);margin:0 0 var(--claro-space-xs)"><?= esc($d['brand'] ?? 'Volt Core') ?></p>
            <h1 style="font-size:var(--claro-font-size-h3);margin:0 0 var(--claro-space-xs)"><?= esc($d['logged_in'] ?? 'You are logged in') ?></h1>
            <p style="font-size:var(--claro-font-size-s);color:var(--claro-color-text-light);margin:0 0 var(--claro-space-l)"><?= str_replace('{name}', esc($user->name), $d['greeting'] ?? 'Hello {name}.') ?></p>

            <div style="display:grid;gap:var(--claro-space-m);grid-template-columns:repeat(auto-fit,minmax(14rem,1fr))">
                <div class="claro-card" style="margin:0">
                    <div class="claro-card__content">
                        <p style="font-size:var(--claro-font-size-s);color:var(--claro-color-text-light);margin:0 0 var(--claro-space-xs)"><?= esc($d['roles'] ?? 'Roles') ?></p>
                        <p style="font-weight:600;margin:0"><?= esc(implode(', ', array_map('strval', $roles))) ?></p>
                    </div>
                </div>
                <div class="claro-card" style="margin:0">
                    <div class="claro-card__content">
                        <p style="font-size:var(--claro-font-size-s);color:var(--claro-color-text-light);margin:0 0 var(--claro-space-xs)"><?= esc($d['status'] ?? 'Status') ?></p>
                        <p style="font-weight:600;margin:0"><?= $user->isActive() ? esc($c['active'] ?? 'Active') : esc($c['inactive'] ?? 'Inactive') ?></p>
                    </div>
                </div>
            </div>

            <div style="display:grid;gap:var(--claro-space-m);grid-template-columns:repeat(auto-fit,minmax(14rem,1fr));margin-top:var(--claro-space-l)">
                <a href="<?= site_url('entities/new') ?>" class="claro-button" style="justify-content:center;text-align:center;margin:0"><?= esc($d['entity_builder'] ?? 'Entity Builder') ?></a>
                <form action="<?= site_url('logout') ?>" method="post">
                    <?= csrf_field() ?>
                    <button type="submit" class="claro-button claro-button--primary" style="width:100%;margin:0"><?= esc($d['logout'] ?? 'Logout') ?></button>
                </form>
            </div>
        </div>
    </div>
</main>
</body>
</html>
