<?php

/** @var array<int, array<string, mixed>> $entities */
/** @var string|null $error */
/** @var string|null $success */
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Volt Entity Builder</title>
    <link rel="stylesheet" href="<?= base_url('assets/vendor/tailwindcss/tailwind.min.css') ?>">
    <script defer src="<?= base_url('assets/vendor/alpinejs/alpine.min.js') ?>"></script>
    <style>[x-cloak]{display:none !important;}</style>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
<main class="mx-auto max-w-7xl px-6 py-10" x-data="entityBuilder()">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-xs uppercase tracking-[0.35em] text-cyan-300">Metadata demo</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">Entity Builder</h1>
            <p class="mt-3 max-w-3xl text-slate-300">Tạo Entity trong sys_entity/sys_entity_field. Kéo thả field, chọn datatype, rồi sync sau như Frappe.</p>
        </div>
        <div class="flex gap-3">
            <a href="<?= site_url('notes') ?>" class="rounded-2xl border border-white/10 bg-white/5 px-4 py-2.5 font-semibold text-slate-100">Notes</a>
            <a href="<?= site_url('/') ?>" class="rounded-2xl border border-white/10 bg-white/5 px-4 py-2.5 font-semibold text-slate-100">Dashboard</a>
        </div>
    </div>

    <?php if (! empty($error)): ?>
        <div class="mt-6 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100"><?= esc($error) ?></div>
    <?php endif; ?>

    <?php if (! empty($success)): ?>
        <div class="mt-6 rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100"><?= esc($success) ?></div>
    <?php endif; ?>

    <form action="<?= site_url('entities/store') ?>" method="post" class="mt-8 grid gap-6 lg:grid-cols-[0.9fr_1.1fr]">
        <?= csrf_field() ?>
        <input type="hidden" name="fields_json" :value="JSON.stringify(fields)">
        <input type="hidden" name="states_json" value='{}'>
        <input type="hidden" name="custom_attributes_json" value='{}'>

        <section class="rounded-3xl border border-white/10 bg-white/5 p-6">
            <h2 class="text-lg font-semibold text-white">Entity config</h2>
            <div class="mt-5 space-y-4">
                <div>
                    <label class="mb-2 block text-sm text-slate-300">Entity name</label>
                    <input name="name" type="text" x-model="entity.name" placeholder="SalesInvoice" class="w-full rounded-2xl border border-white/10 bg-slate-900 px-4 py-3 text-white">
                </div>
                <div>
                    <label class="mb-2 block text-sm text-slate-300">Module</label>
                    <input name="module" type="text" x-model="entity.module" placeholder="sales" class="w-full rounded-2xl border border-white/10 bg-slate-900 px-4 py-3 text-white">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-3 rounded-2xl border border-white/10 bg-slate-900 px-4 py-3">
                        <input name="issingle" type="checkbox" value="1" x-model="entity.issingle" class="rounded border-white/20 bg-slate-800 text-cyan-400">
                        <span class="text-sm text-slate-200">Is single</span>
                    </label>
                    <label class="flex items-center gap-3 rounded-2xl border border-white/10 bg-slate-900 px-4 py-3">
                        <input name="istable" type="checkbox" value="1" x-model="entity.istable" class="rounded border-white/20 bg-slate-800 text-cyan-400">
                        <span class="text-sm text-slate-200">Is table</span>
                    </label>
                </div>
                <div>
                    <label class="mb-2 block text-sm text-slate-300">Autoname</label>
                    <select name="autoname" x-model="entity.autoname" class="w-full rounded-2xl border border-white/10 bg-slate-900 px-4 py-3 text-white">
                        <option value="HASH">HASH</option>
                        <option value="FIELD">FIELD</option>
                        <option value="UUID">UUID</option>
                    </select>
                </div>
            </div>

            <div class="mt-6 rounded-2xl border border-cyan-400/20 bg-cyan-500/10 p-4 text-sm text-cyan-50">
                Kéo thả field bằng tay, hoặc dùng nút lên/xuống để reorder.
            </div>
        </section>

        <section class="rounded-3xl border border-white/10 bg-white/5 p-6">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-white">Fields</h2>
                <button type="button" @click="addField()" class="rounded-2xl bg-cyan-400 px-4 py-2 font-semibold text-slate-950">Add field</button>
            </div>

            <div class="mt-5 space-y-4">
                <template x-for="(field, index) in fields" :key="field.uid">
                    <article class="rounded-3xl border border-white/10 bg-slate-900 p-4" draggable="true" @dragstart="dragStart(index)" @dragover.prevent @drop="drop(index)">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <button type="button" class="cursor-grab rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs uppercase tracking-[0.2em] text-slate-300">Drag</button>
                                <div>
                                    <p class="text-xs uppercase tracking-[0.25em] text-slate-500">Field <span x-text="index + 1"></span></p>
                                    <p class="text-sm text-slate-300" x-text="field.fieldname || 'unnamed_field'"></p>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" @click="move(index, -1)" class="rounded-xl border border-white/10 px-3 py-2 text-xs text-slate-200">Up</button>
                                <button type="button" @click="move(index, 1)" class="rounded-xl border border-white/10 px-3 py-2 text-xs text-slate-200">Down</button>
                                <button type="button" @click="removeField(index)" class="rounded-xl border border-rose-400/20 bg-rose-500/10 px-3 py-2 text-xs text-rose-100">Delete</button>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-3 md:grid-cols-2">
                            <input x-model="field.label" type="text" placeholder="Label" class="rounded-2xl border border-white/10 bg-slate-950 px-4 py-3 text-white">
                            <input x-model="field.fieldname" type="text" placeholder="field_name" class="rounded-2xl border border-white/10 bg-slate-950 px-4 py-3 text-white">
                            <select x-model="field.fieldtype" @change="syncFieldType(field)" class="rounded-2xl border border-white/10 bg-slate-950 px-4 py-3 text-white">
                                <template x-for="option in datatypeOptions" :key="option.value">
                                    <option :value="option.value" x-text="option.label"></option>
                                </template>
                            </select>
                            <input x-model="field.length" type="number" min="1" placeholder="Length" class="rounded-2xl border border-white/10 bg-slate-950 px-4 py-3 text-white">
                            <input x-model="field.options" type="text" placeholder="Options / linked entity / separate" class="rounded-2xl border border-white/10 bg-slate-950 px-4 py-3 text-white md:col-span-2">
                        </div>

                        <div class="mt-4 flex flex-wrap gap-3">
                            <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" x-model="field.reqd"> Required</label>
                            <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" x-model="field.read_only"> Read only</label>
                            <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" x-model="field.hidden"> Hidden</label>
                        </div>
                    </article>
                </template>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit" class="rounded-2xl bg-white px-4 py-3 font-semibold text-slate-950">Create Entity</button>
            </div>
        </section>
    </form>

    <section class="mt-10">
        <h2 class="text-lg font-semibold text-white">Existing Entities</h2>
        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($entities as $entity): ?>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                    <p class="font-medium text-white"><?= esc($entity['name'] ?? '') ?></p>
                    <p class="mt-1 text-sm text-slate-400">Module: <?= esc($entity['module'] ?? '') ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</main>

