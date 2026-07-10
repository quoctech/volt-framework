<?php

/** @var string $title */
/** @var string $listUrl */
/** @var string $saveUrl */
/** @var string $loadUrlBase */
/** @var string $recordName */
/** @var array<int, array<string, mixed>> $fields */
/** @var string $csrfTokenName */
/** @var string $csrfHash */
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
    <div x-data="employeeFormApp({
            title: <?= esc(json_encode($title, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            listUrl: <?= esc(json_encode($listUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            saveUrl: <?= esc(json_encode($saveUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            loadUrlBase: <?= esc(json_encode($loadUrlBase, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            recordName: <?= esc(json_encode($recordName, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            fields: <?= esc(json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            csrfTokenName: <?= esc(json_encode($csrfTokenName, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            csrfHash: <?= esc(json_encode($csrfHash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>
        })" x-init="init()" class="mx-auto max-w-4xl p-6">
        <header class="mb-4 flex items-center justify-between border border-zinc-300 bg-white px-4 py-3">
            <div>
                <h1 class="font-semibold"><?= esc($title) ?></h1>
                <p class="text-zinc-500"><?= esc('/hrms/employee') ?></p>
            </div>
            <div class="flex gap-2">
                <a href="<?= esc($listUrl) ?>" class="border border-zinc-300 px-3 py-2 hover:bg-zinc-50">Back to List</a>
                <button @click="save()" type="button" class="inline-flex items-center border border-zinc-900 bg-zinc-950 px-3 py-2 font-semibold text-white hover:bg-zinc-800">Save Item</button>
            </div>
        </header>

        <section class="border border-zinc-300 bg-white p-4">
            <div class="grid gap-4 lg:grid-cols-2">
                <template x-for="field in fields" :key="field.fieldname">
                    <label class="block" :class="field.fieldtype === 'Text' || field.fieldtype === 'Code' ? 'lg:col-span-2' : ''">
                        <span class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500" x-text="field.label"></span>
                        <template x-if="field.fieldtype === 'Check'">
                            <input x-model="form[field.fieldname]" type="checkbox" class="h-4 w-4 border-zinc-400">
                        </template>
                        <template x-if="field.fieldtype === 'Select'">
                            <select x-model="form[field.fieldname]" class="w-full border border-zinc-300 px-3 py-2 outline-none focus:border-zinc-500">
                                <option value="">Select</option>
                                <template x-for="option in parseOptions(field.options)" :key="option">
                                    <option :value="option" x-text="option"></option>
                                </template>
                            </select>
                        </template>
                        <template x-if="field.fieldtype === 'Text' || field.fieldtype === 'Code'">
                            <textarea x-model="form[field.fieldname]" rows="6" class="w-full border border-zinc-300 px-3 py-2 outline-none focus:border-zinc-500" :placeholder="field.placeholder || ''"></textarea>
                        </template>
                        <template x-if="!['Check', 'Select', 'Text', 'Code'].includes(field.fieldtype)">
                            <input x-model="form[field.fieldname]" :type="inputType(field.fieldtype)" class="w-full border border-zinc-300 px-3 py-2 outline-none focus:border-zinc-500" :placeholder="field.placeholder || ''">
                        </template>
                    </label>
                </template>
            </div>
        </section>
    </div>

    <script><?php readfile(APPPATH . 'Modules/Hrms/DocTypes/Employee/employee_form.js'); ?></script>
</body>
</html>
