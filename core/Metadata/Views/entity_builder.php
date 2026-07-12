<?php

/** @var array<int, string> $modules */
/** @var string $initialEntityName */
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Volt Entity Builder</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-screen bg-zinc-100 text-base text-zinc-900">
    <div
        x-data="entityBuilderApp(<?= esc(json_encode([
            'modules' => $modules,
            'initialEntityName' => $initialEntityName,
            'loadUrl' => site_url('api/entity-builder/load'),
            'saveUrl' => site_url('api/entity-builder/save'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>)"
        x-init="init()"
        @keydown.window.ctrl.s.prevent="save()"
        @keydown.window.meta.s.prevent="save()"
        class="mx-auto max-w-[1720px] p-4 lg:p-6"
    >
        <div class="border border-zinc-300 bg-white">
            <header class="border-b border-zinc-300 p-4">
                <div class="flex items-center justify-end gap-2">
                    <button x-show="canOpenEntityList()" x-cloak @click="goToEntityList()" type="button" class="border border-zinc-300 px-4 py-2 text-base hover:bg-zinc-50">
                        <span x-text="`Go to ${entity.label || titleize(entity.name || 'Entity')}`"></span>
                    </button>
                    <button @click="loadEntity(entity.name)" type="button" class="border border-zinc-300 px-4 py-2 text-base hover:bg-zinc-50">Load</button>
                    <button @click="save()" type="button" class="border border-zinc-900 bg-zinc-900 px-4 py-2 text-base text-white hover:bg-zinc-700">Save</button>
                </div>

                <div class="mt-4 flex gap-2">
                    <button @click="activeTab = 'entity'" type="button" class="border px-3 py-2 text-base" :class="activeTab === 'entity' ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-300 bg-white text-zinc-700'">Entity</button>
                    <button @click="activeTab = 'settings'" type="button" class="border px-3 py-2 text-base" :class="activeTab === 'settings' ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-300 bg-white text-zinc-700'">Entity Settings</button>
                </div>
            </header>

            <main class="grid gap-px bg-zinc-300 xl:grid-cols-[240px_minmax(0,1fr)_320px]">
                <aside class="bg-zinc-50 p-4">
                    <section class="border border-zinc-300 bg-white">
                        <div class="flex items-center justify-between border-b border-zinc-300 px-3 py-2">
                            <span class="text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500">Sessions</span>
                            <button @click="appendSession()" type="button" class="text-xs text-zinc-700">+ Add</button>
                        </div>
                        <div class="space-y-2 p-3">
                            <template x-for="session in sessions" :key="session.uid">
                                <button @click="selectedSessionUid = session.uid" type="button" class="block w-full border px-3 py-2 text-left text-base" :class="selectedSessionUid === session.uid ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-300 bg-white'">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="truncate" x-text="session.title"></span>
                                        <span class="text-xs" x-text="sessionFields(session.uid).length"></span>
                                    </div>
                                </button>
                            </template>
                        </div>
                    </section>
                </aside>

                <section x-show="activeTab === 'entity'" class="bg-zinc-100 p-4">
                    <div x-show="modules.length === 0" x-cloak class="mb-4 border border-zinc-300 bg-white px-4 py-3 text-base text-zinc-600">
                        Chưa có module nào. Tạo module trước tại <a href="<?= site_url('desk/create-module') ?>" class="underline">/desk/create-module</a>.
                    </div>

                    <div class="mb-4 flex flex-col gap-3 border border-zinc-300 bg-white p-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500">Workspace</p>
                            <h2 class="text-base font-semibold">Sessions and fields</h2>
                        </div>
                        <div class="flex gap-2">
                            <div class="relative">
                                <button @click="toggleFieldTypeDropdown()" type="button" class="min-w-40 border border-zinc-300 px-3 py-2 text-left text-base hover:bg-zinc-50">
                                    <span x-text="newFieldType"></span>
                                </button>
                                <div x-show="fieldTypeDropdownOpen" x-cloak @click.outside="fieldTypeDropdownOpen = false" class="absolute right-0 top-12 z-20 w-56 border border-zinc-300 bg-white shadow-sm">
                                    <div class="border-b border-zinc-200 p-2">
                                        <input x-model="fieldTypeFilter" type="text" class="w-full border border-zinc-300 px-3 py-2 text-base outline-none focus:border-zinc-500" placeholder="Filter type">
                                    </div>
                                    <div class="max-h-64 overflow-auto p-1">
                                        <template x-for="type in filteredFieldTypes()" :key="type">
                                            <button @click="selectNewFieldType(type)" type="button" class="block w-full px-3 py-2 text-left text-base hover:bg-zinc-50" :class="newFieldType === type ? 'bg-zinc-100' : ''" x-text="type"></button>
                                        </template>
                                        <div x-show="filteredFieldTypes().length === 0" x-cloak class="px-3 py-2 text-base text-zinc-500">No type found.</div>
                                    </div>
                                </div>
                            </div>
                            <button @click="addFieldToSelectedSession()" type="button" class="border border-zinc-300 px-3 py-2 text-base hover:bg-zinc-50">Add Field</button>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <template x-for="session in sessions" :key="session.uid">
                            <section class="border border-zinc-300 bg-white">
                                <div class="flex items-start gap-3 border-b border-zinc-300 px-4 py-3">
                                    <div class="min-w-0 flex-1">
                                        <input x-model="session.title" @focus="selectedSessionUid = session.uid" type="text" class="w-full bg-transparent text-base font-medium outline-none" placeholder="Session title">
                                        <input x-model="session.description" @focus="selectedSessionUid = session.uid" type="text" class="mt-1 w-full bg-transparent text-base text-zinc-500 outline-none" placeholder="Short description">
                                    </div>

                                    <div class="relative">
                                        <button @click="toggleSessionMenu(session.uid)" type="button" class="border border-zinc-300 px-3 py-2 text-base hover:bg-zinc-50">...</button>
                                        <div x-show="sessionMenuUid === session.uid" x-cloak class="absolute right-0 top-11 z-20 min-w-[180px] border border-zinc-300 bg-white shadow-sm">
                                            <button @click="insertSession(session.uid, 'above')" type="button" class="block w-full border-b border-zinc-200 px-3 py-2 text-left text-base hover:bg-zinc-50">Add session above</button>
                                            <button @click="insertSession(session.uid, 'below')" type="button" class="block w-full border-b border-zinc-200 px-3 py-2 text-left text-base hover:bg-zinc-50">Add session below</button>
                                            <button @click="addColumn(session.uid)" type="button" class="block w-full border-b border-zinc-200 px-3 py-2 text-left text-base hover:bg-zinc-50">Add column</button>
                                            <button @click="removeSession(session.uid)" type="button" class="block w-full px-3 py-2 text-left text-base text-zinc-700 hover:bg-zinc-50">Remove session</button>
                                        </div>
                                    </div>
                                </div>

                                <div @dragover.prevent="selectedSessionUid = session.uid" @drop.prevent="handleSessionDrop(session.uid)" class="space-y-2 p-3" :class="selectedSessionUid === session.uid ? 'bg-zinc-50' : ''">
                                    <template x-if="sessionFields(session.uid).length === 0">
                                        <div class="border border-dashed border-zinc-300 px-4 py-8 text-center text-base text-zinc-500">No field in this session.</div>
                                    </template>

                                    <template x-if="sessionFields(session.uid).length > 0">
                                        <div class="grid gap-3" :style="`grid-template-columns: repeat(${session.column_count || 1}, minmax(0, 1fr));`">
                                            <template x-for="columnNumber in sessionColumnNumbers(session)" :key="`${session.uid}_${columnNumber}`">
                                                <div class="space-y-2" @dragover.prevent @drop.prevent="handleColumnDrop(session.uid, columnNumber)">
                                                    <template x-for="field in sessionFieldsByColumn(session.uid, columnNumber)" :key="field.uid">
                                                        <article
                                                            draggable="true"
                                                            @dragstart="startFieldDrag(field.uid)"
                                                            @dragend="resetDrag()"
                                                            @dragover.prevent="setFieldDropTarget(field.uid)"
                                                            @drop.prevent="dropOnField(field.uid)"
                                                            @click="selectField(field.uid)"
                                                            class="border px-3 py-3 cursor-pointer"
                                                            :class="selectedFieldUid === field.uid ? 'border-zinc-900 bg-zinc-50' : (dragState.targetFieldUid === field.uid ? 'border-zinc-600 bg-zinc-50' : 'border-zinc-300 bg-white')"
                                                        >
                                                            <div class="flex items-center justify-between gap-3">
                                                                <div class="min-w-0">
                                                                    <input
                                                                        x-model="field.label"
                                                                        @input="syncFieldname(field)"
                                                                        @click.stop
                                                                        type="text"
                                                                        class="w-full bg-transparent font-medium outline-none"
                                                                        placeholder="Field label"
                                                                    >
                                                                    <div class="mt-2 flex flex-wrap gap-1 text-[11px] text-zinc-600">
                                                                        <span class="border border-zinc-300 px-2 py-0.5 font-mono" x-text="field.fieldname || 'fieldname'"></span>
                                                                        <span x-show="field.in_list_view" x-cloak class="border border-zinc-300 px-2 py-0.5">List</span>
                                                                        <span x-show="field.is_required" x-cloak class="border border-amber-300 bg-amber-50 px-2 py-0.5 text-amber-800">Required</span>
                                                                        <span x-show="field.read_only" x-cloak class="border border-sky-300 bg-sky-50 px-2 py-0.5 text-sky-800">Read only</span>
                                                                        <span x-show="field.hidden" x-cloak class="border border-zinc-400 bg-zinc-100 px-2 py-0.5 text-zinc-700">Hidden</span>
                                                                        <span x-show="hasCustomJson(field.f_custom_jsonb_text)" x-cloak class="border border-emerald-300 bg-emerald-50 px-2 py-0.5 text-emerald-800">JSON</span>
                                                                    </div>
                                                                </div>
                                                                <div class="flex items-center gap-2">
                                                                    <span class="border border-zinc-300 px-2 py-1 text-base text-zinc-600" x-text="field.fieldtype"></span>
                                                                    <button @click.stop="removeField(field.uid)" type="button" class="border border-zinc-300 px-2 py-1 text-base text-zinc-700 hover:bg-zinc-50">Delete</button>
                                                                </div>
                                                            </div>
                                                        </article>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </section>
                        </template>
                    </div>
                </section>

                <section x-show="activeTab === 'settings'" x-cloak class="bg-zinc-100 p-4">
                    <div class="border border-zinc-300 bg-white p-4">
                        <div class="grid gap-4 lg:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500">Entity Name</span>
                                <input x-model="entity.name" @blur="normalizeEntityName()" type="text" class="w-full border border-zinc-300 px-3 py-2 text-base outline-none focus:border-zinc-500">
                            </label>

                            <div class="block">
                                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500">Naming Rule ID</span>
                                <div class="space-y-2">
                                    <select x-model="namingPreset" @change="applyNamingPreset()" class="w-full border border-zinc-300 px-3 py-2 text-base outline-none focus:border-zinc-500">
                                        <template x-for="preset in namingPresets" :key="preset.value">
                                            <option :value="preset.value" x-text="preset.label"></option>
                                        </template>
                                    </select>
                                    <div x-show="namingPreset === 'CUSTOM'" x-cloak>
                                <input x-model="entity.autoname" type="text" class="w-full border border-zinc-300 px-3 py-2 font-mono text-base outline-none focus:border-zinc-500" placeholder="SI-.YYYY.-.#####">
                                    </div>
                                </div>
                            </div>

                            <label class="block">
                                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500">Label</span>
                                <input x-model="entity.label" type="text" class="w-full border border-zinc-300 px-3 py-2 text-base outline-none focus:border-zinc-500">
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500">Module</span>
                                <select x-model="entity.module" class="w-full border border-zinc-300 px-3 py-2 text-base outline-none focus:border-zinc-500">
                                    <option value="">Select module</option>
                                    <template x-for="module in modules" :key="module">
                                        <option :value="module" x-text="module"></option>
                                    </template>
                                </select>
                            </label>

                            <div class="flex items-end">
                                <label class="flex items-center gap-2 text-base">
                                    <input x-model="entity.is_submittable" type="checkbox" class="h-4 w-4 border-zinc-400">
                                    <span>Submittable</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </section>

                <aside class="space-y-4 bg-zinc-50 p-4">
                    <section x-show="activeTab === 'entity'" class="border border-zinc-300 bg-white">
                        <div class="border-b border-zinc-300 px-3 py-2">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500">Field Inspector</p>
                        </div>

                        <template x-if="selectedField">
                            <div class="space-y-4 p-3">
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500">Label</label>
                                    <input x-model="selectedField.label" @input="syncFieldname(selectedField)" type="text" class="w-full border border-zinc-300 px-3 py-2 text-base outline-none focus:border-zinc-500">
                                </div>

                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500">Fieldname</label>
                                    <input x-model="selectedField.fieldname" @input="selectedField.fieldnameTouched = true" type="text" class="w-full border border-zinc-300 px-3 py-2 font-mono text-base outline-none focus:border-zinc-500">
                                </div>

                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500">Field Type</label>
                                    <select x-model="selectedField.fieldtype" @change="applyFieldDefaults(selectedField)" class="w-full border border-zinc-300 px-3 py-2 text-base outline-none focus:border-zinc-500">
                                        <template x-for="type in fieldTypes" :key="type">
                                            <option :value="type" x-text="type"></option>
                                        </template>
                                    </select>
                                </div>

                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500">Length</label>
                                    <input x-model="selectedField.length" type="number" min="1" class="w-full border border-zinc-300 px-3 py-2 text-base outline-none focus:border-zinc-500">
                                </div>

                                <div x-show="requiresOptions(selectedField.fieldtype)" x-cloak>
                                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500">Options</label>
                                    <textarea x-model="selectedField.options" rows="5" class="w-full border border-zinc-300 px-3 py-2 text-base outline-none focus:border-zinc-500" :placeholder="optionsPlaceholder(selectedField.fieldtype)"></textarea>
                                </div>

                                <div x-show="supportsDefaultValue(selectedField.fieldtype)" x-cloak>
                                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500">Default Value</label>
                                    <template x-if="selectedField.fieldtype === 'Check'">
                                        <select x-model="selectedField.default_value" class="w-full border border-zinc-300 px-3 py-2 text-base outline-none focus:border-zinc-500">
                                            <option value="">None</option>
                                            <option value="0">Unchecked</option>
                                            <option value="1">Checked</option>
                                        </select>
                                    </template>
                                    <template x-if="selectedField.fieldtype !== 'Check'">
                                        <input x-model="selectedField.default_value" type="text" class="w-full border border-zinc-300 px-3 py-2 text-base outline-none focus:border-zinc-500" :placeholder="defaultValuePlaceholder(selectedField.fieldtype)">
                                    </template>
                                </div>

                                <div x-show="supportsPlaceholder(selectedField.fieldtype)" x-cloak>
                                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500">Placeholder</label>
                                    <input x-model="selectedField.placeholder" type="text" class="w-full border border-zinc-300 px-3 py-2 text-base outline-none focus:border-zinc-500" placeholder="Enter placeholder">
                                </div>

                                <label class="flex items-center gap-2 text-base">
                                    <input x-model="selectedField.in_list_view" type="checkbox" class="h-4 w-4 border-zinc-400">
                                    <span>In list view</span>
                                </label>

                                <label class="flex items-center gap-2 text-base">
                                    <input x-model="selectedField.is_required" type="checkbox" class="h-4 w-4 border-zinc-400">
                                    <span>Required</span>
                                </label>

                                <label class="flex items-center gap-2 text-base">
                                    <input x-model="selectedField.read_only" type="checkbox" class="h-4 w-4 border-zinc-400">
                                    <span>Read only</span>
                                </label>

                                <label class="flex items-center gap-2 text-base">
                                    <input x-model="selectedField.hidden" type="checkbox" class="h-4 w-4 border-zinc-400">
                                    <span>Hidden</span>
                                </label>

                                <div x-show="selectedFieldSession() && (selectedFieldSession().column_count || 1) > 1" x-cloak>
                                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500">Column</label>
                                    <select x-model="selectedField.column" class="w-full border border-zinc-300 px-3 py-2 text-base outline-none focus:border-zinc-500">
                                        <template x-for="columnNumber in selectedFieldColumnOptions()" :key="columnNumber">
                                            <option :value="columnNumber" x-text="`Column ${columnNumber}`"></option>
                                        </template>
                                    </select>
                                </div>

                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500">f_custom_jsonb</label>
                                    <textarea x-model="selectedField.f_custom_jsonb_text" rows="6" class="w-full border border-zinc-300 bg-white px-3 py-2 font-mono text-sm outline-none focus:border-zinc-500"></textarea>
                                </div>

                                <button @click="removeField(selectedField.uid)" type="button" class="w-full border border-zinc-300 px-3 py-2 text-base text-zinc-700 hover:bg-zinc-50">Delete Field</button>
                            </div>
                        </template>

                        <template x-if="!selectedField">
                            <div class="p-4 text-base text-zinc-500">
                                Chọn một field để chỉnh thuộc tính.
                            </div>
                        </template>
                    </section>

                    <section x-show="activeTab === 'entity'" class="border border-zinc-300 bg-white">
                        <div class="border-b border-zinc-300 px-3 py-2">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500">Advanced JSON</p>
                        </div>
                        <div class="space-y-3 p-3">
                            <div>
                                <label class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500">Entity.s_custom_jsonb</label>
                                <textarea x-model="entityCustomText" rows="5" class="w-full border border-zinc-300 bg-white px-3 py-2 font-mono text-sm outline-none focus:border-zinc-500"></textarea>
                                <p class="mt-1 text-xs text-zinc-500" x-text="jsonSummary(entityCustomText, 'Entity JSON')"></p>
                            </div>
                            <div>
                                <label class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500">sys_entity_custom.custom_meta</label>
                                <textarea x-model="customPatchText" rows="5" class="w-full border border-zinc-300 bg-white px-3 py-2 font-mono text-sm outline-none focus:border-zinc-500"></textarea>
                                <p class="mt-1 text-xs text-zinc-500" x-text="jsonSummary(customPatchText, 'Custom meta')"></p>
                            </div>
                        </div>
                    </section>
                </aside>
            </main>
        </div>

        <div x-show="flash.message" x-cloak class="fixed bottom-4 right-4 border border-zinc-300 bg-white px-4 py-3 text-base shadow-sm" :class="flash.type === 'error' ? 'text-red-700' : 'text-zinc-800'">
            <span x-text="flash.message"></span>
        </div>
    </div>

    <script>
        function entityBuilderApp(boot) {
            return {
                modules: boot.modules || [],
                loadUrl: boot.loadUrl,
                saveUrl: boot.saveUrl,
                entity: {
                    name: '',
                    module: '',
                    label: '',
                    is_submittable: false,
                    autoname: 'HASH',
                },
                namingPreset: 'HASH',
                activeTab: 'entity',
                newFieldType: 'Input',
                fieldTypeFilter: '',
                fieldTypeDropdownOpen: false,
                sessions: [],
                fields: [],
                selectedSessionUid: null,
                selectedFieldUid: null,
                sessionMenuUid: null,
                entityCustomText: '{}',
                customPatchText: '{}',
                entityListUrl: '',
                flash: { type: 'info', message: '' },
                dragState: {
                    kind: null,
                    fieldUid: null,
                    targetFieldUid: null,
                },
                fieldTypes: ['Data', 'Int', 'Float', 'Check', 'Date', 'Text', 'Select', 'Code', 'Table', 'Input'],
                namingPresets: [
                    { value: 'HASH', label: 'HASH' },
                    { value: 'CUSTOM', label: 'Custom series' },
                ],
                lastGeneratedAutoname: 'HASH',
                requestUrl(url) {
                    const resolved = new URL(String(url || ''), window.location.origin);
                    if (resolved.origin === window.location.origin) {
                        return resolved.toString();
                    }

                    return `${window.location.origin}${resolved.pathname}${resolved.search}${resolved.hash}`;
                },
                init() {
                    if (boot.initialEntityName) {
                        this.entity.name = this.slugify(boot.initialEntityName);
                        this.loadEntity(this.entity.name);
                        return;
                    }

                    this.resetBuilder();
                    this.entity.module = this.modules[0] || '';
                },
                get selectedField() {
                    return this.fields.find((field) => field.uid === this.selectedFieldUid) || null;
                },
                resetBuilder() {
                    this.sessions = [this.makeSession('Primary', 'Main fields')];
                    this.selectedSessionUid = this.sessions[0].uid;
                    this.selectedFieldUid = null;
                    this.fields = [];
                    this.entityCustomText = '{}';
                    this.customPatchText = '{}';
                    this.entityListUrl = '';
                    this.entity.autoname = 'HASH';
                    this.namingPreset = 'HASH';
                    this.lastGeneratedAutoname = 'HASH';
                },
                normalizeEntityName() {
                    const previousGenerated = this.lastGeneratedAutoname;
                    this.entity.name = this.slugify(this.entity.name);
                    this.entity.label = this.entity.label || this.titleize(this.entity.name);
                    if (this.namingPreset === 'CUSTOM' && (!this.entity.autoname || this.entity.autoname === previousGenerated)) {
                        this.entity.autoname = this.buildCustomNamingPattern();
                        this.lastGeneratedAutoname = this.entity.autoname;
                    }
                },
                applyNamingPreset() {
                    if (this.namingPreset === 'HASH') {
                        this.entity.autoname = 'HASH';
                        this.lastGeneratedAutoname = 'HASH';
                        return;
                    }

                    this.entity.autoname = this.buildCustomNamingPattern();
                    this.lastGeneratedAutoname = this.entity.autoname;
                },
                buildCustomNamingPattern() {
                    const parts = this.slugify(this.entity.name).split('_').filter(Boolean);
                    const acronym = (parts.map((part) => part.charAt(0)).join('') || 'DOC').toUpperCase();
                    return `${acronym}-.YYYY.-#####`;
                },
                makeSession(title = 'New Session', description = '') {
                    return { uid: this.uuid(), title, description, column_count: 1 };
                },
                appendSession() {
                    const session = this.makeSession(`Session ${this.sessions.length + 1}`, '');
                    this.sessions.push(session);
                    this.selectedSessionUid = session.uid;
                    this.sessionMenuUid = null;
                },
                insertSession(targetUid, direction) {
                    const index = this.sessions.findIndex((session) => session.uid === targetUid);
                    if (index < 0) {
                        return;
                    }

                    const session = this.makeSession(`Session ${this.sessions.length + 1}`, '');
                    const insertIndex = direction === 'above' ? index : index + 1;
                    this.sessions.splice(insertIndex, 0, session);
                    this.selectedSessionUid = session.uid;
                    this.sessionMenuUid = null;
                },
                toggleSessionMenu(sessionUid) {
                    this.sessionMenuUid = this.sessionMenuUid === sessionUid ? null : sessionUid;
                },
                addColumn(sessionUid) {
                    const session = this.sessions.find((item) => item.uid === sessionUid) || null;
                    if (!session) {
                        return;
                    }

                    session.column_count = Math.min(4, Math.max(1, Number(session.column_count || 1) + 1));
                    this.sessionMenuUid = null;
                },
                removeSession(sessionUid) {
                    if (this.sessions.length === 1) {
                        return;
                    }

                    const nextUid = this.sessions.find((session) => session.uid !== sessionUid)?.uid || null;
                    const movingFields = this.fields.filter((field) => field.session_uid === sessionUid);
                    this.sessions = this.sessions.filter((session) => session.uid !== sessionUid);
                    this.selectedSessionUid = nextUid;
                    this.sessionMenuUid = null;

                    movingFields.forEach((field) => {
                        field.session_uid = nextUid;
                    });

                    this.reindexFields();
                },
                sessionFields(sessionUid) {
                    return this.fields.filter((field) => field.session_uid === sessionUid);
                },
                sessionFieldsByColumn(sessionUid, columnNumber) {
                    return this.sessionFields(sessionUid).filter((field) => Number(field.column || 1) === columnNumber);
                },
                sessionColumnNumbers(session) {
                    const count = Math.min(4, Math.max(1, Number(session.column_count || 1)));
                    return Array.from({ length: count }, (_, index) => index + 1);
                },
                selectedFieldSession() {
                    if (!this.selectedField) {
                        return null;
                    }

                    return this.sessions.find((session) => session.uid === this.selectedField.session_uid) || null;
                },
                selectedFieldColumnOptions() {
                    const session = this.selectedFieldSession();
                    if (!session) {
                        return [1];
                    }

                    return this.sessionColumnNumbers(session);
                },
                filteredFieldTypes() {
                    const keyword = String(this.fieldTypeFilter || '').trim().toLowerCase();
                    if (!keyword) {
                        return this.fieldTypes;
                    }

                    return this.fieldTypes.filter((type) => type.toLowerCase().includes(keyword));
                },
                toggleFieldTypeDropdown() {
                    this.fieldTypeDropdownOpen = !this.fieldTypeDropdownOpen;
                    if (!this.fieldTypeDropdownOpen) {
                        this.fieldTypeFilter = '';
                    }
                },
                selectNewFieldType(type) {
                    this.newFieldType = type;
                    this.fieldTypeDropdownOpen = false;
                    this.fieldTypeFilter = '';
                },
                canOpenEntityList() {
                    return this.buildEntityListUrl() !== '';
                },
                buildEntityListUrl() {
                    if (this.entityListUrl) {
                        return this.entityListUrl;
                    }

                    const moduleName = this.slugify(this.entity.module);
                    const entityName = this.slugify(this.entity.name);
                    if (!moduleName || !entityName) {
                        return '';
                    }

                    return `/${moduleName}/${entityName}`;
                },
                goToEntityList() {
                    const url = this.buildEntityListUrl();
                    if (!url) {
                        return;
                    }

                    window.location.href = url;
                },
                addFieldToSelectedSession() {
                    const availableTypes = this.filteredFieldTypes();
                    if (availableTypes.length > 0 && !availableTypes.includes(this.newFieldType)) {
                        this.newFieldType = availableTypes[0];
                    }

                    this.addField(this.selectedSessionUid || this.sessions[0]?.uid || null, this.newFieldType);
                },
                addField(sessionUid, fieldType = 'Input') {
                    const targetSessionUid = sessionUid || this.sessions[0]?.uid || null;
                    if (!targetSessionUid) {
                        return;
                    }

                    const field = this.makeField(fieldType, targetSessionUid);
                    this.fields.push(field);
                    this.selectedSessionUid = targetSessionUid;
                    this.selectedFieldUid = field.uid;
                    this.reindexFields();
                },
                makeField(fieldType, sessionUid) {
                    const index = this.fields.length + 1;
                    const label = `${fieldType} ${index}`;

                    return {
                        uid: this.uuid(),
                        id: null,
                        session_uid: sessionUid,
                        column: 1,
                        fieldname: this.slugify(label),
                        label,
                        fieldtype: fieldType,
                        length: this.defaultLength(fieldType),
                        options: '',
                        default_value: '',
                        placeholder: '',
                        in_list_view: false,
                        is_required: false,
                        read_only: false,
                        hidden: false,
                        idx: index,
                        f_custom_jsonb_text: '{}',
                        fieldnameTouched: false,
                    };
                },
                selectField(fieldUid) {
                    this.selectedFieldUid = fieldUid;
                },
                defaultLength(fieldType) {
                    if (fieldType === 'Input' || fieldType === 'Data' || fieldType === 'Select') {
                        return 255;
                    }

                    return null;
                },
                applyFieldDefaults(field) {
                    field.length = this.defaultLength(field.fieldtype);
                    if (!this.requiresOptions(field.fieldtype)) {
                        field.options = '';
                    }
                    if (!this.supportsDefaultValue(field.fieldtype)) {
                        field.default_value = '';
                    }
                    if (!this.supportsPlaceholder(field.fieldtype)) {
                        field.placeholder = '';
                    }
                },
                requiresOptions(fieldType) {
                    return ['Select', 'Table'].includes(fieldType);
                },
                supportsDefaultValue(fieldType) {
                    return ['Input', 'Data', 'Select', 'Check'].includes(fieldType);
                },
                supportsPlaceholder(fieldType) {
                    return ['Input', 'Data'].includes(fieldType);
                },
                defaultValuePlaceholder(fieldType) {
                    if (fieldType === 'Select') {
                        return 'draft';
                    }

                    if (fieldType === 'Input' || fieldType === 'Data') {
                        return 'Default text';
                    }

                    return '';
                },
                optionsPlaceholder(fieldType) {
                    if (fieldType === 'Select') {
                        return 'draft\nsubmitted\ncancelled';
                    }

                    if (fieldType === 'Table') {
                        return 'child_entity or child_entity:separate';
                    }

                    return '';
                },
                syncFieldname(field) {
                    if (field.fieldnameTouched) {
                        return;
                    }

                    field.fieldname = this.slugify(field.label);
                },
                startFieldDrag(fieldUid) {
                    this.dragState.kind = 'field';
                    this.dragState.fieldUid = fieldUid;
                    this.dragState.targetFieldUid = null;
                },
                resetDrag() {
                    this.dragState.kind = null;
                    this.dragState.fieldUid = null;
                    this.dragState.targetFieldUid = null;
                },
                handleSessionDrop(sessionUid) {
                    if (this.dragState.kind === 'field' && this.dragState.fieldUid) {
                        const field = this.fields.find((item) => item.uid === this.dragState.fieldUid);
                        if (field) {
                            field.session_uid = sessionUid;
                            field.column = 1;
                            this.moveFieldToSessionEnd(field.uid, sessionUid);
                        }
                    }

                    this.resetDrag();
                },
                handleColumnDrop(sessionUid, columnNumber) {
                    if (this.dragState.kind !== 'field' || !this.dragState.fieldUid) {
                        return;
                    }

                    const field = this.fields.find((item) => item.uid === this.dragState.fieldUid);
                    if (!field) {
                        this.resetDrag();
                        return;
                    }

                    field.session_uid = sessionUid;
                    field.column = columnNumber;
                    this.moveFieldToColumnEnd(field.uid, sessionUid, columnNumber);
                    this.resetDrag();
                },
                setFieldDropTarget(fieldUid) {
                    this.dragState.targetFieldUid = fieldUid;
                },
                dropOnField(targetUid) {
                    if (this.dragState.kind === 'field' && this.dragState.fieldUid) {
                        this.reorderField(this.dragState.fieldUid, targetUid);
                    }

                    this.resetDrag();
                },
                reorderField(sourceUid, targetUid) {
                    if (sourceUid === targetUid) {
                        return;
                    }

                    const sourceIndex = this.fields.findIndex((field) => field.uid === sourceUid);
                    const targetIndex = this.fields.findIndex((field) => field.uid === targetUid);

                    if (sourceIndex < 0 || targetIndex < 0) {
                        return;
                    }

                    const [field] = this.fields.splice(sourceIndex, 1);
                    field.session_uid = this.fields[targetIndex]?.session_uid || field.session_uid;
                    field.column = Number(this.fields[targetIndex]?.column || 1);
                    this.fields.splice(targetIndex, 0, field);
                    this.selectedFieldUid = field.uid;
                    this.reindexFields();
                },
                moveFieldToSessionEnd(fieldUid, sessionUid) {
                    const movingIndex = this.fields.findIndex((field) => field.uid === fieldUid);
                    if (movingIndex < 0) {
                        return;
                    }

                    const [movingField] = this.fields.splice(movingIndex, 1);
                    const lastIndex = this.lastIndexInSession(sessionUid);
                    if (lastIndex === -1) {
                        this.fields.push(movingField);
                    } else {
                        this.fields.splice(lastIndex + 1, 0, movingField);
                    }

                    this.selectedFieldUid = movingField.uid;
                    this.reindexFields();
                },
                moveFieldToColumnEnd(fieldUid, sessionUid, columnNumber) {
                    const movingIndex = this.fields.findIndex((field) => field.uid === fieldUid);
                    if (movingIndex < 0) {
                        return;
                    }

                    const [movingField] = this.fields.splice(movingIndex, 1);
                    const lastIndex = this.lastIndexInColumn(sessionUid, columnNumber);
                    if (lastIndex === -1) {
                        const sessionLastIndex = this.lastIndexInSession(sessionUid);
                        if (sessionLastIndex === -1) {
                            this.fields.push(movingField);
                        } else {
                            this.fields.splice(sessionLastIndex + 1, 0, movingField);
                        }
                    } else {
                        this.fields.splice(lastIndex + 1, 0, movingField);
                    }

                    this.selectedFieldUid = movingField.uid;
                    this.reindexFields();
                },
                lastIndexInSession(sessionUid) {
                    let last = -1;
                    this.fields.forEach((field, index) => {
                        if (field.session_uid === sessionUid) {
                            last = index;
                        }
                    });
                    return last;
                },
                lastIndexInColumn(sessionUid, columnNumber) {
                    let last = -1;
                    this.fields.forEach((field, index) => {
                        if (field.session_uid === sessionUid && Number(field.column || 1) === columnNumber) {
                            last = index;
                        }
                    });
                    return last;
                },
                removeField(fieldUid) {
                    this.fields = this.fields.filter((field) => field.uid !== fieldUid);
                    if (this.selectedFieldUid === fieldUid) {
                        this.selectedFieldUid = this.fields[0]?.uid || null;
                    }
                    this.reindexFields();
                },
                reindexFields() {
                    this.fields = this.fields.map((field, index) => {
                        const session = this.sessions.find((item) => item.uid === field.session_uid) || null;
                        const maxColumn = Math.min(4, Math.max(1, Number(session?.column_count || 1)));

                        return {
                            ...field,
                            column: Math.min(maxColumn, Math.max(1, Number(field.column || 1))),
                            idx: index + 1,
                        };
                    });
                },
                async loadEntity(entityName) {
                    const normalized = this.slugify(entityName);
                    if (!normalized) {
                        this.toast('error', 'Entity name is required to load metadata.');
                        return;
                    }

                    try {
                        const response = await fetch(this.requestUrl(`${this.loadUrl}/${encodeURIComponent(normalized)}`), {
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });
                        const result = await response.json();

                        if (!response.ok || result.status !== 'ok') {
                            throw new Error(result.message || 'Load failed.');
                        }

                        const payload = result.data;
                        const entityCustom = this.ensureJsonObject(payload.entity.s_custom_jsonb);
                        const sessionDefs = this.extractSessions(entityCustom);

                        this.entity = {
                            name: payload.entity.name || '',
                            module: payload.entity.module || '',
                            label: payload.entity.label || this.titleize(payload.entity.name || ''),
                            is_submittable: !!payload.entity.is_submittable,
                            autoname: payload.entity.autoname || 'HASH',
                        };
                        this.namingPreset = this.entity.autoname === 'HASH' ? 'HASH' : 'CUSTOM';
                        this.lastGeneratedAutoname = this.namingPreset === 'CUSTOM' ? this.buildCustomNamingPattern() : 'HASH';
                        this.entityListUrl = this.buildEntityListUrl();
                        this.sessions = sessionDefs.length ? sessionDefs : [this.makeSession('Primary', 'Main fields')];
                        this.selectedSessionUid = this.sessions[0].uid;
                        this.entityCustomText = this.prettyJson(entityCustom);
                        this.customPatchText = this.prettyJson(this.ensureJsonObject(payload.custom_patch));
                        this.fields = (payload.fields || []).map((field, index) => {
                            const parsedCustom = this.ensureJsonObject(field.f_custom_jsonb);
                            const sessionUid = parsedCustom.session_uid && this.sessions.find((session) => session.uid === parsedCustom.session_uid)
                                ? parsedCustom.session_uid
                                : this.sessions[0].uid;

                            return {
                                uid: this.uuid(),
                                id: field.id || null,
                                session_uid: sessionUid,
                                column: Number(parsedCustom.column || 1),
                                fieldname: field.fieldname || '',
                                label: field.label || '',
                                fieldtype: field.fieldtype || 'Input',
                                length: field.length ?? this.defaultLength(field.fieldtype || 'Input'),
                                options: field.options || '',
                                default_value: parsedCustom.default_value ?? '',
                                placeholder: parsedCustom.placeholder ?? '',
                                in_list_view: !!parsedCustom.in_list_view,
                                is_required: !!field.is_required,
                                read_only: !!field.read_only,
                                hidden: !!field.hidden,
                                idx: index + 1,
                                f_custom_jsonb_text: this.prettyJson(parsedCustom),
                                fieldnameTouched: true,
                            };
                        });
                        this.selectedFieldUid = this.fields[0]?.uid || null;

                        if (!this.modules.includes(this.entity.module)) {
                            this.modules.push(this.entity.module);
                            this.modules.sort();
                        }

                        history.replaceState({}, '', `?entity=${encodeURIComponent(normalized)}`);
                        this.toast('info', `Loaded ${normalized}.`);
                    } catch (error) {
                        if (normalized === this.slugify(this.entity.name || '')) {
                            this.resetBuilder();
                            this.entity.name = normalized;
                        }
                        this.toast('error', error.message || 'Unable to load entity.');
                    }
                },
                extractSessions(entityCustom) {
                    const layout = entityCustom && entityCustom.layout ? entityCustom.layout : {};
                    const rawSessions = Array.isArray(layout.sessions) ? layout.sessions : [];

                    return rawSessions
                        .map((session) => ({
                            uid: session.uid || this.uuid(),
                            title: session.title || 'Session',
                            description: session.description || '',
                            column_count: Math.min(4, Math.max(1, Number(session.column_count || 1))),
                        }))
                        .filter((session) => session.uid);
                },
                async save() {
                    try {
                        const payload = this.buildSavePayload();
                        const response = await fetch(this.requestUrl(this.saveUrl), {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify(payload),
                        });
                        const result = await response.json();

                        if (!response.ok || result.status !== 'ok') {
                            throw new Error(result.message || 'Save failed.');
                        }

                        const entityName = payload.entity.name;
                        if (!this.modules.includes(payload.entity.module)) {
                            this.modules.push(payload.entity.module);
                            this.modules.sort();
                        }

                        this.entityListUrl = result.data?.artifacts?.list_url || this.buildEntityListUrl();
                        history.replaceState({}, '', `?entity=${encodeURIComponent(entityName)}`);
                        this.toast('info', `Saved ${entityName}.`);
                    } catch (error) {
                        this.toast('error', error.message || 'Unable to save entity.');
                    }
                },
                buildSavePayload() {
                    const entityName = this.slugify(this.entity.name);
                    if (!entityName) {
                        throw new Error('Entity name is required.');
                    }

                    const moduleName = this.slugify(this.entity.module);
                    if (!moduleName) {
                        throw new Error('Module is required.');
                    }

                    if (this.sessions.length === 0) {
                        throw new Error('At least one session is required.');
                    }

                    const entityCustom = this.parseJsonObject(this.entityCustomText, 'Entity advanced JSON is invalid.');
                    const customPatch = this.parseJsonObject(this.customPatchText, 'Custom patch JSON is invalid.');
                    entityCustom.layout = entityCustom.layout && typeof entityCustom.layout === 'object' && !Array.isArray(entityCustom.layout)
                        ? entityCustom.layout
                        : {};
                    entityCustom.layout.sessions = this.sessions.map((session) => ({
                        uid: session.uid,
                        title: session.title || 'Session',
                        description: session.description || '',
                        column_count: Math.min(4, Math.max(1, Number(session.column_count || 1))),
                    }));

                    const fields = this.fields.map((field, index) => {
                        const custom = this.parseJsonObject(field.f_custom_jsonb_text, `Field JSON invalid: ${field.label || field.fieldname}`);
                        custom.session_uid = field.session_uid;
                        custom.column = Math.max(1, Number(field.column || 1));
                        if (this.supportsDefaultValue(field.fieldtype)) {
                            custom.default_value = field.default_value ?? '';
                        } else {
                            delete custom.default_value;
                        }
                        if (this.supportsPlaceholder(field.fieldtype)) {
                            custom.placeholder = field.placeholder ?? '';
                        } else {
                            delete custom.placeholder;
                        }
                        custom.in_list_view = !!field.in_list_view;

                        return {
                            id: field.id,
                            fieldname: this.slugify(field.fieldname || field.label),
                            label: field.label || this.titleize(field.fieldname),
                            fieldtype: field.fieldtype,
                            length: field.length === '' ? null : field.length,
                            options: field.options || '',
                            is_required: !!field.is_required,
                            read_only: !!field.read_only,
                            hidden: !!field.hidden,
                            idx: index + 1,
                            f_custom_jsonb: custom,
                        };
                    });

                    const seen = new Set();
                    fields.forEach((field) => {
                        if (!field.fieldname) {
                            throw new Error('Every field must have a valid fieldname.');
                        }

                        if (seen.has(field.fieldname)) {
                            throw new Error(`Duplicate fieldname: ${field.fieldname}`);
                        }

                        if (this.requiresOptions(field.fieldtype) && !String(field.options || '').trim()) {
                            throw new Error(`Field ${field.fieldname} requires options.`);
                        }

                        seen.add(field.fieldname);
                    });

                    this.entity.name = entityName;
                    this.entity.module = moduleName;
                    this.entity.label = this.entity.label || this.titleize(entityName);
                    this.entityCustomText = this.prettyJson(entityCustom);

                    return {
                        entity: {
                            name: entityName,
                            module: moduleName,
                            label: this.entity.label,
                            is_submittable: !!this.entity.is_submittable,
                            autoname: this.entity.autoname || 'HASH',
                            s_custom_jsonb: entityCustom,
                        },
                        fields,
                        custom_patch: customPatch,
                    };
                },
                parseJsonObject(text, errorMessage) {
                    const raw = String(text || '').trim();
                    if (!raw) {
                        return {};
                    }

                    let parsed;
                    try {
                        parsed = JSON.parse(raw);
                    } catch (error) {
                        throw new Error(errorMessage);
                    }

                    if (Array.isArray(parsed)) {
                        return {};
                    }

                    if (!parsed || typeof parsed !== 'object') {
                        throw new Error(errorMessage);
                    }

                    return parsed;
                },
                ensureJsonObject(value) {
                    if (!value || Array.isArray(value) || typeof value !== 'object') {
                        return {};
                    }

                    return value;
                },
                prettyJson(value) {
                    return JSON.stringify(value || {}, null, 2);
                },
                hasCustomJson(text) {
                    try {
                        const parsed = this.parseJsonObject(text, 'Field JSON is invalid.');
                        return Object.keys(parsed).length > 0;
                    } catch (error) {
                        return false;
                    }
                },
                jsonSummary(text, label) {
                    try {
                        const parsed = this.parseJsonObject(text, `${label} is invalid.`);
                        const keys = Object.keys(parsed);
                        if (keys.length === 0) {
                            return `${label}: empty object`;
                        }

                        return `${label}: ${keys.length} key${keys.length > 1 ? 's' : ''}`;
                    } catch (error) {
                        return `${label}: invalid JSON`;
                    }
                },
                slugify(value) {
                    return String(value || '')
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .toLowerCase()
                        .trim()
                        .replace(/[^a-z0-9]+/g, '_')
                        .replace(/^_+|_+$/g, '');
                },
                titleize(value) {
                    return String(value || '')
                        .replace(/_/g, ' ')
                        .replace(/\b\w/g, (match) => match.toUpperCase());
                },
                uuid() {
                    return crypto.randomUUID ? crypto.randomUUID() : `field_${Date.now()}_${Math.random().toString(16).slice(2)}`;
                },
                toast(type, message) {
                    this.flash = { type, message };
                    window.clearTimeout(this.flashTimer);
                    this.flashTimer = window.setTimeout(() => {
                        this.flash.message = '';
                    }, 3200);
                },
            };
        }
    </script>
</body>
</html>
