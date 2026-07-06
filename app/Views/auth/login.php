<?php

/** @var bool $setupRequired */
/** @var string $mode */
/** @var string|null $error */
/** @var string|null $success */
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Volt Framework | Auth</title>
    <link rel="stylesheet" href="<?= base_url('assets/vendor/tailwindcss/tailwind.min.css') ?>">
    <script defer src="<?= base_url('assets/vendor/alpinejs/alpine.min.js') ?>"></script>
    <style>[x-cloak]{display:none !important;}</style>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
<div class="relative isolate min-h-screen overflow-hidden">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(34,211,238,0.18),_transparent_28%),radial-gradient(circle_at_bottom_right,_rgba(15,23,42,0.92),_rgba(2,6,23,1))]"></div>
    <div class="absolute left-0 top-0 h-72 w-72 -translate-x-1/3 -translate-y-1/3 rounded-full bg-cyan-400/10 blur-3xl"></div>
    <div class="absolute right-0 top-1/3 h-96 w-96 translate-x-1/3 rounded-full bg-sky-500/10 blur-3xl"></div>

    <div class="relative mx-auto flex min-h-screen max-w-7xl items-center px-6 py-10 lg:px-8">
        <div class="grid w-full gap-8 lg:grid-cols-[1.05fr_0.95fr]">
            <section class="flex flex-col justify-center">
                <div class="mb-6 flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl border border-cyan-400/25 bg-cyan-400/10 text-lg font-black text-cyan-200 shadow-lg shadow-cyan-950/30">V</div>
                    <div>
                        <p class="text-xs uppercase tracking-[0.35em] text-cyan-200/70">Volt Framework</p>
                        <p class="mt-1 text-sm text-slate-400">Auth là core Entity</p>
                    </div>
                </div>

                <p class="mb-4 inline-flex w-fit rounded-full border border-cyan-400/25 bg-cyan-400/10 px-4 py-1 text-xs font-semibold uppercase tracking-[0.3em] text-cyan-100">
                    <?= $setupRequired ? 'First-time setup' : 'Secure sign in' ?>
                </p>

                <h1 class="max-w-2xl text-4xl font-semibold tracking-tight text-white sm:text-5xl lg:text-6xl">
                    <?= $setupRequired ? 'Khởi tạo admin đầu tiên cho Volt' : 'Đăng nhập vào Volt Framework' ?>
                </h1>

                <p class="mt-5 max-w-xl text-base leading-7 text-slate-300 sm:text-lg">
                    Page login dùng session, API login dùng bearer token. Nếu chưa có admin, `/login` sẽ tự chuyển sang màn setup thay vì đẩy lỗi trống.
                </p>

                <div class="mt-8 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-3xl border border-white/10 bg-white/5 p-5 shadow-lg shadow-black/10 backdrop-blur">
                        <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Page</p>
                        <p class="mt-2 text-lg font-semibold text-white">Session</p>
                        <p class="mt-2 text-sm leading-6 text-slate-400">Bảo vệ luồng page bằng filter và CSRF.</p>
                    </div>
                    <div class="rounded-3xl border border-white/10 bg-white/5 p-5 shadow-lg shadow-black/10 backdrop-blur">
                        <p class="text-xs uppercase tracking-[0.25em] text-slate-400">API</p>
                        <p class="mt-2 text-lg font-semibold text-white">Bearer token</p>
                        <p class="mt-2 text-sm leading-6 text-slate-400">Không dựa vào session, hash token trong DB.</p>
                    </div>
                    <div class="rounded-3xl border border-white/10 bg-white/5 p-5 shadow-lg shadow-black/10 backdrop-blur">
                        <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Bootstrap</p>
                        <p class="mt-2 text-lg font-semibold text-white">Admin setup</p>
                        <p class="mt-2 text-sm leading-6 text-slate-400">Tự kích hoạt khi hệ thống chưa có admin.</p>
                    </div>
                </div>
            </section>

            <section class="relative rounded-[2rem] border border-white/10 bg-slate-900/80 p-4 shadow-[0_30px_90px_rgba(2,6,23,0.55)] backdrop-blur">
                <div class="absolute inset-x-8 top-0 h-px bg-gradient-to-r from-transparent via-cyan-300/60 to-transparent"></div>
                <div x-data="{ mode: '<?= esc($mode ?: ($setupRequired ? 'setup' : 'login')) ?>', showPassword: false }" class="rounded-[1.5rem] border border-white/5 bg-slate-950/70 p-6 sm:p-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm uppercase tracking-[0.3em] text-slate-500">Access portal</p>
                            <h2 class="mt-2 text-2xl font-semibold text-white" x-text="mode === 'setup' ? 'Tạo admin' : 'Đăng nhập'"></h2>
                        </div>
                        <div class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-medium text-slate-300">
                            <?= esc($setupRequired ? 'Setup required' : 'Ready') ?>
                        </div>
                    </div>

                    <div class="mt-6 grid grid-cols-2 gap-2 rounded-2xl border border-white/10 bg-white/5 p-1 text-sm font-medium">
                        <button type="button" class="rounded-xl px-4 py-2.5 transition" :class="mode === 'login' ? 'bg-cyan-400 text-slate-950 shadow-lg shadow-cyan-950/30' : 'text-slate-300 hover:text-white'" @click="mode = 'login'">Login</button>
                        <button type="button" class="rounded-xl px-4 py-2.5 transition" :class="mode === 'setup' ? 'bg-cyan-400 text-slate-950 shadow-lg shadow-cyan-950/30' : 'text-slate-300 hover:text-white'" @click="mode = 'setup'">Setup</button>
                    </div>

                    <div class="mt-6">
                        <?php if (! empty($error)): ?>
                            <div class="mb-5 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                                <?= esc($error) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (! empty($success)): ?>
                            <div class="mb-5 rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                                <?= esc($success) ?>
                            </div>
                        <?php endif; ?>

                        <form x-show="mode === 'login'" x-cloak action="<?= site_url('login') ?>" method="post" class="space-y-4">
                            <?= csrf_field() ?>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-slate-300" for="login_name">Tên đăng nhập</label>
                                <input id="login_name" name="name" type="text" autocomplete="username" required class="w-full rounded-2xl border border-white/10 bg-slate-900 px-4 py-3.5 text-white outline-none transition placeholder:text-slate-500 focus:border-cyan-400/60 focus:ring-2 focus:ring-cyan-400/15" placeholder="admin">
                            </div>
                            <div>
                                <div class="mb-2 flex items-center justify-between">
                                    <label class="block text-sm font-medium text-slate-300" for="login_password">Mật khẩu</label>
                                    <button type="button" class="text-xs font-medium text-cyan-300 transition hover:text-cyan-200" @click="showPassword = ! showPassword" x-text="showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"></button>
                                </div>
                                <input id="login_password" name="password" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" required class="w-full rounded-2xl border border-white/10 bg-slate-900 px-4 py-3.5 text-white outline-none transition placeholder:text-slate-500 focus:border-cyan-400/60 focus:ring-2 focus:ring-cyan-400/15" placeholder="••••••••">
                            </div>
                            <button type="submit" class="group relative w-full overflow-hidden rounded-2xl bg-cyan-400 px-4 py-3.5 font-semibold text-slate-950 transition hover:bg-cyan-300">
                                <span class="relative z-10">Đăng nhập</span>
                                <span class="absolute inset-0 bg-gradient-to-r from-cyan-300/0 via-white/30 to-cyan-300/0 opacity-0 transition group-hover:opacity-100"></span>
                            </button>
                        </form>

                        <form x-show="mode === 'setup'" x-cloak action="<?= site_url('setup') ?>" method="post" class="space-y-4">
                            <?= csrf_field() ?>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-slate-300" for="setup_name">Tên admin</label>
                                <input id="setup_name" name="name" type="text" autocomplete="username" required class="w-full rounded-2xl border border-white/10 bg-slate-900 px-4 py-3.5 text-white outline-none transition placeholder:text-slate-500 focus:border-cyan-400/60 focus:ring-2 focus:ring-cyan-400/15" placeholder="admin">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-slate-300" for="setup_password">Mật khẩu mới</label>
                                <input id="setup_password" name="password" type="password" autocomplete="new-password" required class="w-full rounded-2xl border border-white/10 bg-slate-900 px-4 py-3.5 text-white outline-none transition placeholder:text-slate-500 focus:border-cyan-400/60 focus:ring-2 focus:ring-cyan-400/15" placeholder="Ít nhất 12 ký tự">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-slate-300" for="setup_password_confirmation">Xác nhận mật khẩu</label>
                                <input id="setup_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="w-full rounded-2xl border border-white/10 bg-slate-900 px-4 py-3.5 text-white outline-none transition placeholder:text-slate-500 focus:border-cyan-400/60 focus:ring-2 focus:ring-cyan-400/15" placeholder="Nhập lại mật khẩu">
                            </div>
                            <button type="submit" class="w-full rounded-2xl bg-white px-4 py-3.5 font-semibold text-slate-950 transition hover:bg-cyan-50">Tạo admin</button>
                        </form>

                        <div class="mt-6 grid gap-3 rounded-2xl border border-white/10 bg-white/5 p-4 text-sm leading-6 text-slate-300 sm:grid-cols-2">
                            <div>
                                <p class="font-medium text-white">Security</p>
                                <p class="mt-1 text-slate-400">CSRF bật cho form page, API login không dùng session.</p>
                            </div>
                            <div>
                                <p class="font-medium text-white">Storage</p>
                                <p class="mt-1 text-slate-400">Token API lưu hash, không giữ plain token trong DB.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
</body>
</html>
