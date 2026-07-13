<?php

/** @var array<int, string> $modules */
/** @var string $moduleFilter */
/** @var array<int, array<string, mixed>> $entities */
/** @var bool $isAdmin */
/** @var string $currentUserName */
$isAdmin = $isAdmin ?? false;
$currentUserName = $currentUserName ?? '';
$moduleFilter = $moduleFilter ?? '';
$modules = $modules ?? [];
$entities = $entities ?? [];
$deskActive = 'entities';
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entity List · Volt Desk</title>
    <link rel="stylesheet" href="<?= base_url('assets/vendor/tailwindcss/tailwind.min.css') ?>">
    <script defer src="<?= base_url('assets/vendor/alpinejs/alpine.min.js') ?>"></script>
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <?= view('Volt\\Core\\Metadata\\Views\\partials\\desk_topbar', compact('currentUserName', 'isAdmin', 'deskActive')) ?>

    <main class="mx-auto max-w-5xl p-4 lg:p-8">
        <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Entity List</h1>
                <p class="mt-1 text-sm text-slate-500">Lọc entity theo module để kiểm tra metadata đã có.</p>
            </div>

            <form method="get" action="<?= site_url('desk/entities') ?>" class="flex gap-2">
                <select name="module" class="border border-slate-300 bg-white px-3 py-2 text-sm outline-none">
                    <option value="">All modules</option>
                    <?php foreach ($modules as $module): ?>
                        <option value="<?= esc($module) ?>" <?= $moduleFilter === $module ? 'selected' : '' ?>><?= esc($module) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="border border-slate-300 bg-white px-4 py-2 text-sm hover:bg-slate-50">Filter</button>
            </form>
        </div>

        <section class="border border-slate-300 bg-white p-4">
            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-slate-300 text-left text-slate-500">
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
                                <td colspan="5" class="py-4 text-slate-500">Không có entity nào khớp bộ lọc hiện tại.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($entities as $entity): ?>
                            <tr class="border-b border-slate-200">
                                <td class="py-2 pr-4">
                                    <?php if ($isAdmin): ?>
                                        <a href="<?= site_url('desk/entity-builder?entity=' . rawurlencode((string) $entity['name'])) ?>" class="underline">
                                            <?= esc((string) $entity['name']) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= esc((string) $entity['name']) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2 pr-4"><?= esc((string) $entity['label']) ?></td>
                                <td class="py-2 pr-4"><?= esc((string) $entity['module']) ?></td>
                                <td class="py-2 pr-4"><?= esc((string) $entity['autoname']) ?></td>
                                <td class="py-2 pr-4"><?= ! empty($entity['is_submittable']) ? 'Yes' : 'No' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
