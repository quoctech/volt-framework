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
$__lang = \Volt\Core\Config\Lang\LangService::load();
?><!doctype html>
<html lang="<?= esc($__lang['code'] ?? 'en') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title) ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/vendor/tailwindcss/tailwind.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/volt/claro.css') ?>">
    <script defer src="<?= base_url('assets/volt/claro.js') ?>"></script>
    <script defer src="<?= base_url('assets/vendor/alpinejs/alpine.min.js') ?>"></script>
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="claro-body">
    <div x-data="employeeeducationFormApp({
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
        })" x-init="init()" class="claro-page">
        <div class="claro-table-toolbar">
            <div class="claro-table-toolbar__left">
                <div class="claro-page-header" style="margin-bottom:0">
                    <h1 class="claro-page-header__title"><?= esc($title) ?></h1>
                    <p class="claro-page-header__subtitle"><?= esc('/hrms/employeeeducation') ?></p>
                </div>
            </div>
            <div class="claro-table-toolbar__right">
                <template x-if="isSubmittable && recordName">
                    <span class="claro-badge" x-bind:class="workflowStateBadgeClass" x-text="workflowState || 'Draft'"></span>
                </template>
                <a href="<?= esc($listUrl) ?>" class="claro-button claro-button--small">Back to List</a>
                <button @click="save()" type="button" class="claro-button claro-button--small claro-button--primary">Save Item</button>
                <template x-if="isSubmittable && recordName">
                    <div style="display:flex;gap:var(--claro-space-xs)">
                        <button @click="submitWorkflow()" type="button" class="claro-button claro-button--small" style="color:#7a5a00;background:var(--claro-color-warning-bg)" x-show="canSubmit">Submit</button>
                        <button @click="approveWorkflow()" type="button" class="claro-button claro-button--small" style="color:#1a7a4a;background:var(--claro-color-success-bg)" x-show="canApprove">Approve</button>
                        <button @click="cancelWorkflow()" type="button" class="claro-button claro-button--small claro-button--danger" x-show="canCancel">Cancel</button>
                        <button @click="amendWorkflow()" type="button" class="claro-button claro-button--small" x-show="canAmend">Amend</button>
                    </div>
                </template>
            </div>
        </div>

        <div style="display:grid;gap:var(--claro-space-m)">
            <template x-for="session in sessions" :key="session.uid">
                <section class="claro-card">
                    <div class="claro-card__content" style="padding-bottom:var(--claro-space-m);border-bottom:1px solid var(--claro-gray-100)">
                        <h2 style="margin:0" x-text="session.title || 'Session'"></h2>
                        <p x-show="session.description" style="margin:var(--claro-space-xs) 0 0;font-size:var(--claro-font-size-s);color:var(--claro-color-text-light)" x-text="session.description"></p>
                    </div>
                    <div class="claro-card__content">
                        <div :style="sessionGridStyle(session)">
                            <template x-for="columnNumber in sessionColumnNumbers(session)" :key="session.uid + '_' + columnNumber">
                                <div style="display:grid;gap:var(--claro-space-m);align-content:start">
                                    <template x-for="field in sessionFieldsByColumn(session.uid, columnNumber)" :key="field.fieldname">
                                        <label style="display:block">
                                            <span style="display:flex;align-items:center;gap:var(--claro-space-xs);margin-bottom:var(--claro-space-xs);font-size:var(--claro-font-size-xs);font-weight:700;text-transform:uppercase;letter-spacing:0.18em;color:var(--claro-color-text-light)">
                                                <span x-text="field.label"></span>
                                                <span x-show="field.is_required" x-cloak style="color:var(--claro-color-error)">*</span>
                                                <span x-show="field.read_only" x-cloak class="claro-badge claro-badge--info" style="font-size:var(--claro-font-size-xxs);letter-spacing:normal">Read only</span>
                                            </span>
                                            <template x-if="field.fieldtype === 'Check'">
                                                <input x-model="form[field.fieldname]" type="checkbox" :disabled="field.read_only">
                                            </template>
                                            <template x-if="field.fieldtype === 'Select'">
                                                <select x-model="form[field.fieldname]" class="claro-select" :disabled="field.read_only" :required="field.is_required">
                                                    <option value="">Select</option>
                                                    <template x-for="option in parseOptions(field.options)" :key="option">
                                                        <option :value="option" x-text="option"></option>
                                                    </template>
                                                </select>
                                            </template>
                                            <template x-if="field.fieldtype === 'Link'">
                                                <div style="position:relative" @click.outside="closeLinkLookup(field.fieldname)">
                                                    <input
                                                        x-model="form[field.fieldname]"
                                                        @focus="openLinkLookup(field)"
                                                        @click="openLinkLookup(field)"
                                                        @input="handleLinkInput(field)"
                                                        @change="handleLinkChange(field)"
                                                        type="text"
                                                        class="claro-input"
                                                        :placeholder="field.placeholder || ''"
                                                        :readonly="field.read_only"
                                                        :required="field.is_required"
                                                        autocomplete="off"
                                                    >
                                                    <div x-show="linkLookupOpen(field.fieldname)" x-cloak class="claro-card" style="position:absolute;left:0;top:calc(100% + 4px);z-index:20;width:22rem;max-width:calc(100vw - 3rem);overflow:hidden">
                                                        <div x-show="linkLookupState(field.fieldname).loading" x-cloak style="padding:var(--claro-space-s) var(--claro-space-m);border-bottom:1px solid var(--claro-gray-100);font-size:var(--claro-font-size-s);color:var(--claro-color-text-light)">
                                                            Searching...
                                                        </div>
                                                        <div style="max-height:20rem;overflow:auto">
                                                            <template x-for="item in linkLookupState(field.fieldname).items" :key="item.name">
                                                                <button @click.prevent="selectLinkLookupItem(field, item)" type="button" style="display:block;width:100%;padding:var(--claro-space-s) var(--claro-space-m);border:0;border-bottom:1px solid var(--claro-gray-100);background:var(--claro-color-bg);text-align:left;cursor:pointer" @mouseenter="$el.style.background='var(--claro-color-bg-hover)'" @mouseleave="$el.style.background='var(--claro-color-bg)'">
                                                                    <div style="font-weight:500;color:var(--claro-color-text)" x-text="linkLookupCodeText(item)"></div>
                                                                    <div x-show="linkLookupPrimaryText(field, item) !== ''" x-cloak style="font-size:var(--claro-font-size-s);color:var(--claro-color-text-light)" x-text="linkLookupPrimaryText(field, item)"></div>
                                                                </button>
                                                            </template>
                                                            <div x-show="!linkLookupState(field.fieldname).loading && linkLookupState(field.fieldname).items.length === 0" x-cloak style="padding:var(--claro-space-s) var(--claro-space-m);font-size:var(--claro-font-size-s);color:var(--claro-color-text-light)">
                                                                No linked record found.
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                            <template x-if="field.fieldtype === 'Table' || field.fieldtype === 'Child Table (JSONB)'">
                                                <div :class="field.read_only ? 'claro-card claro-card--readonly' : 'claro-card'">
                                                    <table class="claro-table" style="margin:0">
                                                        <thead>
                                                            <tr>
                                                                <template x-for="col in (field.child_columns || [])" :key="col.fieldname">
                                                                    <th x-text="col.label || col.fieldname"></th>
                                                                </template>
                                                                <th x-show="!field.read_only" style="width:2.5rem"></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <template x-for="(row, rowIdx) in (form[field.fieldname] || [])" :key="rowIdx">
                                                                <tr>
                                                                    <template x-for="col in (field.child_columns || [])" :key="col.fieldname">
                                                                        <td>
                                                                            <template x-if="col.fieldtype === 'Check'">
                                                                                <input type="checkbox" x-model="form[field.fieldname][rowIdx][col.fieldname]">
                                                                            </template>
                                                                            <template x-if="col.fieldtype === 'Select'">
                                                                                <select x-model="form[field.fieldname][rowIdx][col.fieldname]" class="claro-select">
                                                                                    <option value="">Select</option>
                                                                                    <template x-for="opt in parseOptions(col.options || '')" :key="opt">
                                                                                        <option :value="opt" x-text="opt"></option>
                                                                                    </template>
                                                                                </select>
                                                                            </template>
                                                                            <template x-if="col.fieldtype === 'Int'">
                                                                                <input type="number" step="1" x-model="form[field.fieldname][rowIdx][col.fieldname]" class="claro-input">
                                                                            </template>
                                                                            <template x-if="col.fieldtype === 'Float'">
                                                                                <input type="number" step="any" x-model="form[field.fieldname][rowIdx][col.fieldname]" class="claro-input">
                                                                            </template>
                                                                            <template x-if="!['Check', 'Select', 'Int', 'Float'].includes(col.fieldtype)">
                                                                                <input type="text" x-model="form[field.fieldname][rowIdx][col.fieldname]" class="claro-input">
                                                                            </template>
                                                                        </td>
                                                                    </template>
                                                                    <td x-show="!field.read_only" style="text-align:center">
                                                                        <button @click="removeChildRow(field.fieldname, rowIdx)" type="button" style="color:var(--claro-color-error);font-size:var(--claro-font-size-s);font-weight:700;background:none;border:0;cursor:pointer" title="Remove row">&times;</button>
                                                                    </td>
                                                                </tr>
                                                            </template>
                                                        </tbody>
                                                    </table>
                                                    <button x-show="!field.read_only" @click="addChildRow(field.fieldname)" type="button" class="claro-button claro-button--small">+ Add Row</button>
                                                </div>
                                            </template>
                                            <template x-if="field.fieldtype === 'Text' || field.fieldtype === 'Code'">
                                                <textarea x-model="form[field.fieldname]" rows="6" class="claro-textarea" :placeholder="field.placeholder || ''" :readonly="field.read_only" :required="field.is_required"></textarea>
                                            </template>
                                            <template x-if="field.fieldtype === 'Attach' || field.fieldtype === 'Attach Image'">
                                                <div style="display:flex;align-items:center;gap:var(--claro-space-m)" :class="field.read_only ? 'claro-readonly' : ''">
                                                    <template x-if="form[field.fieldname]">
                                                        <a :href="fileDownloadUrl(form[field.fieldname])" target="_blank" style="color:var(--claro-color-primary);text-decoration:underline;font-size:var(--claro-font-size-s)" x-text="'View ' + (form[field.fieldname] || '').substring(0, 8) + '...'"></a>
                                                    </template>
                                                    <input type="file" :accept="field.fieldtype === 'Attach Image' ? 'image/*' : ''" @change="handleFileSelect(field, $event)" :disabled="field.read_only" :required="field.is_required && !form[field.fieldname]">
                                                    <div x-show="form[field.fieldname + '__uploading']" x-cloak style="font-size:var(--claro-font-size-xs);color:var(--claro-color-text-light)">Uploading...</div>
                                                </div>
                                            </template>
                                            <template x-if="!['Check', 'Select', 'Link', 'Text', 'Code', 'Table', 'Child Table (JSONB)', 'Attach', 'Attach Image'].includes(field.fieldtype)">
                                                <input x-model="form[field.fieldname]" :type="inputType(field.fieldtype)" class="claro-input" :placeholder="field.placeholder || ''" :readonly="field.read_only" :required="field.is_required">
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
    </div>

    <script><?php readfile(APPPATH . 'Modules/Hrms/Entities/Employeeeducation/employeeeducation_form.js'); ?></script>
</body>
</html>