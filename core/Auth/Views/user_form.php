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
        <a href="<?= site_url('desk/users') ?>" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Back to User List
        </a>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900"><?= $isEdit ? 'Edit User' : 'New User' ?></h1>
        <p class="mt-1 text-sm text-slate-500"><?= $isEdit ? 'Chỉnh sửa thông tin người dùng.' : 'Tạo tài khoản người dùng mới.' ?></p>
    </div>

    <?php if ($errors !== []): ?>
        <div class="mb-4 border border-red-300 bg-red-50 p-4 text-sm text-red-800">
            <p class="mb-1 font-medium">Please fix the following errors:</p>
            <ul class="ml-4 list-disc space-y-1">
                <?php foreach ($errors as $error): ?>
                    <li><?= esc($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="border border-slate-300 bg-white p-6">
        <form action="<?= $isEdit ? site_url("desk/users/update/{$user->name}") : site_url('desk/users/store') ?>" method="post">
            <?= csrf_field() ?>

            <div class="grid gap-5">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500" for="name">Username</label>
                    <input
                        id="name"
                        name="name"
                        type="text"
                        required
                        maxlength="100"
                        value="<?= esc($user->name ?? '') ?>"
                        placeholder="john_doe"
                        class="w-full border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-slate-600 <?= $isEdit ? 'bg-slate-100 text-slate-500' : '' ?>"
                        <?= $isEdit ? 'readonly' : '' ?>
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500" for="password">
                        Password <?= $isEdit ? '(để trống nếu không đổi)' : '' ?>
                    </label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        maxlength="255"
                        <?= $isEdit ? '' : 'required' ?>
                        class="w-full border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-slate-600"
                    >
                </div>

                <div>
                    <span class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Roles</span>
                    <div class="grid grid-cols-3 gap-2">
                        <?php foreach ($allRoles as $role): ?>
                            <label class="flex items-center gap-2 rounded border border-slate-200 px-3 py-2 text-sm hover:bg-slate-50">
                                <input
                                    type="checkbox"
                                    name="roles[]"
                                    value="<?= esc($role->name) ?>"
                                    <?= in_array($role->name, $currentRoles, true) ? 'checked' : '' ?>
                                    class="h-4 w-4 border-slate-400 text-slate-700"
                                >
                                <span class="text-slate-700"><?= esc($role->label) ?></span>
                            </label>
                        <?php endforeach; ?>
                        <?php if ($allRoles === []): ?>
                            <p class="col-span-3 text-sm text-slate-400">Chưa có role nào. <a href="<?= site_url('desk/roles/create') ?>" class="text-sky-700 underline">Tạo role</a> trước.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <label class="flex items-center gap-2">
                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            <?= ($isEdit ? ($user->is_active ? true : false) : true) ? 'checked' : '' ?>
                            class="h-4 w-4 border-slate-400 text-slate-700"
                        >
                        <span class="text-sm font-medium text-slate-700">Active</span>
                    </label>
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3 border-t border-slate-200 pt-6">
                <button type="submit" class="border border-slate-900 bg-slate-900 px-5 py-2 text-sm font-medium text-white transition hover:bg-slate-700">
                    <?= $isEdit ? 'Update User' : 'Create User' ?>
                </button>
                <a href="<?= site_url('desk/users') ?>" class="border border-slate-300 px-5 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </div>
</div>
