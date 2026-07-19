<?php

/** @var int $moduleCount */
/** @var int $entityCount */
/** @var bool $isAdmin */
/** @var string $currentUserName */
$isAdmin = $isAdmin ?? false;
$currentUserName = $currentUserName ?? '';
$deskActive = 'desk';
?>
<!doctype html>
<html lang="vi">
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
            <h1 class="text-2xl font-semibold">Desk</h1>
            <p class="mt-1 text-sm text-slate-500">Chọn mục bên dưới để làm việc. Entity List nằm ở trang riêng.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 <?= $isAdmin ? 'lg:grid-cols-4' : '' ?>">
            <a href="<?= site_url('desk/entities') ?>" class="border border-slate-300 bg-white p-5 transition hover:border-slate-500">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Browse</p>
                <h2 class="mt-2 text-xl font-semibold">Entity List</h2>
                <p class="mt-2 text-sm text-slate-600">Xem và lọc entity theo module. Admin có thể mở Entity Builder từ danh sách.</p>
                <p class="mt-4 text-sm text-slate-500">Hiện có <?= esc((string) $entityCount) ?> entity.</p>
            </a>

            <?php if ($isAdmin): ?>
                <a href="<?= site_url('desk/users') ?>" class="border border-slate-300 bg-white p-5 transition hover:border-slate-500">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Admin</p>
                    <h2 class="mt-2 text-xl font-semibold">User List</h2>
                    <p class="mt-2 text-sm text-slate-600">Quản lý người dùng và gán role.</p>
                    <p class="mt-4 text-sm text-slate-500">CRUD user + phân quyền.</p>
                </a>

                <a href="<?= site_url('desk/roles') ?>" class="border border-slate-300 bg-white p-5 transition hover:border-slate-500">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Admin</p>
                    <h2 class="mt-2 text-xl font-semibold">Role List</h2>
                    <p class="mt-2 text-sm text-slate-600">Quản lý role và phân quyền CRUD, Import cho từng entity.</p>
                    <p class="mt-4 text-sm text-slate-500">Gồm User Role Permission.</p>
                </a>

                <a href="<?= site_url('desk/system-status') ?>" class="border border-slate-300 bg-white p-5 transition hover:border-slate-500">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Admin</p>
                    <h2 class="mt-2 text-xl font-semibold">System Status</h2>
                    <p class="mt-2 text-sm text-slate-600">Kiểm tra nhanh trạng thái runtime, cache, database và các bảng hệ thống theo kiểu dashboard vận hành.</p>
                    <p class="mt-4 text-sm text-slate-500">Phù hợp để soi lỗi cấu hình và readiness.</p>
                </a>

                <a href="<?= site_url('desk/create-module') ?>" class="border border-slate-300 bg-white p-5 transition hover:border-slate-500">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Admin</p>
                    <h2 class="mt-2 text-xl font-semibold">Create Module</h2>
                    <p class="mt-2 text-sm text-slate-600">Sinh thư mục <code class="text-xs">app/Modules/{Module}</code> và lưu metadata module.</p>
                    <p class="mt-4 text-sm text-slate-500">Hiện có <?= esc((string) $moduleCount) ?> module.</p>
                </a>

                <a href="<?= site_url('desk/entity-builder') ?>" class="border border-slate-300 bg-white p-5 transition hover:border-slate-500">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Admin</p>
                    <h2 class="mt-2 text-xl font-semibold">Entity Builder</h2>
                    <p class="mt-2 text-sm text-slate-600">Cấu hình field, session layout, sync schema và scaffold artifact.</p>
                    <p class="mt-4 text-sm text-slate-500">Chỉ tài khoản admin.</p>
                </a>
            <?php else: ?>
                <div class="border border-amber-300 bg-amber-50 p-5 text-sm text-amber-900 sm:col-span-1">
                    <p class="font-semibold">Quyền hạn chế</p>
                    <p class="mt-2">Create Module và Entity Builder chỉ dành cho <strong>admin</strong>. Bạn vẫn dùng Entity List để xem metadata.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
