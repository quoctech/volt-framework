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
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Volt Core Dashboard</title>
    <link rel="stylesheet" href="<?= base_url('assets/vendor/tailwindcss/tailwind.min.css') ?>">
    <script defer src="<?= base_url('assets/vendor/alpinejs/alpine.min.js') ?>"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
<main class="mx-auto flex min-h-screen max-w-5xl items-center px-6 py-12">
    <div class="w-full rounded-3xl border border-white/10 bg-slate-900/80 p-8 shadow-2xl shadow-cyan-950/20 backdrop-blur">
        <p class="text-sm uppercase tracking-[0.35em] text-cyan-300">Volt Core</p>
        <h1 class="mt-4 text-3xl font-semibold text-white">Bạn đã đăng nhập</h1>
        <p class="mt-3 text-slate-300">Xin chào <?= esc($user->name) ?>. Đây là landing page tối thiểu sau auth để tiếp tục phát triển các Entity khác.</p>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                <p class="text-sm text-slate-400">Roles</p>
                <p class="mt-2 font-medium text-white"><?= esc(implode(', ', array_map('strval', $roles))) ?></p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                <p class="text-sm text-slate-400">Status</p>
                <p class="mt-2 font-medium text-white"><?= $user->isActive() ? 'Active' : 'Inactive' ?></p>
            </div>
        </div>

        <div class="mt-8 flex flex-wrap gap-3">
            <form action="<?= site_url('logout') ?>" method="post">
                <?= csrf_field() ?>
                <button type="submit" class="rounded-2xl bg-slate-100 px-4 py-2 font-semibold text-slate-950 transition hover:bg-white">Logout</button>
            </form>
        </div>
    </div>
</main>
</body>
</html>
