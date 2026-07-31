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
    <link rel="stylesheet" href="<?= base_url('assets/volt/claro.css') ?>">
    <script defer src="<?= base_url('assets/volt/claro.js') ?>"></script>
    <script defer src="<?= base_url('assets/vendor/alpinejs/alpine.min.js') ?>"></script>
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="claro-body">
    <?= view('Volt\\Core\\Metadata\\Views\\partials\\desk_topbar', compact('currentUserName', 'isAdmin', 'deskActive')) ?>

    <main class="claro-page">
        <div class="claro-page-header">
            <h1 class="claro-page-header__title"><?= esc($d['title'] ?? 'Desk') ?></h1>
            <p class="claro-page-header__subtitle"><?= esc($d['subtitle'] ?? '') ?></p>
        </div>

        <div class="claro-card-grid" style="grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr))">
            <a href="<?= site_url('desk/entities') ?>" class="claro-link-card">
                <p class="claro-link-card__badge"><?= esc($d['browse'] ?? 'Browse') ?></p>
                <h2 class="claro-link-card__title"><?= esc($d['entity_list'] ?? 'Entity List') ?></h2>
                <p class="claro-link-card__desc"><?= $d['entity_desc'] ?? '' ?></p>
                <p class="claro-link-card__meta"><?= str_replace('{count}', (string) $entityCount, $d['entity_count'] ?? '') ?></p>
            </a>

            <?php if ($isAdmin): ?>
                <a href="<?= site_url('desk/users') ?>" class="claro-link-card">
                    <p class="claro-link-card__badge"><?= esc($d['admin'] ?? 'Admin') ?></p>
                    <h2 class="claro-link-card__title"><?= esc($d['users'] ?? 'User List') ?></h2>
                    <p class="claro-link-card__desc"><?= $d['users_desc'] ?? '' ?></p>
                    <p class="claro-link-card__meta"><?= $d['users_hint'] ?? '' ?></p>
                </a>

                <a href="<?= site_url('desk/roles') ?>" class="claro-link-card">
                    <p class="claro-link-card__badge"><?= esc($d['admin'] ?? 'Admin') ?></p>
                    <h2 class="claro-link-card__title"><?= esc($d['roles'] ?? 'Role List') ?></h2>
                    <p class="claro-link-card__desc"><?= $d['roles_desc'] ?? '' ?></p>
                    <p class="claro-link-card__meta"><?= $d['roles_hint'] ?? '' ?></p>
                </a>

                <a href="<?= site_url('desk/tenants') ?>" class="claro-link-card">
                    <p class="claro-link-card__badge"><?= esc($d['admin'] ?? 'Admin') ?></p>
                    <h2 class="claro-link-card__title"><?= esc($d['tenants'] ?? 'Tenants') ?></h2>
                    <p class="claro-link-card__desc"><?= $d['tenants_desc'] ?? '' ?></p>
                    <p class="claro-link-card__meta"><?= $d['tenants_hint'] ?? '' ?></p>
                </a>

                <a href="<?= site_url('desk/system-status') ?>" class="claro-link-card">
                    <p class="claro-link-card__badge"><?= esc($d['admin'] ?? 'Admin') ?></p>
                    <h2 class="claro-link-card__title"><?= esc($d['system_status'] ?? 'System Status') ?></h2>
                    <p class="claro-link-card__desc"><?= $d['system_status_desc'] ?? '' ?></p>
                    <p class="claro-link-card__meta"><?= $d['system_status_hint'] ?? '' ?></p>
                </a>

                <a href="<?= site_url('desk/system-settings') ?>" class="claro-link-card">
                    <p class="claro-link-card__badge"><?= esc($d['admin'] ?? 'Admin') ?></p>
                    <h2 class="claro-link-card__title"><?= esc($lang['nav']['system_settings'] ?? 'System Settings') ?></h2>
                    <p class="claro-link-card__desc"><?= $lang['system']['description'] ?? '' ?></p>
                    <p class="claro-link-card__meta">Language / Timezone</p>
                </a>

                <a href="<?= site_url('desk/create-module') ?>" class="claro-link-card">
                    <p class="claro-link-card__badge"><?= esc($d['admin'] ?? 'Admin') ?></p>
                    <h2 class="claro-link-card__title"><?= esc($d['create_module'] ?? 'Create Module') ?></h2>
                    <p class="claro-link-card__desc"><?= $d['create_module_desc'] ?? '' ?></p>
                    <p class="claro-link-card__meta"><?= str_replace('{count}', (string) $moduleCount, $d['create_module_hint'] ?? '') ?></p>
                </a>

                <a href="<?= site_url('desk/entity-builder') ?>" class="claro-link-card">
                    <p class="claro-link-card__badge"><?= esc($d['admin'] ?? 'Admin') ?></p>
                    <h2 class="claro-link-card__title"><?= esc($d['entity_builder'] ?? 'Entity Builder') ?></h2>
                    <p class="claro-link-card__desc"><?= $d['entity_builder_desc'] ?? '' ?></p>
                    <p class="claro-link-card__meta"><?= $d['entity_builder_hint'] ?? '' ?></p>
                </a>

                <a href="<?= site_url('desk/pages') ?>" class="claro-link-card">
                    <p class="claro-link-card__badge"><?= esc($d['admin'] ?? 'Admin') ?></p>
                    <h2 class="claro-link-card__title">Pages</h2>
                    <p class="claro-link-card__desc">Create and manage custom pages with HTML, CSS, JS.</p>
                    <p class="claro-link-card__meta">Custom routes at /pagename</p>
                </a>

                <a href="<?= site_url('desk/reports') ?>" class="claro-link-card">
                    <p class="claro-link-card__badge"><?= esc($d['admin'] ?? 'Admin') ?></p>
                    <h2 class="claro-link-card__title">Reports</h2>
                    <p class="claro-link-card__desc">Build custom reports, pivot tables, and charts.</p>
                    <p class="claro-link-card__meta">Query, Pivot &amp; SQL</p>
                </a>

            <?php else: ?>
                <div class="claro-message claro-message--warning">
                    <div class="claro-message__content">
                        <p class="claro-message__title"><?= esc($d['restricted'] ?? 'Restricted Access') ?></p>
                        <p><?= $d['restricted_desc'] ?? '' ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($canViewErrorLogs): ?>
                <a href="<?= site_url('desk/error-logs') ?>" class="claro-link-card">
                    <p class="claro-link-card__badge"><?= esc($d['system'] ?? 'System') ?></p>
                    <h2 class="claro-link-card__title"><?= esc($d['error_logs'] ?? 'Error Logs') ?></h2>
                    <p class="claro-link-card__desc"><?= $d['error_logs_desc'] ?? '' ?></p>
                    <p class="claro-link-card__meta"><?= $d['error_logs_hint'] ?? '' ?></p>
                </a>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
