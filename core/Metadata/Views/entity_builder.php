<?php

/** @var array<int, string> $modules */
/** @var array<int, array{name:string,label:string,module:string}> $entityOptions */
/** @var array<string, array<int, array{fieldname:string,label:string,fieldtype:string}>> $entityFieldCatalog */
/** @var string $initialEntityName */
$deleteModalBody = static function (): string {
    ob_start();
    ?>
    <p class="text-sm text-zinc-700">
        Bạn có chắc muốn xóa entity <span class="font-semibold" x-text="entity.name || 'this entity'"></span> không?
    </p>
    <p class="text-sm text-zinc-600">Nhập mật khẩu hiện tại để xác nhận thao tác xóa.</p>
    <label class="block">
        <span class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500">Password</span>
        <input x-model="deletePassword" @keydown.enter.prevent="destroyEntity()" type="password" class="w-full border border-zinc-300 px-3 py-2 text-base outline-none focus:border-zinc-500">
    </label>
    <?php
    return (string) ob_get_clean();
};

$deleteModalFooter = static function (): string {
    ob_start();
    ?>
    <button @click="closeDeleteModal()" type="button" class="border border-zinc-300 px-4 py-2 text-base text-zinc-700 hover:bg-zinc-50">Cancel</button>
    <button @click="destroyEntity()" type="button" class="border border-red-300 bg-red-50 px-4 py-2 text-base font-medium text-red-800 hover:bg-red-100">
        Confirm Delete
    </button>
    <?php
    return (string) ob_get_clean();
};

