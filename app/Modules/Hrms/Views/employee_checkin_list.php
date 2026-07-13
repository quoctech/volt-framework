<?php

/** @var string $title */
/** @var string $dataUrl */
/** @var string $createUrl */
/** @var string $editUrlBase */
/** @var string $builderUrl */
/** @var array<string, array<string, string>> $linkTargets */
$columns = json_decode('[{"fieldname":"name","label":"Name","fieldtype":"Data"},{"fieldname":"employee","label":"Nhân Viên","fieldtype":"Link"}]', true) ?: [];
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title) ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/vendor/tailwindcss/tailwind.min.css') ?>">
    <script defer src="<?= base_url('assets/vendor/alpinejs/alpine.min.js') ?>"></script>
</head>
<body class="min-h-screen bg-zinc-100 text-base text-zinc-900">
    <div x-data="employee_checkinListApp({
            title: <?= esc(json_encode($title, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            dataUrl: <?= esc(json_encode($dataUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            createUrl: <?= esc(json_encode($createUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            editUrlBase: <?= esc(json_encode($editUrlBase, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            deleteUrlBase: <?= esc(json_encode(site_url('hrms/api/employee_checkin/delete'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            columns: <?= esc(json_encode($columns, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            linkTargets: <?= esc(json_encode($linkTargets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>
        })" x-init="init()" class="mx-auto max-w-7xl p-6">
        <header class="mb-4 flex items-center justify-between border border-zinc-300 bg-white px-4 py-3">
            <div>
                <h1 class="font-semibold"><?= esc($title) ?></h1>
                <p class="text-zinc-500">Generated list route: <?= esc('/hrms/employee_checkin') ?></p>
            </div>
            <div class="flex gap-2">
                <a href="<?= esc($builderUrl) ?>" class="border border-zinc-300 px-3 py-2 hover:bg-zinc-50">Open Builder</a>
                <a href="<?= esc($createUrl) ?>" class="inline-flex items-center border border-slate-900 bg-slate-900 px-3 py-2 font-semibold text-white hover:bg-slate-800">Create EmployeeCheckin</a>
            </div>
        </header>

        <section class="border border-zinc-300 bg-white">
            <div class="flex flex-wrap items-center gap-3 border-b border-zinc-300 px-4 py-3">
                <input x-model="query" @keydown.enter.prevent="load(1)" type="text" placeholder="Filter rows" class="min-w-64 flex-1 border border-zinc-300 px-3 py-2 outline-none focus:border-zinc-500">
                <select x-model="perPage" @change="load(1)" class="border border-zinc-300 px-3 py-2 outline-none focus:border-zinc-500">
                    <template x-for="option in perPageOptions" :key="option">
                        <option :value="option" x-text="option"></option>
                    </template>
                </select>
                <button @click="load(1)" type="button" class="border border-zinc-300 px-3 py-2 hover:bg-zinc-50">Reload</button>
            </div>

            <div class="overflow-auto">
                <table class="min-w-full border-collapse">
                    <thead class="bg-zinc-50">
                        <tr>
                            <template x-for="column in columns" :key="column.fieldname">
                                <th class="border-b border-zinc-300 px-4 py-3 text-left font-medium" x-text="column.label"></th>
                            </template>
                            <th class="border-b border-zinc-300 px-4 py-3 text-left font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-if="loading">
                            <tr>
                                <td :colspan="columns.length + 1" class="px-4 py-8 text-center text-zinc-500">Loading...</td>
                            </tr>
                        </template>
                        <template x-if="!loading && rows.length === 0">
                            <tr>
                                <td :colspan="columns.length + 1" class="px-4 py-8 text-center text-zinc-500">No rows found.</td>
                            </tr>
                        </template>
                        <template x-for="row in rows" :key="row.name ?? JSON.stringify(row)">
                            <tr class="border-b border-zinc-200">
                                <template x-for="column in columns" :key="column.fieldname">
                                    <td class="px-4 py-3">
                                        <template x-if="isLinkColumn(column) && canOpenLinkedRecord(column, row)">
                                            <button @click="openLinkedRecord(column, row)" type="button" class="text-left text-sky-700 underline" x-text="linkDisplayValue(column, row)"></button>
                                        </template>
                                        <template x-if="!isLinkColumn(column) || !canOpenLinkedRecord(column, row)">
                                            <span x-text="isLinkColumn(column) ? linkDisplayValue(column, row) : cellValue(row, column.fieldname)"></span>
                                        </template>
                                    </td>
                                </template>
                                <td class="px-4 py-3">
                                    <div class="flex gap-2">
                                        <button @click="openEdit(row.name)" type="button" class="border border-zinc-300 px-2 py-1 hover:bg-zinc-50">Edit</button>
                                        <button @click="deleteRow(row.name)" type="button" class="border border-zinc-300 px-2 py-1 hover:bg-zinc-50">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between border-t border-zinc-300 px-4 py-3">
                <p class="text-zinc-500" x-text="paginationText()"></p>
                <div class="flex gap-2">
                    <button @click="load(page - 1)" :disabled="page <= 1" type="button" class="border border-zinc-300 px-3 py-2 disabled:opacity-40">Prev</button>
                    <button @click="load(page + 1)" :disabled="page >= totalPages" type="button" class="border border-zinc-300 px-3 py-2 disabled:opacity-40">Next</button>
                </div>
            </div>
        </section>
    </div>

    <script><?php readfile(APPPATH . 'Modules/Hrms/Entities/EmployeeCheckin/employee_checkin_list.js'); ?></script>
</body>
</html>