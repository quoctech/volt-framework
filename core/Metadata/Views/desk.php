<?php

/** @var int $moduleCount */
/** @var int $entityCount */
/** @var array<int, string> $modules */
/** @var string $moduleFilter */
/** @var array<int, array<string, mixed>> $entities */
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Volt Desk</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="min-h-screen bg-zinc-100 text-zinc-900">
    <main class="mx-auto max-w-5xl p-4 lg:p-8">
        <div class="mb-6">
            <h1 class="text-2xl font-semibold">Desk</h1>
            <p class="mt-1 text-sm text-zinc-500">Tạo module trước, sau đó dùng module đó để dựng entity.</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <a href="<?= site_url('desk/create-module') ?>" class="border border-zinc-300 bg-white p-5 transition hover:border-zinc-500">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">Create Module</p>
                <h2 class="mt-2 text-xl font-semibold">Tạo module mới</h2>
                <p class="mt-2 text-sm text-zinc-600">Sinh thư mục `app/Modules/{Module}` và lưu metadata module vào database.</p>
                <p class="mt-4 text-sm text-zinc-500">Hiện có <?= esc((string) $moduleCount) ?> module.</p>
            </a>

            <a href="<?= site_url('desk/entity-builder') ?>" class="border border-zinc-300 bg-white p-5 transition hover:border-zinc-500">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">Entity Builder</p>
                <h2 class="mt-2 text-xl font-semibold">Tạo entity</h2>
                <p class="mt-2 text-sm text-zinc-600">Chọn module có sẵn, cấu hình session, field và sinh file DocType JSON/PHP.</p>
                <p class="mt-4 text-sm text-zinc-500">Hiện có <?= esc((string) $entityCount) ?> entity.</p>
            </a>
        </div>

        <section class="mt-6 border border-zinc-300 bg-white p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Entity List</h2>
                    <p class="mt-1 text-sm text-zinc-500">Lọc entity theo module để kiểm tra metadata đã có.</p>
                </div>

                <form method="get" action="<?= site_url('desk') ?>" class="flex gap-2">
                    <select name="module" class="border border-zinc-300 px-3 py-2 text-sm outline-none">
                        <option value="">All modules</option>
                        <?php foreach ($modules as $module): ?>
                            <option value="<?= esc($module) ?>" <?= $moduleFilter === $module ? 'selected' : '' ?>><?= esc($module) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="border border-zinc-300 px-4 py-2 text-sm hover:bg-zinc-50">Filter</button>
                </form>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-zinc-300 text-left text-zinc-500">
                            <th class="py-2 pr-4 font-medium">Entity</th>
                            <th class="py-2 pr-4 font-medium">Label</th>
                            <th class="py-2 pr-4 font-medium">Module</th>
                            <th class="py-2 pr-4 font-medium">Autoname</th>
                            <th class="py-2 pr-4 font-medium">Submittable</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($entities === []): ?>
                            <tr>
                                <td colspan="5" class="py-4 text-zinc-500">Không có entity nào khớp bộ lọc hiện tại.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($entities as $entity): ?>
                            <tr class="border-b border-zinc-200">
                                <td class="py-2 pr-4">
                                    <a href="<?= site_url('desk/entity-builder?entity=' . rawurlencode((string) $entity['name'])) ?>" class="underline">
                                        <?= esc((string) $entity['name']) ?>
                                    </a>
                                </td>
                                <td class="py-2 pr-4"><?= esc((string) $entity['label']) ?></td>
                                <td class="py-2 pr-4"><?= esc((string) $entity['module']) ?></td>
                                <td class="py-2 pr-4"><?= esc((string) $entity['autoname']) ?></td>
                                <td class="py-2 pr-4"><?= !empty($entity['is_submittable']) ? 'Yes' : 'No' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
