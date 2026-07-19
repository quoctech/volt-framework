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
    <script defer src="<?= base_url('assets/vendor/alpinejs/alpine.min.js') ?>"></script>
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <?= view('Volt\\Core\\Metadata\\Views\\partials\\desk_topbar', compact('currentUserName', 'isAdmin', 'deskActive')) ?>

    <main class="mx-auto max-w-xl p-4 lg:p-8">
        <div class="mb-6">
            <h1 class="text-2xl font-semibold"><?= esc($p['edit'] ?? 'Edit profile') ?></h1>
            <p class="mt-1 text-sm text-slate-500"><?= esc($p['description'] ?? '') ?></p>
        </div>

        <?php if (! empty($error)): ?>
            <div class="mb-4 border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800"><?= esc((string) $error) ?></div>
        <?php endif; ?>
        <?php if (! empty($success)): ?>
            <div class="mb-4 border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= esc((string) $success) ?></div>
        <?php endif; ?>

        <section class="mb-4 border border-slate-300 bg-white p-4">
            <dl class="grid gap-3 text-sm">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500"><?= esc($p['username'] ?? 'Username') ?></dt>
                    <dd class="mt-1 font-medium"><?= esc((string) $user->name) ?></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500"><?= esc($p['roles'] ?? 'Roles') ?></dt>
                    <dd class="mt-1"><?= esc($roles !== [] ? implode(', ', $roles) : '—') ?></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500"><?= esc($p['status'] ?? 'Status') ?></dt>
                    <dd class="mt-1"><?= $user->isActive() ? esc($c['active'] ?? 'Active') : esc($c['inactive'] ?? 'Inactive') ?></dd>
                </div>
            </dl>
        </section>

        <section class="border border-slate-300 bg-white p-4">
            <h2 class="text-sm font-semibold"><?= esc($p['api_key'] ?? 'API Key') ?></h2>
            <p class="mt-1 text-xs text-slate-500"><?= $p['api_instruction'] ?? '' ?></p>

            <?php if (! empty($apiKey) && ! empty($newSecret)): ?>
                <div class="mt-3 border border-amber-300 bg-amber-50 px-4 py-3 text-sm">
                    <p class="font-semibold text-amber-900"><?= esc($p['api_new_key_message'] ?? '') ?></p>
                    <dl class="mt-2 grid gap-1 text-xs">
                        <dt class="font-semibold text-slate-600"><?= esc($p['api_key_label'] ?? 'API Key') ?></dt>
                        <dd class="font-mono text-slate-900"><?= esc($apiKey) ?></dd>
                        <dt class="mt-1 font-semibold text-slate-600"><?= esc($p['api_secret_label'] ?? 'API Secret') ?></dt>
                        <dd class="font-mono text-slate-900"><?= esc($newSecret) ?></dd>
                    </dl>
                </div>
            <?php elseif (! empty($apiKey)): ?>
                <div class="mt-3 flex items-center justify-between rounded border border-slate-200 bg-slate-50 px-4 py-2 text-sm">
                    <span class="font-mono text-slate-700"><?= esc($apiKey) ?></span>
                    <form method="post" action="<?= site_url('desk/profile/generate-api-key') ?>" class="inline">
                        <?= csrf_field() ?>
                        <button
                            type="submit"
                            class="rounded border border-slate-300 bg-white px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100"
                            onclick="return confirm('<?= esc($p['generate_confirm'] ?? '') ?>')"
                        ><?= esc($p['generate_new'] ?? 'Generate new') ?></button>
                    </form>
                </div>
            <?php else: ?>
                <form method="post" action="<?= site_url('desk/profile/generate-api-key') ?>" class="mt-3">
                    <?= csrf_field() ?>
                    <button
                        type="submit"
                        class="rounded border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                    ><?= esc($p['generate_api_key'] ?? 'Generate API Key') ?></button>
                </form>
            <?php endif; ?>
        </section>

        <section class="mt-4 border border-slate-300 bg-white p-4">
            <h2 class="text-sm font-semibold"><?= esc($p['change_password'] ?? 'Change password') ?></h2>
            <form method="post" action="<?= site_url('desk/profile') ?>" class="mt-4 grid gap-3">
                <?= csrf_field() ?>
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500"><?= esc($p['current_password'] ?? 'Current password') ?></span>
                    <input type="password" name="current_password" required autocomplete="current-password" class="w-full border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-slate-500">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500"><?= esc($p['new_password'] ?? 'New password') ?></span>
                    <input type="password" name="password" required minlength="8" autocomplete="new-password" class="w-full border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-slate-500">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500"><?= esc($p['confirm_password'] ?? 'Confirm new password') ?></span>
                    <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" class="w-full border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-slate-500">
                </label>
                <div class="flex justify-end gap-2 pt-2">
                    <a href="<?= site_url('desk') ?>" class="border border-slate-300 bg-white px-4 py-2 text-sm text-slate-800 hover:bg-slate-50"><?= esc($c['cancel'] ?? 'Cancel') ?></a>
                    <button
                        type="submit"
                        class="border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                    ><?= esc($c['save'] ?? 'Save') ?></button>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
