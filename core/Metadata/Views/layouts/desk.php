<?php

/**
 * Shared Volt Desk layout.
 *
 * @var string      $pageTitle
 * @var string      $currentUserName
 * @var bool        $isAdmin
 * @var string      $deskActive
 * @var string      $content
 * @var string|null $extraStyles
 * @var string|null $extraScripts
 */
$currentUserName = $currentUserName ?? '';
$isAdmin = $isAdmin ?? false;
$deskActive = $deskActive ?? 'desk';
$extraStyles = $extraStyles ?? '';
$extraScripts = $extraScripts ?? '';
?><!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/vendor/tailwindcss/tailwind.min.css') ?>">
    <script defer src="<?= base_url('assets/vendor/alpinejs/alpine.min.js') ?>"></script>
    <style>[x-cloak]{display:none!important}<?= $extraStyles ?></style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <?= view('Volt\\Core\\Metadata\\Views\\partials\\desk_topbar', compact('currentUserName', 'isAdmin', 'deskActive')) ?>

    <main class="mx-auto max-w-5xl p-4 lg:p-8">
        <?= $content ?>
    </main>
    <?= $extraScripts ?>
</body>
</html>
