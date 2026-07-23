<?php

/** @var string $title */
/** @var string $listUrl */
/** @var string $saveUrl */
/** @var string $loadUrlBase */
/** @var string $recordName */
/** @var array<int, array<string, mixed>> $fields */
/** @var array<int, array<string, mixed>> $sessions */
/** @var array<string, array<string, string>> $linkTargets */
/** @var bool $isSubmittable */
/** @var string $submitUrl */
/** @var string $approveUrl */
/** @var string $cancelUrl */
/** @var string $amendUrl */
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
    <div x-data="leaveFormApp({
            title: <?= esc(json_encode($title, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            listUrl: <?= esc(json_encode($listUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            saveUrl: <?= esc(json_encode($saveUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            loadUrlBase: <?= esc(json_encode($loadUrlBase, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            recordName: <?= esc(json_encode($recordName, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            fields: <?= esc(json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            sessions: <?= esc(json_encode($sessions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            linkTargets: <?= esc(json_encode($linkTargets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            isSubmittable: <?= json_encode($isSubmittable) ?>,
            submitUrl: <?= esc(json_encode($submitUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            approveUrl: <?= esc(json_encode($approveUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            cancelUrl: <?= esc(json_encode($cancelUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            amendUrl: <?= esc(json_encode($amendUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>
        })" x-init="init()" class="mx-auto max-w-4xl p-6">
        <header class="mb-4 flex items-center justify-between border border-zinc-300 bg-white px-4 py-3">
            <div>
                <h1 class="font-semibold"><?= esc($title) ?></h1>
                <p class="text-zinc-500"><?= esc('/hrms/leave') ?></p>
            </div>
            <div class="flex items-center gap-2">
                <template x-if="isSubmittable && recordName">
                    <span class="rounded border px-2 py-1 text-xs font-medium" x-bind:class="workflowStateBadgeClass" x-text="workflowState || 'Draft'"></span>
                </template>
                <a href="<?= esc($listUrl) ?>" class="border border-zinc-300 px-3 py-2 hover:bg-zinc-50">Back to List</a>
                <button @click="save()" type="button" class="inline-flex items-center border border-slate-900 bg-slate-900 px-3 py-2 font-semibold text-white hover:bg-slate-800">Save Item</button>
                <template x-if="isSubmittable && recordName">
                    <div class="flex gap-1">
                        <button @click="submitWorkflow()" type="button" class="border border-amber-500 bg-amber-50 px-3 py-2 text-sm hover:bg-amber-100" x-show="canSubmit">Submit</button>
                        <button @click="approveWorkflow()" type="button" class="border border-emerald-500 bg-emerald-50 px-3 py-2 text-sm hover:bg-emerald-100" x-show="canApprove">Approve</button>
                        <button @click="cancelWorkflow()" type="button" class="border border-red-300 px-3 py-2 text-sm hover:bg-red-50" x-show="canCancel">Cancel</button>
                        <button @click="amendWorkflow()" type="button" class="border border-sky-300 px-3 py-2 text-sm hover:bg-sky-50" x-show="canAmend">Amend</button>
                    </div>
                </template>
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
                                                <template x-if="field.fieldtype === 'Link'">
                                                    <div class="relative" @click.outside="closeLinkLookup(field.fieldname)">
                                                        <input
                                                            x-model="form[field.fieldname]"
                                                            @focus="openLinkLookup(field)"
                                                            @click="openLinkLookup(field)"
                                                            @input="handleLinkInput(field)"
                                                            @change="handleLinkChange(field)"
                                                            type="text"
                                                            class="w-full border border-zinc-300 px-3 py-2 outline-none focus:border-zinc-500"
                                                            :placeholder="field.placeholder || ''"
                                                            :readonly="field.read_only"
                                                            :required="field.is_required"
                                                            autocomplete="off"
                                                        >
                                                        <div x-show="linkLookupOpen(field.fieldname)" x-cloak class="absolute left-0 top-12 z-20 w-[22rem] max-w-[calc(100vw-3rem)] border border-zinc-300 bg-white shadow-sm">
                                                            <div x-show="linkLookupState(field.fieldname).loading" x-cloak class="border-b border-zinc-200 px-3 py-2 text-sm text-zinc-500">
                                                                Searching...
                                                            </div>
                                                            <div class="max-h-80 overflow-auto">
                                                                <template x-for="item in linkLookupState(field.fieldname).items" :key="item.name">
                                                                    <button @click.prevent="selectLinkLookupItem(field, item)" type="button" class="block w-full border-b border-zinc-100 px-3 py-2 text-left hover:bg-zinc-50">
                                                                        <div class="font-medium text-zinc-900" x-text="linkLookupCodeText(item)"></div>
                                                                        <div x-show="linkLookupPrimaryText(field, item) !== ''" x-cloak class="text-sm text-zinc-500" x-text="linkLookupPrimaryText(field, item)"></div>
                                                                    </button>
                                                                </template>
                                                                <div x-show="!linkLookupState(field.fieldname).loading && linkLookupState(field.fieldname).items.length === 0" x-cloak class="px-3 py-2 text-sm text-zinc-500">
                                                                    No linked record found.
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                                <template x-if="field.fieldtype === 'Table' || field.fieldtype === 'Child Table (JSONB)'">
                                                    <div class="w-full" :class="field.read_only ? 'opacity-60 pointer-events-none' : ''">
                                                        <table class="w-full border-collapse border border-zinc-300 text-sm">
                                                            <thead>
                                                                <tr class="bg-zinc-100">
                                                                    <template x-for="col in (field.child_columns || [])" :key="col.fieldname">
                                                                        <th class="border border-zinc-300 px-2 py-1.5 text-left font-medium" x-text="col.label || col.fieldname"></th>
                                                                    </template>
                                                                    <th x-show="!field.read_only" class="border border-zinc-300 px-2 py-1.5 w-10"></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <template x-for="(row, rowIdx) in (form[field.fieldname] || [])" :key="rowIdx">
                                                                    <tr>
                                                                        <template x-for="col in (field.child_columns || [])" :key="col.fieldname">
                                                                            <td class="border border-zinc-300 px-2 py-1">
                                                                                <template x-if="col.fieldtype === 'Check'">
                                                                                    <input type="checkbox" x-model="form[field.fieldname][rowIdx][col.fieldname]" class="h-4 w-4 border-zinc-400">
                                                                                </template>
                                                                                <template x-if="col.fieldtype === 'Select'">
                                                                                    <select x-model="form[field.fieldname][rowIdx][col.fieldname]" class="w-full border border-zinc-300 px-1.5 py-1 text-sm">
                                                                                        <option value="">Select</option>
                                                                                        <template x-for="opt in parseOptions(col.options || '')" :key="opt">
                                                                                            <option :value="opt" x-text="opt"></option>
                                                                                        </template>
                                                                                    </select>
                                                                                </template>
                                                                                <template x-if="col.fieldtype === 'Int'">
                                                                                    <input type="number" step="1" x-model="form[field.fieldname][rowIdx][col.fieldname]" class="w-full border border-zinc-300 px-1.5 py-1 text-sm">
                                                                                </template>
                                                                                <template x-if="col.fieldtype === 'Float'">
                                                                                    <input type="number" step="any" x-model="form[field.fieldname][rowIdx][col.fieldname]" class="w-full border border-zinc-300 px-1.5 py-1 text-sm">
                                                                                </template>
                                                                                <template x-if="!['Check', 'Select', 'Int', 'Float'].includes(col.fieldtype)">
                                                                                    <input type="text" x-model="form[field.fieldname][rowIdx][col.fieldname]" class="w-full border border-zinc-300 px-1.5 py-1 text-sm">
                                                                                </template>
                                                                            </td>
                                                                        </template>
                                                                        <td x-show="!field.read_only" class="border border-zinc-300 px-2 py-1 text-center">
                                                                            <button @click="removeChildRow(field.fieldname, rowIdx)" type="button" class="text-red-600 hover:text-red-800 text-xs font-bold" title="Remove row">&times;</button>
                                                                        </td>
                                                                    </tr>
                                                                </template>
                                                            </tbody>
                                                        </table>
                                                        <button x-show="!field.read_only" @click="addChildRow(field.fieldname)" type="button" class="mt-1 border border-zinc-300 px-2 py-1 text-xs hover:bg-zinc-50">+ Add Row</button>
                                                    </div>
                                                </template>
                                                <template x-if="field.fieldtype === 'Text' || field.fieldtype === 'Code'">
                                                    <textarea x-model="form[field.fieldname]" rows="6" class="w-full border border-zinc-300 px-3 py-2 outline-none focus:border-zinc-500" :placeholder="field.placeholder || ''" :readonly="field.read_only" :required="field.is_required"></textarea>
                                                </template>
                                                <template x-if="field.fieldtype === 'Attach' || field.fieldtype === 'Attach Image'">
                                                    <div class="flex items-center gap-2" :class="field.read_only ? 'opacity-60 pointer-events-none' : ''">
                                                        <template x-if="form[field.fieldname]">
                                                            <a :href="fileDownloadUrl(form[field.fieldname])" target="_blank" class="inline-flex items-center gap-1 text-sky-700 underline text-sm" x-text="'View ' + (form[field.fieldname] || '').substring(0, 8) + '...'"></a>
                                                        </template>
                                                        <input type="file" :accept="field.fieldtype === 'Attach Image' ? 'image/*' : ''" @change="handleFileSelect(field, $event)" class="block w-full text-sm text-zinc-500 file:mr-2 file:border file:border-zinc-300 file:px-2 file:py-1 file:text-sm file:bg-zinc-50 file:hover:bg-zinc-100" :disabled="field.read_only" :required="field.is_required && !form[field.fieldname]">
                                                        <div x-show="form[field.fieldname + '__uploading']" x-cloak class="text-xs text-zinc-500">Uploading...</div>
                                                    </div>
                                                </template>
                                                <template x-if="!['Check', 'Select', 'Link', 'Text', 'Code', 'Table', 'Child Table (JSONB)', 'Attach', 'Attach Image'].includes(field.fieldtype)">
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

    <script><?php readfile(APPPATH . 'Modules/Hrms/Entities/Leave/leave_form.js'); ?></script>
</body>
</html>