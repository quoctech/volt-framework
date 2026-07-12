<?php

/** @var string $title */
/** @var string $listUrl */
/** @var string $saveUrl */
/** @var string $loadUrlBase */
/** @var string $recordName */
/** @var array<int, array<string, mixed>> $fields */
/** @var array<int, array<string, mixed>> $sessions */
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
            sessions: <?= esc(json_encode($sessions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>
        })" x-init="init()" class="mx-auto max-w-4xl p-6">
        <header class="mb-4 flex items-center justify-between border border-zinc-300 bg-white px-4 py-3">
            <div>
                <h1 class="font-semibold"><?= esc($title) ?></h1>
                <p class="text-zinc-500"><?= esc('/hrms/employee') ?></p>
            </div>
            <div class="flex gap-2">
                <a href="<?= esc($listUrl) ?>" class="border border-zinc-300 px-3 py-2 hover:bg-zinc-50">Back to List</a>
                <button @click="save()" type="button" class="inline-flex items-center border border-slate-900 bg-slate-900 px-3 py-2 font-semibold text-white hover:bg-slate-800">Save Item</button>
            </div>
        </header>

        <section class="border border-zinc-300 bg-white p-4">
            <div class="space-y-6">
                <template x-for="session in sessions" :key="session.uid">
                    <section class="border border-zinc-200 bg-zinc-50/40">
                        <div class="border-b border-zinc-200 px-4 py-3">
                            <h2 class="font-medium" x-text="session.title || 'Session'"></h2>
                            <p x-show="session.description" class="mt-1 text-sm text-zinc-500" x-text="session.description"></p>
                        </div>
                        <div class="p-4">
                            <div class="grid gap-4" :style="sessionGridStyle(session)">
                                <template x-for="columnNumber in sessionColumnNumbers(session)" :key="session.uid + '_' + columnNumber">
                                    <div class="space-y-4">
                                        <template x-for="field in sessionFieldsByColumn(session.uid, columnNumber)" :key="field.fieldname">
                                            <label class="block">
                                                <span class="mb-1 flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500">
                                                    <span x-text="field.label"></span>
                                                    <span x-show="field.is_required" x-cloak class="text-red-600">*</span>
                                                    <span x-show="field.read_only" x-cloak class="border border-sky-300 bg-sky-50 px-1.5 py-0.5 text-[10px] tracking-normal text-sky-800">Read only</span>
                                                </span>
                                                <template x-if="field.fieldtype === 'Check'">
                                                    <input x-model="form[field.fieldname]" type="checkbox" class="h-4 w-4 border-zinc-400" :disabled="field.read_only">
                                                </template>
                                                <template x-if="field.fieldtype === 'Select'">
                                                    <select x-model="form[field.fieldname]" class="w-full border border-zinc-300 px-3 py-2 outline-none focus:border-zinc-500" :disabled="field.read_only" :required="field.is_required">
                                                        <option value="">Select</option>
                                                        <template x-for="option in parseOptions(field.options)" :key="option">
                                                            <option :value="option" x-text="option"></option>
                                                        </template>
                                                    </select>
                                                </template>
                                                <template x-if="field.fieldtype === 'Text' || field.fieldtype === 'Code'">
                                                    <textarea x-model="form[field.fieldname]" rows="6" class="w-full border border-zinc-300 px-3 py-2 outline-none focus:border-zinc-500" :placeholder="field.placeholder || ''" :readonly="field.read_only" :required="field.is_required"></textarea>
                                                </template>
                                                <template x-if="!['Check', 'Select', 'Text', 'Code'].includes(field.fieldtype)">
                                                    <input x-model="form[field.fieldname]" :type="inputType(field.fieldtype)" class="w-full border border-zinc-300 px-3 py-2 outline-none focus:border-zinc-500" :placeholder="field.placeholder || ''" :readonly="field.read_only" :required="field.is_required">
                                                </template>
                                            </label>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </section>
                </template>
            </div>
        </section>
    </div>

    <script><?php readfile(APPPATH . 'Modules/Hrms/Entities/Employee/employee_form.js'); ?></script>
</body>
</html>