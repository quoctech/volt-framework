<?php

/** @var int $moduleCount */
/** @var int $entityCount */
/** @var bool $isAdmin */
/** @var string $currentUserName */
$isAdmin = $isAdmin ?? false;
$currentUserName = $currentUserName ?? '';
$deskActive = 'desk';
$currentActor = service('voltAuth')->currentUser();
$permissionResolver = service('voltPermissionResolver');
$canViewErrorLogs = $currentActor !== null && ($currentActor->isAdmin() || $permissionResolver->can('error_logs', 'read', null, null, $currentActor));

$lang = \Volt\Core\Config\Lang\LangService::load();
$d = $lang['desk'] ?? [];
$common = $lang['common'] ?? [];
?>
<!doctype html>
<html lang="<?= esc($lang['code'] ?? 'en') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Volt Desk</title>
    <link rel="stylesheet" href="<?= base_url('assets/vendor/tailwindcss/tailwind.min.css') ?>">
    <script defer src="<?= base_url('assets/vendor/alpinejs/alpine.min.js') ?>"></script>
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <?= view('Volt\\Core\\Metadata\\Views\\partials\\desk_topbar', compact('currentUserName', 'isAdmin', 'deskActive')) ?>

    <main class="mx-auto max-w-5xl p-4 lg:p-8">
        <div class="mb-6">
            <h1 class="text-2xl font-semibold"><?= esc($d['title'] ?? 'Desk') ?></h1>
            <p class="mt-1 text-sm text-slate-500"><?= esc($d['subtitle'] ?? '') ?></p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 <?= ($isAdmin || $canViewErrorLogs) ? 'lg:grid-cols-4' : '' ?>">
            <a href="<?= site_url('desk/entities') ?>" class="border border-slate-300 bg-white p-5 transition hover:border-slate-500">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500"><?= esc($d['browse'] ?? 'Browse') ?></p>
                <h2 class="mt-2 text-xl font-semibold"><?= esc($d['entity_list'] ?? 'Entity List') ?></h2>
                <p class="mt-2 text-sm text-slate-600"><?= $d['entity_desc'] ?? '' ?></p>
                <p class="mt-4 text-sm text-slate-500"><?= str_replace('{count}', (string) $entityCount, $d['entity_count'] ?? '') ?></p>
            </a>

            <?php if ($isAdmin): ?>
                <a href="<?= site_url('desk/users') ?>" class="border border-slate-300 bg-white p-5 transition hover:border-slate-500">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500"><?= esc($d['admin'] ?? 'Admin') ?></p>
                    <h2 class="mt-2 text-xl font-semibold"><?= esc($d['users'] ?? 'User List') ?></h2>
                    <p class="mt-2 text-sm text-slate-600"><?= $d['users_desc'] ?? '' ?></p>
                    <p class="mt-4 text-sm text-slate-500"><?= $d['users_hint'] ?? '' ?></p>
                </a>

                <a href="<?= site_url('desk/roles') ?>" class="border border-slate-300 bg-white p-5 transition hover:border-slate-500">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500"><?= esc($d['admin'] ?? 'Admin') ?></p>
                    <h2 class="mt-2 text-xl font-semibold"><?= esc($d['roles'] ?? 'Role List') ?></h2>
                    <p class="mt-2 text-sm text-slate-600"><?= $d['roles_desc'] ?? '' ?></p>
                    <p class="mt-4 text-sm text-slate-500"><?= $d['roles_hint'] ?? '' ?></p>
                </a>

                <a href="<?= site_url('desk/system-status') ?>" class="border border-slate-300 bg-white p-5 transition hover:border-slate-500">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500"><?= esc($d['admin'] ?? 'Admin') ?></p>
                    <h2 class="mt-2 text-xl font-semibold"><?= esc($d['system_status'] ?? 'System Status') ?></h2>
                    <p class="mt-2 text-sm text-slate-600"><?= $d['system_status_desc'] ?? '' ?></p>
                    <p class="mt-4 text-sm text-slate-500"><?= $d['system_status_hint'] ?? '' ?></p>
                </a>

                <a href="<?= site_url('desk/system-settings') ?>" class="border border-slate-300 bg-white p-5 transition hover:border-slate-500">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500"><?= esc($d['admin'] ?? 'Admin') ?></p>
                    <h2 class="mt-2 text-xl font-semibold"><?= esc($lang['nav']['system_settings'] ?? 'System Settings') ?></h2>
                    <p class="mt-2 text-sm text-slate-600"><?= $lang['system']['description'] ?? '' ?></p>
                    <p class="mt-4 text-sm text-slate-500">Language / Timezone</p>
                </a>

                <a href="<?= site_url('desk/create-module') ?>" class="border border-slate-300 bg-white p-5 transition hover:border-slate-500">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500"><?= esc($d['admin'] ?? 'Admin') ?></p>
                    <h2 class="mt-2 text-xl font-semibold"><?= esc($d['create_module'] ?? 'Create Module') ?></h2>
                    <p class="mt-2 text-sm text-slate-600"><?= $d['create_module_desc'] ?? '' ?></p>
                    <p class="mt-4 text-sm text-slate-500"><?= str_replace('{count}', (string) $moduleCount, $d['create_module_hint'] ?? '') ?></p>
                </a>

                <a href="<?= site_url('desk/entity-builder') ?>" class="border border-slate-300 bg-white p-5 transition hover:border-slate-500">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500"><?= esc($d['admin'] ?? 'Admin') ?></p>
                    <h2 class="mt-2 text-xl font-semibold"><?= esc($d['entity_builder'] ?? 'Entity Builder') ?></h2>
                    <p class="mt-2 text-sm text-slate-600"><?= $d['entity_builder_desc'] ?? '' ?></p>
                    <p class="mt-4 text-sm text-slate-500"><?= $d['entity_builder_hint'] ?? '' ?></p>
                </a>
            <?php else: ?>
                <div class="border border-amber-300 bg-amber-50 p-5 text-sm text-amber-900 sm:col-span-1">
                    <p class="font-semibold"><?= esc($d['restricted'] ?? 'Restricted Access') ?></p>
                    <p class="mt-2"><?= $d['restricted_desc'] ?? '' ?></p>
                </div>
            <?php endif; ?>

            <?php if ($canViewErrorLogs): ?>
                <a href="<?= site_url('desk/error-logs') ?>" class="border border-slate-300 bg-white p-5 transition hover:border-slate-500">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500"><?= esc($d['system'] ?? 'System') ?></p>
                    <h2 class="mt-2 text-xl font-semibold"><?= esc($d['error_logs'] ?? 'Error Logs') ?></h2>
                    <p class="mt-2 text-sm text-slate-600"><?= $d['error_logs_desc'] ?? '' ?></p>
                    <p class="mt-4 text-sm text-slate-500"><?= $d['error_logs_hint'] ?? '' ?></p>
                </a>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