$__lang = \Volt\Core\Config\Lang\LangService::load();
?>
<!doctype html>
<html lang="<?= esc($__lang['code'] ?? 'en') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Volt Entity Builder</title>
    <link rel="stylesheet" href="<?= base_url('assets/vendor/tailwindcss/tailwind.min.css') ?>">
    <script defer src="<?= base_url('assets/vendor/alpinejs/alpine.min.js') ?>"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-screen bg-zinc-100 text-base text-zinc-900">
    <div
        x-data="entityBuilderApp(<?= esc(json_encode([
            'modules' => $modules,
            'entityOptions' => $entityOptions,
            'entityFieldCatalog' => $entityFieldCatalog,
            'initialEntityName' => $initialEntityName,
            'loadUrl' => site_url('api/entity-builder/load'),
            'saveUrl' => site_url('api/entity-builder/save'),
            'deleteUrl' => site_url('api/entity-builder/delete'),
            'deskUrl' => site_url('desk'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>)"
        x-init="init()"
        @keydown.window.ctrl.s.prevent="save()"
        @keydown.window.meta.s.prevent="save()"
        class="mx-auto max-w-[1720px] p-4 lg:p-6"
    >
        <div class="border border-zinc-300 bg-white">
            <header class="border-b border-zinc-300 p-4">
                <div class="flex items-center justify-between gap-2">
                    <a :href="deskUrl" class="border border-zinc-300 px-4 py-2 text-base hover:bg-zinc-50">Back to Desk</a>
                    <div class="flex items-center gap-2">
                    <button x-show="canOpenEntityList()" x-cloak @click="goToEntityList()" type="button" class="border border-zinc-300 px-4 py-2 text-base hover:bg-zinc-50">
                        <span x-text="`Go to ${entity.label || titleize(entity.name || 'Entity')}`"></span>
                    </button>
                    <button @click="save()" type="button" class="border border-zinc-900 bg-zinc-900 px-4 py-2 text-base text-white hover:bg-zinc-700">Save</button>
                    </div>
                </div>

                <div class="mt-4 flex gap-2">
                    <button @click="activeTab = 'settings'" type="button" class="border px-3 py-2 text-base" :class="activeTab === 'settings' ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-300 bg-white text-zinc-700'">Entity Settings</button>
                    <button @click="activeTab = 'entity'" type="button" class="border px-3 py-2 text-base" :class="activeTab === 'entity' ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-300 bg-white text-zinc-700'">Entity</button>
                    <button @click="activeTab = 'workflow'" type="button" class="border px-3 py-2 text-base" :class="activeTab === 'workflow' ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-300 bg-white text-zinc-700'">Workflow</button>
                </div>
            </header>

            <main class="grid gap-px bg-zinc-300 xl:grid-cols-[minmax(0,1fr)_320px]">
                <section x-show="activeTab === 'entity'" class="bg-zinc-100 p-4">
                    <div x-show="modules.length === 0" x-cloak class="mb-4 border border-zinc-300 bg-white px-4 py-3 text-base text-zinc-600">
                        Chưa có module nào. Tạo module trước tại <a href="<?= site_url('desk/create-module') ?>" class="underline">/desk/create-module</a>.
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
                                        <div class="border border-dashed border-zinc-300 px-4 py-8 text-center text-base text-zinc-500">
                                            <p>No field in this session.</p>
                                            <div class="mt-4">
                                                <div class="relative inline-block text-left" @click.outside="closeFieldTypeDropdown()">
                                                    <button @click="toggleFieldTypeDropdown(`session:${session.uid}`)" type="button" class="border border-zinc-300 bg-white px-3 py-2 text-base hover:bg-zinc-50">Add Field</button>
                                                    <div x-show="fieldTypeDropdownOpen && fieldTypeAnchor === `session:${session.uid}`" x-cloak class="absolute left-1/2 top-12 z-20 w-56 -translate-x-1/2 border border-zinc-300 bg-white shadow-sm">
                                                        <div class="border-b border-zinc-200 p-2">
                                                            <input x-model="fieldTypeFilter" type="text" class="w-full border border-zinc-300 px-3 py-2 text-base outline-none focus:border-zinc-500" placeholder="Filter type">
                                                        </div>
                                                        <div class="max-h-64 overflow-auto p-1">
                                                            <template x-for="type in filteredFieldTypes()" :key="type">
                                                                <button @click="addFieldFromAnchor(type, `session:${session.uid}`)" type="button" class="block w-full px-3 py-2 text-left text-base hover:bg-zinc-50" x-text="type"></button>
                                                            </template>
                                                            <div x-show="filteredFieldTypes().length === 0" x-cloak class="px-3 py-2 text-base text-zinc-500">No type found.</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <template x-if="sessionFields(session.uid).length > 0">
                                        <div class="grid gap-3" :style="`grid-template-columns: repeat(${session.column_count || 1}, minmax(0, 1fr));`">
                                            <template x-for="columnNumber in sessionColumnNumbers(session)" :key="`${session.uid}_${columnNumber}`">
                                                <div class="space-y-2" @dragover.prevent @drop.prevent="handleColumnDrop(session.uid, columnNumber)">
                                                    <template x-for="field in sessionFieldsByColumn(session.uid, columnNumber)" :key="field.uid">
                                                        <div class="space-y-2">
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

                                                            <template x-if="selectedFieldUid === field.uid">
                                                                <div class="flex justify-center">
                                                                    <div class="relative inline-block text-left" @click.outside="closeFieldTypeDropdown()">
                                                                        <button @click="toggleFieldTypeDropdown(`field:${field.uid}`)" type="button" class="border border-zinc-300 bg-white px-3 py-2 text-base hover:bg-zinc-50">Add Field</button>
                                                                        <div x-show="fieldTypeDropdownOpen && fieldTypeAnchor === `field:${field.uid}`" x-cloak class="absolute left-1/2 top-12 z-20 w-56 -translate-x-1/2 border border-zinc-300 bg-white shadow-sm">
                                                                            <div class="border-b border-zinc-200 p-2">
                                                                                <input x-model="fieldTypeFilter" type="text" class="w-full border border-zinc-300 px-3 py-2 text-base outline-none focus:border-zinc-500" placeholder="Filter type">
                                                                            </div>
                                                                            <div class="max-h-64 overflow-auto p-1">
                                                                                <template x-for="type in filteredFieldTypes()" :key="type">
                                                                                    <button @click="addFieldFromAnchor(type, `field:${field.uid}`)" type="button" class="block w-full px-3 py-2 text-left text-base hover:bg-zinc-50" x-text="type"></button>
                                                                                </template>
                                                                                <div x-show="filteredFieldTypes().length === 0" x-cloak class="px-3 py-2 text-base text-zinc-500">No type found.</div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </template>
                                                        </div>
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

                            <div class="flex items-end gap-4">
                                <label class="flex items-center gap-2 text-base">
                                    <input x-model="entity.is_submittable" type="checkbox" class="h-4 w-4 border-zinc-400">
                                    <span>Submittable</span>
                                </label>
                                <label class="flex items-center gap-2 text-base">
                                    <input x-model="entity.istable" type="checkbox" class="h-4 w-4 border-zinc-400">
                                    <span>Child Table (istable)</span>
                                </label>
                            </div>
                        </div>

                        <div x-show="canDeleteEntity()" x-cloak class="mt-6 border-t border-zinc-200 pt-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-red-600">Danger Zone</p>
                                    <p class="mt-1 text-sm text-zinc-600">Xóa entity sẽ xóa metadata, bảng dữ liệu và artifact đã sinh trong module.</p>
                                </div>
                                <button @click="openDeleteModal()" type="button" class="border border-red-300 bg-red-50 px-4 py-2 text-base font-medium text-red-800 hover:bg-red-100">
                                    Delete Entity
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <section x-show="activeTab === 'workflow'" x-cloak class="bg-zinc-100 p-4">
                    <div x-show="!entity.is_submittable" class="border border-zinc-300 bg-white p-8 text-center text-zinc-500">
                        Enable <strong>Submittable</strong> in Entity Settings to configure workflow.
                    </div>
                    <template x-if="entity.is_submittable">
                        <div class="space-y-4">
                            <div class="flex items-center gap-3 border border-zinc-300 bg-white p-4">
                                <div class="flex-1">
                                    <label class="block">
                                        <span class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500" x-text="t('workflow_label')"></span>
                                        <input x-model="workflow.label" type="text" class="w-full border border-zinc-300 px-3 py-2 text-base outline-none focus:border-zinc-500" :placeholder="t('workflow_label')">
                                    </label>
                                </div>
                            </div>

                            <div class="border border-zinc-300 bg-white p-4">
                                <div class="mb-3 flex items-center justify-between">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500" x-text="t('states')"></p>
                                    <div class="flex gap-2">
                                        <button @click="addDefaultWorkflowStates()" type="button" class="border border-zinc-300 px-3 py-1 text-xs hover:bg-zinc-50" x-text="t('add_default')"></button>
                                        <button @click="addWorkflowState()" type="button" class="border border-zinc-300 px-3 py-1 text-sm hover:bg-zinc-50" x-text="t('add_state')"></button>
                                    </div>
                                </div>

                                <template x-if="workflow.states.length === 0">
                                    <p class="py-4 text-center text-sm text-zinc-500" x-text="t('no_states')"></p>
                                </template>

                                <template x-if="workflow.states.length > 0">
                                    <div class="space-y-3">
                                        <template x-for="(state, idx) in workflow.states" :key="state.uid">
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <div @click="editedState = (editedState?.uid === state.uid) ? null : state; editedTransition = null" class="relative flex flex-1 cursor-pointer items-center gap-3 rounded-lg border-2 p-3 transition-shadow hover:shadow-md" :class="editedState?.uid === state.uid ? 'border-zinc-600 shadow-md' : 'border-zinc-200'" :style="{ borderLeftColor: state.color || '#6b7280', borderLeftWidth: '4px' }">
                                                        <div class="flex-1">
                                                            <div class="flex items-center gap-2">
                                                                <input x-model="state.name" type="text" class="min-w-0 flex-1 border-0 border-b border-transparent bg-transparent px-0 py-0.5 text-sm font-medium outline-none hover:border-zinc-300 focus:border-zinc-500" :placeholder="t('state_name')" @click.stop>
                                                                <span class="rounded bg-zinc-100 px-1.5 py-0.5 text-[10px] font-mono tabular-nums text-zinc-600" x-text="'doc:' + state.docstatus"></span>
                                                                <span x-show="state.allow_edit" class="rounded bg-sky-100 px-1.5 py-0.5 text-[10px] text-sky-700" x-text="t('allow_edit')"></span>
                                                                <span x-show="state.is_final" class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] text-amber-700" x-text="t('is_final')"></span>
                                                            </div>
                                                        </div>
                                                        <button @click.stop="removeWorkflowState(state.uid)" type="button" class="shrink-0 rounded p-1 text-zinc-400 hover:bg-red-50 hover:text-red-600" :title="t('delete_state')">
                                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                        </button>
                                                    </div>
                                                </div>

                                                <div x-show="editedState?.uid === state.uid" x-cloak class="mb-2 mt-1 ml-2 grid grid-cols-6 gap-3 rounded border border-zinc-200 bg-zinc-50 p-3 text-xs">
                                                    <div>
                                                        <span class="mb-0.5 block text-[10px] uppercase text-zinc-500" x-text="t('docstatus')"></span>
                                                        <select x-model="state.docstatus" class="w-full border border-zinc-300 px-2 py-1 outline-none focus:border-zinc-500">
                                                            <option value="0">0</option>
                                                            <option value="1">1</option>
                                                            <option value="2">2</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <span class="mb-0.5 block text-[10px] uppercase text-zinc-500" x-text="t('color')"></span>
                                                        <select x-model="state.color" class="w-full border border-zinc-300 px-2 py-1 outline-none focus:border-zinc-500">
                                                            <option value="#6b7280">Gray</option>
                                                            <option value="#3b82f6">Blue</option>
                                                            <option value="#22c55e">Green</option>
                                                            <option value="#f59e0b">Amber</option>
                                                            <option value="#ef4444">Red</option>
                                                            <option value="#a855f7">Purple</option>
                                                        </select>
                                                    </div>
                                                    <div class="flex items-end gap-3">
                                                        <label class="flex items-center gap-1">
                                                            <input x-model="state.allow_edit" type="checkbox" class="h-3.5 w-3.5 border-zinc-400">
                                                            <span x-text="t('allow_edit')"></span>
                                                        </label>
                                                        <label class="flex items-center gap-1">
                                                            <input x-model="state.is_final" type="checkbox" class="h-3.5 w-3.5 border-zinc-400">
                                                            <span x-text="t('is_final')"></span>
                                                        </label>
                                                    </div>
                                                </div>

                                                <div x-show="!editedState || editedState?.uid !== state.uid" class="ml-2 mt-1 space-y-1">
                                                    <template x-for="(trans, tidx) in workflow.transitions.filter(t => t.from_state === state.name)" :key="trans.uid || tidx">
                                                        <div @click="editedTransition = (editedTransition?.uid === trans.uid) ? null : trans; editedState = null" class="flex cursor-pointer items-center gap-2 rounded border border-dashed border-zinc-300 px-3 py-1.5 text-xs transition-colors hover:border-zinc-500" :class="editedTransition?.uid === trans.uid ? 'border-zinc-600 bg-zinc-100' : ''">
                                                            <span class="text-zinc-400">↳</span>
                                                            <span class="font-medium text-amber-700" x-text="trans.action || '?'"></span>
                                                            <span class="text-zinc-400">→</span>
                                                            <span class="font-medium text-zinc-700" x-text="trans.to_state"></span>
                                                            <span x-show="trans.label" class="text-zinc-400" x-text="'(' + trans.label + ')'"></span>
                                                            <button @click.stop="removeWorkflowTransition(trans.uid, tidx)" type="button" class="ml-auto rounded p-0.5 text-zinc-400 hover:text-red-600" :title="t('delete_transition')">
                                                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                            </button>
                                                        </div>
                                                    </template>
                                                    <div x-show="editedTransition" x-cloak x-transition class="ml-4 grid grid-cols-5 gap-2 rounded border border-zinc-200 bg-white p-2 text-xs">
                                                        <div>
                                                            <span class="mb-0.5 block text-[10px] uppercase text-zinc-500" x-text="t('from_state')"></span>
                                                            <span class="block px-2 py-1 text-xs font-medium" x-text="editedTransition?.from_state"></span>
                                                        </div>
                                                        <div>
                                                            <span class="mb-0.5 block text-[10px] uppercase text-zinc-500" x-text="t('action')"></span>
                                                            <select x-model="editedTransition.action" class="w-full border border-zinc-300 px-2 py-1 outline-none focus:border-zinc-500">
                                                                <option value="" x-text="t('select_action')"></option>
                                                                <template x-for="action in availableActions" :key="action.name">
                                                                    <option :value="action.name" x-text="action.label || action.name"></option>
                                                                </template>
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <span class="mb-0.5 block text-[10px] uppercase text-zinc-500" x-text="t('to_state')"></span>
                                                            <select x-model="editedTransition.to_state" class="w-full border border-zinc-300 px-2 py-1 outline-none focus:border-zinc-500">
                                                                <option value="" x-text="t('select_state')"></option>
                                                                <template x-for="st in workflow.states" :key="st.name">
                                                                    <option :value="st.name" x-text="st.name || st.label || st.uid"></option>
                                                                </template>
                                                            </select>
                                                        </div>
                                                        <div class="sm:col-span-2">
                                                            <span class="mb-0.5 block text-[10px] uppercase text-zinc-500" x-text="t('label_opt')"></span>
                                                            <input x-model="editedTransition.label" type="text" class="w-full border border-zinc-300 px-2 py-1 outline-none focus:border-zinc-500" :placeholder="t('label_opt')">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>

                            <div class="border border-zinc-300 bg-white p-4">
                                <div class="mb-3 flex items-center justify-between">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500" x-text="t('transitions')"></p>
                                    <button @click="addWorkflowTransition()" type="button" class="border border-zinc-300 px-3 py-1 text-sm hover:bg-zinc-50" x-text="t('add_transition')"></button>
                                </div>
                                <template x-if="workflow.transitions.length === 0">
                                    <p class="py-2 text-center text-sm text-zinc-500" x-text="t('no_transitions')"></p>
                                </template>
                                <template x-if="workflow.transitions.length > 0">
                                    <div class="flex flex-wrap gap-2">
                                        <template x-for="(trans, tidx) in workflow.transitions" :key="trans.uid || tidx">
                                            <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs" :class="editedTransition?.uid === trans.uid ? 'border-zinc-600 bg-zinc-100' : 'border-zinc-300'">
                                                <span class="font-medium" x-text="trans.from_state"></span>
                                                <span class="text-amber-600" x-text="'→'"></span>
                                                <span class="font-medium" x-text="trans.action || '?'"></span>
                                                <span class="text-amber-600" x-text="'→'"></span>
                                                <span class="font-medium" x-text="trans.to_state"></span>
                                                <button @click="removeWorkflowTransition(trans.uid, tidx)" type="button" class="ml-0.5 rounded-full p-0.5 text-zinc-400 hover:text-red-600">
                                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </span>
                                        </template>
                                    </div>
                                </template>
                            </div>

                            <div class="flex items-center gap-2 border border-zinc-300 bg-white px-4 py-3 text-sm text-zinc-600">
                                <span class="h-2 w-2 rounded-full" :class="workflow.states.length > 0 ? 'bg-green-500' : 'bg-zinc-300'"></span>
                                <span x-text="workflow.states.length > 0 ? (t('workflow_active') + ': ' + (workflow.label || t('states'))) : t('no_states')"></span>
                            </div>
                        </div>
                    </template>
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
                                    <select x-model="selectedField.fieldtype" @change="selectedField.fieldtype = normalizeFieldType(selectedField.fieldtype); applyFieldDefaults(selectedField)" class="w-full border border-zinc-300 px-3 py-2 text-base outline-none focus:border-zinc-500">
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
                                    <template x-if="selectedField.fieldtype === 'Link' || selectedField.fieldtype === 'Table' || selectedField.fieldtype === 'Child Table (JSONB)'">
                                        <div class="relative" @click.outside="closeLinkEntityPicker()">
                                            <input
                                                :value="selectedField.fieldtype === 'Table' ? stripSeparateSuffix(selectedField.options) : selectedField.options"
                                                @focus="openLinkEntityPicker()"
                                                @click="openLinkEntityPicker()"
                                                @input="syncLinkEntityFilter(); handleEntityInput($event)"
                                                type="text"
                                                class="w-full border border-zinc-300 px-3 py-2 text-base outline-none focus:border-zinc-500"
                                                :placeholder="selectedField.fieldtype === 'Table' || selectedField.fieldtype === 'Child Table (JSONB)' ? 'Select child entity' : 'Select linked entity'"
                                            >
                                            <div x-show="linkEntityDropdownOpen" x-cloak class="absolute left-0 right-0 top-12 z-20 border border-zinc-300 bg-white shadow-sm">
                                                <div class="max-h-64 overflow-auto p-1">
                                                    <template x-for="entityOption in filteredLinkEntityOptions()" :key="entityOption.name">
                                                        <button @click.prevent="selectLinkEntity(entityOption.name)" type="button" class="block w-full px-3 py-2 text-left text-base hover:bg-zinc-50">
                                                            <span x-text="entityOption.label || titleize(entityOption.name)"></span>
                                                            <span class="ml-2 text-xs text-zinc-500" x-text="entityOption.name"></span>
                                                        </button>
                                                    </template>
                                                    <div x-show="filteredLinkEntityOptions().length === 0" x-cloak class="px-3 py-2 text-base text-zinc-500">No entity found.</div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                    <template x-if="selectedField.fieldtype !== 'Link' && selectedField.fieldtype !== 'Table' && selectedField.fieldtype !== 'Child Table (JSONB)'">
                                        <textarea x-model="selectedField.options" rows="5" class="w-full border border-zinc-300 px-3 py-2 text-base outline-none focus:border-zinc-500" :placeholder="optionsPlaceholder(selectedField.fieldtype)"></textarea>
                                    </template>
                                </div>

                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500">Fetch From</label>
                                    <input x-model="selectedField.fetch_from" type="text" class="w-full border border-zinc-300 px-3 py-2 text-base outline-none focus:border-zinc-500" placeholder="employee.age">
                                    <div x-show="fetchFromSuggestions(selectedField).length > 0" x-cloak class="mt-2 flex flex-wrap gap-2">
                                        <template x-for="suggestion in fetchFromSuggestions(selectedField)" :key="suggestion.value">
                                            <button @click="selectedField.fetch_from = suggestion.value" type="button" class="border border-zinc-300 px-2 py-1 text-xs hover:bg-zinc-50">
                                                <span x-text="suggestion.value"></span>
                                                <span class="ml-1 text-zinc-500" x-text="suggestion.label"></span>
                                            </button>
                                        </template>
                                    </div>
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

        <?= view('Volt\\Core\\Metadata\\Views\\partials\\modal', [
            'modalState' => 'deleteModalOpen',
            'title' => 'Delete Entity',
            'bodyHtml' => $deleteModalBody(),
            'footerHtml' => $deleteModalFooter(),
            'closeAction' => 'closeDeleteModal()',
            'maxWidthClass' => 'max-w-md',
        ]) ?>
    </div>

    <script>
        function entityBuilderApp(boot) {
            return {
                modules: boot.modules || [],
                entityOptions: boot.entityOptions || [],
                entityFieldCatalog: boot.entityFieldCatalog || {},
                loadUrl: boot.loadUrl,
                saveUrl: boot.saveUrl,
                deleteUrl: boot.deleteUrl,
                deskUrl: boot.deskUrl,
                entity: {
                    name: '',
                    module: '',
                    label: '',
                    is_submittable: false,
                    istable: false,
                    autoname: 'HASH',
                },
                workflow: {
                    id: null,
                    states: [],
                    transitions: [],
                },
                availableActions: [],
                locale: document.documentElement.lang?.startsWith('vi') ? 'vi' : 'en',
                editedState: null,
                editedTransition: null,
                namingPreset: 'HASH',
                activeTab: 'settings',
                fieldTypeFilter: '',
                fieldTypeDropdownOpen: false,
                fieldTypeAnchor: null,
                linkEntityFilter: '',
                linkEntityDropdownOpen: false,
                sessions: [],
                fields: [],
                selectedSessionUid: null,
                selectedFieldUid: null,
                sessionMenuUid: null,
                entityCustomText: '{}',
                customPatchText: '{}',
                entityListUrl: '',
                flash: { type: 'info', message: '' },
                deleteModalOpen: false,
                deletePassword: '',
                dragState: {
                    kind: null,
                    fieldUid: null,
                    targetFieldUid: null,
                },
            fieldTypes: ['Data', 'Int', 'Float', 'Check', 'Date', 'Text', 'Select', 'Code', 'Input', 'Link', 'Password', 'Attach', 'Attach Image', 'Table', 'Child Table (JSONB)'],
            normalizedFieldTypes: ['Input', 'Data', 'Int', 'Float', 'Select', 'Check', 'Text', 'Date', 'Link', 'Code', 'Password', 'Attach', 'Attach Image', 'Table', 'Child Table (JSONB)'],
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
                t(key) {
                    const dict = {
                        en: {
                            workflow_label: 'Workflow Label',
                            states: 'States',
                            transitions: 'Transitions',
                            add_state: '+ Add State',
                            add_default: '+ Defaults',
                            add_transition: '+ Add Transition',
                            no_states: 'No states defined. Implicit workflow will be used.',
                            no_transitions: 'No transitions defined.',
                            docstatus: 'Docstatus',
                            allow_edit: 'Edit',
                            is_final: 'Final',
                            action: 'Action',
                            from_state: 'From State',
                            to_state: 'To State',
                            label_opt: 'Label',
                            select_state: 'Select state',
                            select_action: 'Select action',
                            delete_state: 'Delete',
                            delete_transition: 'Delete',
                            workflow_active: 'Workflow active',
                            state_name: 'Name',
                            color: 'Color',
                            save: 'Save',
                            cancel: 'Cancel',
                            lang_en: 'English',
                            lang_vi: 'Tiếng Việt',
                            confirm_delete_state: 'Delete this state? Existing transitions referencing it will also be removed.',
                            confirm_delete_transition: 'Delete this transition?',
                        },
                        vi: {
                            workflow_label: 'Nhãn quy trình',
                            states: 'Trạng thái',
                            transitions: 'Chuyển tiếp',
                            add_state: '+ Thêm trạng thái',
                            add_default: '+ Mặc định',
                            add_transition: '+ Thêm chuyển tiếp',
                            no_states: 'Chưa có trạng thái. Quy trình mặc định sẽ được dùng.',
                            no_transitions: 'Chưa có chuyển tiếp nào.',
                            docstatus: 'Docstatus',
                            allow_edit: 'Sửa',
                            is_final: 'Cuối',
                            action: 'Hành động',
                            from_state: 'Từ trạng thái',
                            to_state: 'Đến trạng thái',
                            label_opt: 'Nhãn',
                            select_state: 'Chọn trạng thái',
                            select_action: 'Chọn hành động',
                            delete_state: 'Xoá',
                            delete_transition: 'Xoá',
                            workflow_active: 'Quy trình đang hoạt động',
                            state_name: 'Tên',
                            color: 'Màu',
                            save: 'Lưu',
                            cancel: 'Huỷ',
                            lang_en: 'English',
                            lang_vi: 'Tiếng Việt',
                            confirm_delete_state: 'Xoá trạng thái này? Các chuyển tiếp tham chiếu đến nó cũng sẽ bị xoá.',
                            confirm_delete_transition: 'Xoá chuyển tiếp này?',
                        },
                    };
                    const lang = dict[this.locale] || dict.en;
                    return lang[key] || key;
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
                    this.activeTab = 'settings';
                    this.workflow = { id: null, states: [], transitions: [] };
                    this.editedState = null;
                    this.editedTransition = null;
                    this.availableActions = [];
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
                // Normalize field type from legacy payloads so inspector and badges stay consistent.
                normalizeFieldType(fieldType) {
                    const candidate = String(fieldType || '').trim();
                    const matched = this.normalizedFieldTypes.find((type) => type.toLowerCase() === candidate.toLowerCase());

                    return matched || 'Data';
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
                    return this.sessionFields(sessionUid).filter((field) => Number(field.column || 1) === columnNumber).sort((a, b) => a.idx - b.idx);
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
                toggleFieldTypeDropdown(anchor) {
                    const shouldOpen = !this.fieldTypeDropdownOpen || this.fieldTypeAnchor !== anchor;
                    this.fieldTypeDropdownOpen = shouldOpen;
                    this.fieldTypeAnchor = shouldOpen ? anchor : null;

                    if (!shouldOpen) {
                        this.fieldTypeFilter = '';
                    }
                },
                closeFieldTypeDropdown() {
                    this.fieldTypeDropdownOpen = false;
                    this.fieldTypeAnchor = null;
                    this.fieldTypeFilter = '';
                },
                parseFieldTypeAnchor(anchor) {
                    const [kind, uid] = String(anchor || '').split(':', 2);

                    return { kind: kind || '', uid: uid || '' };
                },
                canOpenEntityList() {
                    return this.buildEntityListUrl() !== '';
                },
                canDeleteEntity() {
                    return this.slugify(this.entity.name) !== '' && this.slugify(this.entity.module) !== '';
                },
                openDeleteModal() {
                    this.deletePassword = '';
                    this.deleteModalOpen = true;
                },
                closeDeleteModal() {
                    this.deleteModalOpen = false;
                    this.deletePassword = '';
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
                async destroyEntity() {
                    const entityName = this.slugify(this.entity.name);
                    if (!entityName) {
                        this.toast('error', 'Entity name is required.');
                        return;
                    }

                    if (!String(this.deletePassword || '').trim()) {
                        this.toast('error', 'Password is required.');
                        return;
                    }

                    try {
                        const response = await fetch(this.requestUrl(`${this.deleteUrl}/${encodeURIComponent(entityName)}`), {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({
                                password: this.deletePassword,
                            }),
                        });
                        const result = await response.json();

                        if (!response.ok || result.status !== 'ok') {
                            throw new Error(result.message || 'Delete failed.');
                        }

                        this.closeDeleteModal();
                        window.location.href = this.requestUrl(`${this.deskUrl}/entities`);
                    } catch (error) {
                        this.toast('error', error.message || 'Unable to delete entity.');
                    }
                },
                addFieldFromAnchor(fieldType, anchor) {
                    const parsedAnchor = this.parseFieldTypeAnchor(anchor);
                    this.closeFieldTypeDropdown();

                    if (parsedAnchor.kind === 'field' && parsedAnchor.uid) {
                        const referenceField = this.fields.find((field) => field.uid === parsedAnchor.uid) || null;
                        if (referenceField) {
                            this.addField(referenceField.session_uid, fieldType, referenceField.uid, Number(referenceField.column || 1));
                            return;
                        }
                    }

                    if (parsedAnchor.kind === 'session' && parsedAnchor.uid) {
                        this.addField(parsedAnchor.uid, fieldType);
                        return;
                    }

                    this.addField(this.selectedSessionUid || this.sessions[0]?.uid || null, fieldType);
                },
                addField(sessionUid, fieldType = 'Input', insertAfterUid = null, columnNumber = 1) {
                    const targetSessionUid = sessionUid || this.sessions[0]?.uid || null;
                    if (!targetSessionUid) {
                        return;
                    }

                    const field = this.makeField(this.normalizeFieldType(fieldType), targetSessionUid);
                    field.column = Math.max(1, Number(columnNumber || 1));

                    if (insertAfterUid) {
                        const insertIndex = this.fields.findIndex((item) => item.uid === insertAfterUid);
                        if (insertIndex >= 0) {
                            this.fields.splice(insertIndex + 1, 0, field);
                        } else {
                            this.fields.push(field);
                        }
                    } else {
                        this.fields.push(field);
                    }

                    this.selectedSessionUid = targetSessionUid;
                    this.selectedFieldUid = field.uid;
                    this.activeTab = 'entity';
                    this.reindexFields();
                },
                makeField(fieldType, sessionUid) {
                    const index = this.fields.length + 1;
                    const normalizedType = this.normalizeFieldType(fieldType);
                    const label = `${normalizedType} ${index}`;

                    return {
                        uid: this.uuid(),
                        id: null,
                        session_uid: sessionUid,
                        column: 1,
                        fieldname: this.slugify(label),
                        label,
                        fieldtype: normalizedType,
                        length: this.defaultLength(normalizedType),
                        options: '',
                        default_value: '',
                        placeholder: '',
                        fetch_from: '',
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
                    const field = this.selectedField;
                    if (field) {
                        field.fieldtype = this.normalizeFieldType(field.fieldtype);
                    }
                },
                defaultLength(fieldType) {
                    if (['Input', 'Data', 'Select', 'Link', 'Password'].includes(fieldType)) {
                        return 255;
                    }

                    return null;
                },
                applyFieldDefaults(field) {
                    field.fieldtype = this.normalizeFieldType(field.fieldtype);
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
                    return ['Select', 'Table', 'Link', 'Child Table (JSONB)'].includes(fieldType);
                },
                stripSeparateSuffix(options) {
                    if (!options) return '';
                    return options.replace(/:separate$/, '');
                },
                handleEntityInput(event) {
                    const value = event.target.value;
                    if (this.selectedField?.fieldtype === 'Table') {
                        this.selectedField.options = value + ':separate';
                    } else {
                        this.selectedField.options = value;
                    }
                },
                openLinkEntityPicker() {
                    const raw = this.selectedField?.options || '';
                    this.linkEntityFilter = this.selectedField?.fieldtype === 'Table' ? this.stripSeparateSuffix(raw) : raw;
                    this.linkEntityDropdownOpen = true;
                },
                closeLinkEntityPicker() {
                    this.linkEntityDropdownOpen = false;
                },
                syncLinkEntityFilter() {
                    const raw = this.selectedField?.options || '';
                    this.linkEntityFilter = this.selectedField?.fieldtype === 'Table' ? this.stripSeparateSuffix(raw) : raw;
                    this.linkEntityDropdownOpen = true;
                },
                filteredLinkEntityOptions() {
                    const raw = String(this.linkEntityFilter || this.selectedField?.options || '').trim();
                    const keyword = raw.replace(/:separate$/, '').toLowerCase();
                    const isTable = this.selectedField?.fieldtype === 'Table' || this.selectedField?.fieldtype === 'Child Table (JSONB)';
                    return this.entityOptions
                        .filter((entityOption) => entityOption.name !== this.entity.name)
                        .filter((entityOption) => {
                            if (isTable) return !!entityOption.istable;
                            return true;
                        })
                        .filter((entityOption) => {
                            if (!keyword) {
                                return true;
                            }

                            const haystack = `${entityOption.name} ${entityOption.label || ''} ${entityOption.module || ''}`.toLowerCase();
                            return haystack.includes(keyword);
                        });
                },
                selectLinkEntity(entityName) {
                    if (!this.selectedField) {
                        return;
                    }

                    if (this.selectedField.fieldtype === 'Table') {
                        this.selectedField.options = entityName + ':separate';
                    } else {
                        this.selectedField.options = entityName;
                    }
                    this.linkEntityFilter = entityName;
                    this.linkEntityDropdownOpen = false;
                },
                fetchFromSuggestions(field) {
                    if (!field) {
                        return [];
                    }

                    return this.fields
                        .filter((candidate) => candidate.uid !== field.uid && candidate.fieldtype === 'Link' && candidate.options)
                        .flatMap((candidate) => {
                            const targetFields = this.entityFieldCatalog[candidate.options] || [];
                            return targetFields.map((targetField) => ({
                                value: `${candidate.fieldname}.${targetField.fieldname}`,
                                label: `${candidate.label || candidate.fieldname} -> ${targetField.label || targetField.fieldname}`,
                            }));
                        });
                },
                supportsDefaultValue(fieldType) {
                    return ['Input', 'Data', 'Select', 'Check', 'Link'].includes(fieldType);
                },
                supportsPlaceholder(fieldType) {
                    return ['Input', 'Data', 'Link'].includes(fieldType);
                },
                defaultValuePlaceholder(fieldType) {
                    if (fieldType === 'Select') {
                        return 'draft';
                    }

                    if (fieldType === 'Input' || fieldType === 'Data' || fieldType === 'Link') {
                        return 'Default text';
                    }

                    return '';
                },
                optionsPlaceholder(fieldType) {
                    if (fieldType === 'Select') {
                        return 'draft\nsubmitted\ncancelled';
                    }

                    if (fieldType === 'Table' || fieldType === 'Link' || fieldType === 'Child Table (JSONB)') {
                        return 'target_entity_name';
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
                            istable: !!payload.entity.istable,
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
                                fieldtype: this.normalizeFieldType(field.fieldtype || 'Input'),
                                length: field.length ?? this.defaultLength(this.normalizeFieldType(field.fieldtype || 'Input')),
                                options: field.options || '',
                                default_value: parsedCustom.default_value ?? '',
                                placeholder: parsedCustom.placeholder ?? '',
                                fetch_from: parsedCustom.fetch_from ?? '',
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
                        this.activeTab = 'settings';

                        this.workflow = {
                            id: payload.workflow?.id || null,
                            states: Array.isArray(payload.workflow?.states) ? payload.workflow.states.map((s) => ({ ...s, uid: this.uuid() })) : [],
                            transitions: Array.isArray(payload.workflow?.transitions) ? payload.workflow.transitions.map((t) => ({ ...t, uid: this.uuid() })) : [],
                        };
                        this.availableActions = Array.isArray(payload.workflow?.actions) ? payload.workflow.actions : [];

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
                addDefaultWorkflowStates() {
                    this.workflow.states = [
                        { uid: this.uuid(), name: 'Draft', docstatus: 0, allow_edit: true, is_final: false, color: '#6b7280' },
                        { uid: this.uuid(), name: 'Submitted', docstatus: 1, allow_edit: false, is_final: false, color: '#3b82f6' },
                        { uid: this.uuid(), name: 'Approved', docstatus: 1, allow_edit: false, is_final: true, color: '#22c55e' },
                        { uid: this.uuid(), name: 'Cancelled', docstatus: 2, allow_edit: false, is_final: false, color: '#ef4444' },
                    ];
                    this.workflow.transitions = [
                        { uid: this.uuid(), from_state: 'Draft', action: 'Submit', to_state: 'Submitted' },
                        { uid: this.uuid(), from_state: 'Submitted', action: 'Approve', to_state: 'Approved' },
                        { uid: this.uuid(), from_state: 'Submitted', action: 'Cancel', to_state: 'Cancelled' },
                        { uid: this.uuid(), from_state: 'Cancelled', action: 'Amend', to_state: 'Draft' },
                    ];
                },
                addWorkflowState() {
                    this.workflow.states.push({
                        uid: this.uuid(),
                        name: '',
                        docstatus: 0,
                        allow_edit: false,
                        is_final: false,
                        color: '#6b7280',
                    });
                },
                removeWorkflowState(uid) {
                    const removed = this.workflow.states.find((s) => s.uid === uid);
                    this.workflow.states = this.workflow.states.filter((s) => s.uid !== uid);
                    if (removed) {
                        this.workflow.transitions = this.workflow.transitions.filter(
                            (t) => t.from_state !== removed.name && t.to_state !== removed.name,
                        );
                    }
                },
                addWorkflowTransition() {
                    if (this.workflow.states.length < 2) return;
                    const from = this.workflow.states[0];
                    const to = this.workflow.states[1];
                    this.workflow.transitions.push({
                        uid: this.uuid(),
                        from_state: from.name,
                        action: '',
                        to_state: to.name,
                    });
                },
                removeWorkflowTransition(uid) {
                    this.workflow.transitions = this.workflow.transitions.filter((t) => t.uid !== uid);
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
                        if (String(field.fetch_from || '').trim() !== '') {
                            custom.fetch_from = String(field.fetch_from || '').trim();
                        } else {
                            delete custom.fetch_from;
                        }
                        custom.in_list_view = !!field.in_list_view;

                        return {
                            id: field.id,
                            fieldname: this.slugify(field.fieldname || field.label),
                            label: field.label || this.titleize(field.fieldname),
                            fieldtype: this.normalizeFieldType(field.fieldtype),
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

                    const workflowStates = this.workflow.states.map((s) => ({
                        uid: s.uid,
                        state: s.name,
                        docstatus: Number(s.docstatus || 0),
                        allow_edit: !!s.allow_edit,
                        is_final: !!s.is_final,
                        color: s.color || '#6b7280',
                    }));

                    const workflowTransitions = this.workflow.transitions.map((t) => ({
                        uid: t.uid,
                        from_state: t.from_state,
                        action: t.action,
                        to_state: t.to_state,
                    }));

                    return {
                        entity: {
                            name: entityName,
                            module: moduleName,
                            label: this.entity.label,
                            is_submittable: !!this.entity.is_submittable,
                            istable: !!this.entity.istable,
                            autoname: this.entity.autoname || 'HASH',
                            s_custom_jsonb: entityCustom,
                        },
                        fields,
                        custom_patch: customPatch,
                        workflow: {
                            id: this.workflow.id || null,
                            states: workflowStates,
                            transitions: workflowTransitions,
                        },
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
