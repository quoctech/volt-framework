<?php

/** @var string $title */
/** @var string $dataUrl */
/** @var string $createUrl */
/** @var string $editUrlBase */
/** @var string $builderUrl */
/** @var array<string, array<string, string>> $linkTargets */
/** @var bool $isSubmittable */
$columns = json_decode('[{"fieldname":"name","label":"Name","fieldtype":"Data"}]', true) ?: [];
if ($isSubmittable) {
    $columns[] = ['fieldname' => 'workflow_state', 'label' => 'State', 'fieldtype' => 'Data'];
}
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
    <div x-data="employeeeducationListApp({
            title: <?= esc(json_encode($title, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            dataUrl: <?= esc(json_encode($dataUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            createUrl: <?= esc(json_encode($createUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            editUrlBase: <?= esc(json_encode($editUrlBase, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            deleteUrlBase: <?= esc(json_encode(site_url('hrms/api/employeeeducation/delete'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            columns: <?= esc(json_encode($columns, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            linkTargets: <?= esc(json_encode($linkTargets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            isSubmittable: <?= json_encode($isSubmittable) ?>,
            submitUrlBase: <?= esc(json_encode(site_url('hrms/api/employeeeducation/submit'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            approveUrlBase: <?= esc(json_encode(site_url('hrms/api/employeeeducation/approve'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            cancelUrlBase: <?= esc(json_encode(site_url('hrms/api/employeeeducation/cancel'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            amendUrlBase: <?= esc(json_encode(site_url('hrms/api/employeeeducation/amend'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>
        })" x-init="init()" class="claro-page claro-page--wide">
        <div class="claro-table-toolbar">
            <div class="claro-table-toolbar__left">
                <div class="claro-page-header" style="margin-bottom:0">
                    <h1 class="claro-page-header__title"><?= esc($title) ?></h1>
                    <p class="claro-page-header__subtitle"><?= esc('/hrms/employeeeducation') ?></p>
                </div>
            </div>
            <div class="claro-table-toolbar__right">
                <a href="<?= esc($builderUrl) ?>" class="claro-button claro-button--small">Open Builder</a>
                <a href="<?= esc($createUrl) ?>" class="claro-button claro-button--small claro-button--primary">Create Employeeeducation</a>
            </div>
        </div>

        <div class="claro-card">
            <div class="claro-card__content" style="display:flex;flex-wrap:wrap;align-items:center;gap:var(--claro-space-m);padding-bottom:var(--claro-space-m);border-bottom:1px solid var(--claro-gray-100)">
                <div class="claro-search" style="flex:1;min-width:16rem">
                    <span class="claro-search__icon">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    <input x-model="query" @keydown.enter.prevent="load(1)" type="text" placeholder="Filter rows" class="claro-input claro-search__input">
                </div>
                <select x-model="perPage" @change="load(1)" class="claro-select" style="width:auto">
                    <template x-for="option in perPageOptions" :key="option">
                        <option :value="option" x-text="option"></option>
                    </template>
                </select>
                <button @click="load(1)" type="button" class="claro-button claro-button--small">Reload</button>
            </div>

            <div style="overflow:auto">
                <table class="claro-table" style="margin:0">
                    <thead>
                        <tr>
                            <template x-for="column in columns" :key="column.fieldname">
                                <th x-text="column.label"></th>
                            </template>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-if="loading">
                            <tr>
                                <td :colspan="columns.length + 1" style="text-align:center;padding:var(--claro-space-xl) var(--claro-space-m);color:var(--claro-color-text-light)">Loading...</td>
                            </tr>
                        </template>
                        <template x-if="!loading && rows.length === 0">
                            <tr>
                                <td :colspan="columns.length + 1" style="text-align:center;padding:var(--claro-space-xl) var(--claro-space-m);color:var(--claro-color-text-light)">No rows found.</td>
                            </tr>
                        </template>
                        <template x-for="row in rows" :key="row.name ?? JSON.stringify(row)">
                            <tr>
                                <template x-for="column in columns" :key="column.fieldname">
                                    <td>
                                        <template x-if="column.fieldname === 'workflow_state'">
                                            <span class="claro-badge" x-bind:class="workflowStateBadgeClass(String(row.workflow_state || ''))" x-text="row.workflow_state || 'Draft'"></span>
                                        </template>
                                        <template x-if="column.fieldname !== 'workflow_state' && isLinkColumn(column) && canOpenLinkedRecord(column, row)">
                                            <button @click="openLinkedRecord(column, row)" type="button" style="color:var(--claro-color-primary);text-decoration:underline" x-text="linkDisplayValue(column, row)"></button>
                                        </template>
                                        <template x-if="column.fieldname !== 'workflow_state' && (!isLinkColumn(column) || !canOpenLinkedRecord(column, row))">
                                            <span x-text="isLinkColumn(column) ? linkDisplayValue(column, row) : cellValue(row, column.fieldname)"></span>
                                        </template>
                                    </td>
                                </template>
                                <td class="claro-table__actions">
                                    <button @click="openEdit(row.name)" type="button" class="claro-button claro-button--small">Edit</button>
                                    <button @click="deleteRow(row.name)" type="button" class="claro-button claro-button--small">Delete</button>
                                    <button x-show="isSubmittable && row.workflow_state === 'Draft'" @click="submitRow(row.name)" type="button" class="claro-button claro-button--small" style="color:#7a5a00;background:var(--claro-color-warning-bg)">Submit</button>
                                    <button x-show="isSubmittable && row.workflow_state === 'Submitted'" @click="approveRow(row.name)" type="button" class="claro-button claro-button--small" style="color:#1a7a4a;background:var(--claro-color-success-bg)">Approve</button>
                                    <button x-show="isSubmittable && row.workflow_state === 'Submitted'" @click="cancelRow(row.name)" type="button" class="claro-button claro-button--small claro-button--danger">Cancel</button>
                                    <button x-show="isSubmittable && row.workflow_state === 'Cancelled' && !row.amended_from" @click="amendRow(row.name)" type="button" class="claro-button claro-button--small">Amend</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="claro-pagination">
                <p style="margin:0" x-text="paginationText()"></p>
                <div class="claro-pagination__controls">
                    <button @click="load(page - 1)" :disabled="page <= 1" type="button" class="claro-button claro-button--small">Prev</button>
                    <button @click="load(page + 1)" :disabled="page >= totalPages" type="button" class="claro-button claro-button--small">Next</button>
                </div>
            </div>
        </div>
    </div>

    <script><?php readfile(APPPATH . 'Modules/Hrms/Entities/Employeeeducation/employeeeducation_list.js'); ?></script>
</body>
</html>