<script>
function entityBuilder() {
    return {
        entity: {
            name: '',
            module: '',
            issingle: false,
            istable: false,
            autoname: 'HASH',
        },
        datatypeOptions: [
            { value: 'Data', label: 'Data' },
            { value: 'Text', label: 'Text' },
            { value: 'Int', label: 'Int' },
            { value: 'Float', label: 'Float' },
            { value: 'Check', label: 'Check' },
            { value: 'Link', label: 'Link' },
            { value: 'Table', label: 'Table' },
        ],
        fields: [],
        draggingIndex: null,
        init() {
            this.fields = [
                this.createField('title', 'Title', 'Data', 255),
                this.createField('content', 'Content', 'Text', null),
            ];
        },
        createField(fieldname, label, fieldtype, length) {
            return {
                uid: crypto.randomUUID(),
                fieldname,
                label,
                fieldtype,
                length,
                options: '',
                reqd: true,
                read_only: false,
                hidden: false,
            };
        },
        addField() {
            this.fields.push(this.createField('', 'New Field', 'Data', 255));
        },
        removeField(index) {
            this.fields.splice(index, 1);
        },
        move(index, direction) {
            const target = index + direction;
            if (target < 0 || target >= this.fields.length) {
                return;
            }
            const [item] = this.fields.splice(index, 1);
            this.fields.splice(target, 0, item);
        },
        dragStart(index) {
            this.draggingIndex = index;
        },
        drop(index) {
            if (this.draggingIndex === null || this.draggingIndex === index) {
                return;
            }
            const [item] = this.fields.splice(this.draggingIndex, 1);
            this.fields.splice(index, 0, item);
            this.draggingIndex = null;
        },
        syncFieldType(field) {
            if (field.fieldtype === 'Data' && ! field.length) {
                field.length = 255;
            }
            if (field.fieldtype !== 'Data') {
                field.length = field.fieldtype === 'Text' ? null : field.length;
            }
            if (field.fieldtype !== 'Link' && field.fieldtype !== 'Table') {
                field.options = '';
            }
        },
    };
}
</script>
</body>
</html>
