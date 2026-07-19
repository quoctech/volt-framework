<?php

/**
 * @var \Volt\Core\Auth\Entities\UserEntity|null $user
 * @var array<int, \Volt\Core\Role\Entities\RoleEntity> $allRoles
 * @var array<string, string> $errors
 */
$isEdit = $user !== null;

$currentRoles = [];
if ($isEdit) {
    $raw = $user->roles;
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $currentRoles = is_array($decoded) ? $decoded : [];
    } elseif (is_array($raw)) {
        $currentRoles = $raw;
    }
}
?><div>
    <div class="mb-6">
        <a href="<?= site_url('desk/users') ?>" class="inline-flex items-center gap-1 text-sm text-gray-600 transition hover:text-gray-900">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Danh sách người dùng
        </a>
    </div>

    <div class="rounded border border-gray-300 bg-white">
        <div class="border-b border-gray-300 bg-gray-100 px-5 py-4">
            <h1 class="text-lg font-bold text-gray-900"><?= $isEdit ? 'Chỉnh sửa người dùng' : 'Thêm người dùng mới' ?></h1>
            <div class="mt-0.5 text-sm text-gray-600"><?= $isEdit ? 'Cập nhật thông tin tài khoản người dùng.' : 'Tạo tài khoản mới cho người dùng trong hệ thống.' ?></div>
        </div>

        <?php if ($errors !== []): ?>
            <div class="m-5 rounded border border-red-300 bg-red-100 px-4 py-3">
                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div class="min-w-0">
                        <p class="font-bold text-red-800">Vui lòng sửa các lỗi sau:</p>
                        <ul class="mt-1.5 list-disc space-y-1 pl-5 text-sm text-red-700">
                            <?php foreach ($errors as $error): ?>
                                <li><?= esc($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <form action="<?= $isEdit ? site_url("desk/users/update/{$user->name}") : site_url('desk/users/store') ?>" method="post" class="px-5 py-5">
            <?= csrf_field() ?>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-semibold text-gray-800" for="name">Tên đăng nhập</label>
                    <input
                        id="name"
                        name="name"
                        type="text"
                        required
                        maxlength="100"
                        value="<?= esc($user->name ?? '') ?>"
                        placeholder="john_doe"
                        class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-gray-500 focus:ring-1 focus:ring-gray-500 <?= $isEdit ? 'bg-gray-100 text-gray-600' : '' ?>"
                        <?= $isEdit ? 'readonly' : '' ?>
                    >
                    <?php if ($isEdit): ?>
                        <div class="mt-1 text-xs text-gray-500">Tên đăng nhập không thể thay đổi.</div>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-gray-800" for="password">
                        Mật khẩu
                        <?php if ($isEdit): ?>
                            <span class="font-normal text-gray-500">(không bắt buộc)</span>
                        <?php endif; ?>
                    </label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        maxlength="255"
                        <?= $isEdit ? '' : 'required' ?>
                        placeholder="<?= $isEdit ? 'Để trống nếu không đổi' : 'Nhập mật khẩu' ?>"
                        class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-gray-500 focus:ring-1 focus:ring-gray-500"
                    >
                </div>
            </div>

            <div class="mt-6">
                <span class="mb-2 block text-sm font-semibold text-gray-800">Vai trò</span>
                <div class="grid gap-1.5 sm:grid-cols-2">
                    <?php foreach ($allRoles as $role): ?>
                        <label class="flex cursor-pointer items-center gap-2.5 rounded border border-gray-300 bg-gray-50 px-3 py-2 text-sm transition hover:bg-gray-100">
                            <input
                                type="checkbox"
                                name="roles[]"
                                value="<?= esc($role->name) ?>"
                                <?= in_array($role->name, $currentRoles, true) ? 'checked' : '' ?>
                                class="h-4 w-4 rounded border-gray-400 text-gray-800 outline-none transition focus:ring-1 focus:ring-gray-500"
                            >
                            <div>
                                <span class="font-medium text-gray-800"><?= esc($role->label) ?></span>
                                <?php if ($role->description): ?>
                                    <div class="text-xs text-gray-500"><?= esc($role->description) ?></div>
                                <?php endif; ?>
                            </div>
                        </label>
                    <?php endforeach; ?>
                    <?php if ($allRoles === []): ?>
                        <p class="col-span-full text-sm text-gray-500">Chưa có vai trò nào. <a href="<?= site_url('desk/roles/create') ?>" class="font-semibold underline hover:text-gray-800">Tạo vai trò</a> trước.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-5">
                <label class="flex cursor-pointer items-center gap-2.5 rounded border border-gray-300 bg-gray-50 px-3 py-2 text-sm transition hover:bg-gray-100">
                    <input
                        id="is_active"
                        type="checkbox"
                        name="is_active"
                        value="1"
                        <?= ($isEdit ? ($user->is_active ? true : false) : true) ? 'checked' : '' ?>
                        class="h-4 w-4 rounded border-gray-400 text-gray-800 outline-none transition focus:ring-1 focus:ring-gray-500"
                    >
                    <div>
                        <span class="font-medium text-gray-800">Kích hoạt tài khoản</span>
                        <div class="text-xs text-gray-500">Cho phép người dùng đăng nhập vào hệ thống.</div>
                    </div>
                </label>
            </div>

            <div class="mt-8 flex flex-col-reverse items-center gap-3 border-t border-gray-200 pt-5 sm:flex-row sm:justify-end">
                <a href="<?= site_url('desk/users') ?>" class="inline-flex w-full items-center justify-center rounded border border-gray-300 bg-white px-5 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-1 sm:w-auto">Huỷ</a>
                <button type="submit" class="inline-flex w-full items-center justify-center rounded border border-transparent bg-gray-800 px-5 py-2 text-sm font-semibold text-white transition hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-1 sm:w-auto">
                    <?= $isEdit ? 'Lưu thay đổi' : 'Tạo người dùng' ?>
                </button>
            </div>
        </form>
    </div>
</div>
