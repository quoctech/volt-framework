<?php

/**
 * User's personal Workspace (Claro theme).
 *
 * @var array<string, mixed> $workspace
 * @var list<array<string, mixed>> $blocks
 * @var list<array<string, mixed>> $availableEntities
 * @var list<array{label:string,url:string}> $pages
 */
$workspace = $workspace ?? [];
$blocks = $blocks ?? [];
$availableEntities = $availableEntities ?? [];
$pages = $pages ?? [];

$lang = \Volt\Core\Config\Lang\LangService::load();
$w = $lang['workspace'] ?? [];

$columns = (int) ($workspace['columns'] ?? 3);
$columns = min(4, max(1, $columns));

$icons = ['doc', 'user', 'shield', 'server', 'chart', 'folder', 'link', 'star'];
?>
<div
    x-data="workspaceApp(<?= esc(json_encode([
        'blocks' => $blocks,
        'workspace' => $workspace,
        'entities' => $availableEntities,
        'pages' => $pages,
        'urls' => [
            'saveBlock' => site_url('api/workspace/block/save'),
            'deleteBlock' => site_url('api/workspace/block/delete'),
            'reorder' => site_url('api/workspace/block/reorder'),
            'save' => site_url('api/workspace/save'),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>)"
>
    <div class="claro-page-header">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:var(--claro-space-m);flex-wrap:wrap">
            <div>
                <h1 class="claro-page-header__title" x-text="workspace.title || '<?= esc($w['title'] ?? 'Workspace') ?>'"></h1>
                <p class="claro-page-header__subtitle"><?= esc($w['description'] ?? 'Your personal workspace.') ?></p>
            </div>
            <div style="display:flex;align-items:center;gap:var(--claro-space-s);flex-wrap:wrap">
                <div x-show="editMode" x-cloak class="claro-workspace-cols" title="<?= esc($w['drag_hint'] ?? 'Drag blocks to reorder them.') ?>">
                    <label for="ws-columns" style="font-weight:600"><?= esc($w['columns_label'] ?? 'Columns') ?></label>
                    <select id="ws-columns" class="claro-select" style="width:4.5rem;padding:var(--claro-space-xs) var(--claro-space-s);font-size:var(--claro-font-size-xs)" x-model.number="workspace.columns" @change="saveColumns()">
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                    </select>
                </div>
                <button
                    type="button"
                    class="claro-button"
                    :class="{ 'claro-button--primary': editMode }"
                    @click="editMode = !editMode"
                >
                    <template x-if="!editMode">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" style="vertical-align:-2px;margin-right:var(--claro-space-xs)"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM12.379 4.793 3 14.172V17h2.828l9.379-9.379-2.828-2.828z"/></svg>
                    </template>
                    <span x-text="editMode ? '<?= esc($w['done'] ?? 'Done') ?>' : '<?= esc($w['customize'] ?? 'Customize') ?>'"></span>
                </button>
            </div>
        </div>
    </div>

    <div x-show="editMode" x-cloak style="margin-top:var(--claro-space-s);font-size:var(--claro-font-size-s);color:var(--claro-color-text-light)">
        <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor" style="vertical-align:-1px;margin-right:var(--claro-space-xs)"><path fill-rule="evenodd" d="M7 4.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm4.5 0a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm4.5 0a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM7 10a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm4.5 0a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm4.5 0a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM7 15.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm4.5 0a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm4.5 0a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" clip-rule="evenodd" /></svg>
        <span><?= esc($w['edit_hint'] ?? 'Edit mode: drag blocks to reorder, use the buttons to edit or delete.') ?></span>
    </div>

    <!-- Empty state (always visible when no blocks) -->
    <div x-show="blocks.length === 0" x-cloak class="claro-workspace-empty">
        <div class="claro-workspace-empty__icon">&#127968;</div>
        <div class="claro-workspace-empty__text"><?= esc($w['empty_hint'] ?? 'Your workspace is empty. Add a block to get started.') ?></div>
        <button type="button" class="claro-button claro-button--primary" @click="openDialog()">+ <?= esc($w['add_first_block'] ?? 'Add your first block') ?></button>
    </div>

    <div
        class="claro-card-grid claro-workspace-grid"
        x-ref="grid"
        :style="'grid-template-columns:repeat(' + workspace.columns + ', minmax(0, 1fr))'"
        style="grid-template-columns:repeat(<?= $columns ?>, minmax(0, 1fr))"
    >
        <template x-for="(block, index) in blocks" :key="block.id">
            <div
                class="claro-workspace-block"
                :class="{ 'claro-workspace-block--edit': editMode }"
                :data-block-id="block.id"
                :style="'grid-column:span ' + Math.min(workspace.columns, Math.max(1, block.size))"
            >
                <div class="claro-workspace-block__toolbar">
                    <button
                        type="button"
                        class="claro-workspace-block__drag"
                        :title="'<?= esc($w['drag_hint'] ?? 'Drag to reorder') ?>'"
                        aria-label="Drag to reorder"
                    >
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7 4.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm4.5 0a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm4.5 0a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM7 10a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm4.5 0a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm4.5 0a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM7 15.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm4.5 0a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm4.5 0a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" clip-rule="evenodd" /></svg>
                    </button>
                    <button type="button" class="claro-button claro-button--extrasmall" :title="'<?= esc($w['edit_block'] ?? 'Edit') ?>'" aria-label="Edit" @click="editBlock(block)">
                        <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM12.379 4.793 3 14.172V17h2.828l9.379-9.379-2.828-2.828z"/></svg>
                    </button>
                    <button type="button" class="claro-button claro-button--extrasmall claro-button--danger" :title="'<?= esc($w['delete_confirm'] ?? 'Delete') ?>'" aria-label="Delete" @click="deleteBlock(block.id)">
                        <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75V4H3a.75.75 0 000 1.5h.063l.777 10.14A2.75 2.75 0 006.588 18h6.824a2.75 2.75 0 002.748-2.36l.777-10.14H17a.75.75 0 000-1.5h-3v-.25A2.75 2.75 0 0011.25 1h-2.5zM10 2.5c.69 0 1.25.56 1.25 1.25V4h-2.5v-.25c0-.69.56-1.25 1.25-1.25zM7.346 6.026a.75.75 0 011.497.079l.44 7a.75.75 0 01-1.497.079l-.44-7zm5.811.079a.75.75 0 011.497-.079l.44 7a.75.75 0 11-1.497.079l-.44-7z" clip-rule="evenodd" /></svg>
                    </button>
                </div>

                <template x-if="block.block_type === 'shortcut'">
                    <a :href="block.data.url || '#'" class="claro-link-card claro-workspace-shortcut">
                        <div x-show="block.data.icon" class="claro-workspace-shortcut__icon">
                            <svg x-show="block.data.icon === 'doc'" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M4 4.5A2.5 2.5 0 016.5 2H12a.75.75 0 01.53.22l3.25 3.25a.75.75 0 01.22.53V15.5a2.5 2.5 0 01-2.5 2.5h-7A2.5 2.5 0 014 15.5v-11zM8.25 7.75a.75.75 0 000 1.5h4.5a.75.75 0 000-1.5h-4.5zm0 3.5a.75.75 0 000 1.5h4.5a.75.75 0 000-1.5h-4.5zM8 4.5h2.5v2.5H8V4.5z"/></svg>
                            <svg x-show="block.data.icon === 'user'" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 100 9 4.5 4.5 0 000-9zM3.5 16.5c0-2.39 2.29-4.5 6.5-4.5s6.5 2.11 6.5 4.5v.5a.5.5 0 01-.5.5H4a.5.5 0 01-.5-.5v-.5z" clip-rule="evenodd" /></svg>
                            <svg x-show="block.data.icon === 'shield'" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a.75.75 0 01.75.75 2.25 2.25 0 002.25 2.25h.5a.75.75 0 01.75.75v.5c0 .523.144 1.014.395 1.433a7.5 7.5 0 01-.395 9.668A7.5 7.5 0 0110 19a7.5 7.5 0 01-4.23-3.63 7.5 7.5 0 01.07-9.63A2.25 2.25 0 016.25 4.25h-.5a.75.75 0 01-.75-.75V3a.75.75 0 01.75-.75h.5A2.25 2.25 0 018.75.75H10zM10 6a1 1 0 00-1 1c0 .272.108.518.284.698a.75.75 0 001.432.302A1 1 0 0010 6zm-1.75 5.75a.75.75 0 000 1.5h3.5a.75.75 0 000-1.5h-3.5z" clip-rule="evenodd" /></svg>
                            <svg x-show="block.data.icon === 'server'" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M3 5a2 2 0 012-2h10a2 2 0 012 2v2a2 2 0 01-2 2H5a2 2 0 01-2-2V5zm0 8a2 2 0 012-2h10a2 2 0 012 2v2a2 2 0 01-2 2H5a2 2 0 01-2-2v-2zM5 6a.75.75 0 100-1.5A.75.75 0 005 6zm8-.75a.75.75 0 01.75.75v.5a.75.75 0 01-1.5 0V6A.75.75 0 0113 5.25zm-8 9a.75.75 0 100-1.5.75.75 0 000 1.5zm8-.75a.75.75 0 01.75.75v.5a.75.75 0 01-1.5 0v-.5A.75.75 0 0113 13.5z"/></svg>
                            <svg x-show="block.data.icon === 'chart'" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M3 2.75A.75.75 0 012.25 2h2.5A.75.75 0 015.5 2.75v14.5a.75.75 0 01-.75.75h-2.5a.75.75 0 01-.75-.75V2.75zM9.75 2a.75.75 0 01.75.75v14.5a.75.75 0 01-.75.75h-2.5a.75.75 0 01-.75-.75V2.75A.75.75 0 019.75 2h2.5zm5.5 0a.75.75 0 01.75.75v14.5a.75.75 0 01-.75.75h-2.5a.75.75 0 01-.75-.75V2.75A.75.75 0 0112.75 2h2.5z"/></svg>
                            <svg x-show="block.data.icon === 'folder'" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M3.75 3A1.75 1.75 0 002 4.75v10.5c0 .966.784 1.75 1.75 1.75h12.5A1.75 1.75 0 0018 15.25v-8.5A1.75 1.75 0 0016.25 5h-5.19a.75.75 0 01-.53-.22L9.36 3.6a1.75 1.75 0 00-1.24-.51H3.75z"/></svg>
                            <svg x-show="block.data.icon === 'link'" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M12.232 4.232a2.5 2.5 0 013.536 3.536l-1.225 1.224a.75.75 0 001.061 1.06l1.224-1.224a4 4 0 00-5.656-5.657l-3.5 3.5a4 4 0 000 5.656.75.75 0 001.06-1.06 2.5 2.5 0 010-3.536l3.5-3.5z"/><path d="M7.768 15.768a2.5 2.5 0 01-3.536-3.536l1.225-1.224a.75.75 0 00-1.061-1.06l-1.224 1.224a4 4 0 005.656 5.657l3.5-3.5a4 4 0 000-5.656.75.75 0 00-1.06 1.06 2.5 2.5 0 010 3.536l-3.5 3.5z"/></svg>
                            <svg x-show="block.data.icon === 'star'" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z"/></svg>
                        </div>
                        <h3 class="claro-link-card__title" style="margin-top:var(--claro-space-xs)" x-text="block.title"></h3>
                    </a>
                </template>

                <template x-if="block.block_type === 'note'">
                    <div class="claro-card" style="height:100%">
                        <div class="claro-card__content" style="padding:var(--claro-space-m)">
                            <h3 x-show="block.title" class="claro-workspace-note__title" x-text="block.title"></h3>
                            <div class="claro-workspace-note__body" x-text="block.data.text"></div>
                        </div>
                    </div>
                </template>

                <template x-if="block.block_type === 'count'">
                    <a :href="block.record_url || '#'" class="claro-workspace-count">
                        <span class="claro-workspace-count__value">
                            <template x-if="block.count.ok !== false"><span x-text="Number(block.count.value).toLocaleString()"></span></template>
                            <template x-if="block.count.ok === false">&ndash;</template>
                        </span>
                        <span class="claro-workspace-count__label" x-text="block.title"></span>
                    </a>
                </template>

                <template x-if="block.block_type === 'entity_list'">
                    <div class="claro-card" style="height:100%">
                        <div class="claro-card__content" style="padding:0">
                            <div class="claro-workspace-list__header">
                                <h3 class="claro-workspace-list__title" x-text="block.title"></h3>
                                <a x-show="block.record_url" :href="block.record_url" class="claro-button claro-button--link claro-button--extrasmall" style="display:inline-flex"><?= esc($w['view_all'] ?? 'View all') ?></a>
                            </div>
                            <div class="claro-table" style="overflow-x:auto">
                                <table>
                                    <thead>
                                        <tr>
                                            <template x-for="col in block.records.columns" :key="col.name">
                                                <th x-text="col.label"></th>
                                            </template>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="row in block.records.rows" :key="row.name">
                                            <tr>
                                                <template x-for="col in block.records.columns" :key="col.name">
                                                    <td>
                                                        <template x-if="col.name === 'name' && block.record_url">
                                                            <a :href="block.record_url + '/' + encodeURIComponent(row.name)" style="font-weight:500" x-text="row.name"></a>
                                                        </template>
                                                        <template x-if="!(col.name === 'name' && block.record_url)">
                                                            <span x-text="row[col.name] ?? ''"></span>
                                                        </template>
                                                    </td>
                                                </template>
                                            </tr>
                                        </template>
                                        <tr x-show="block.records.rows.length === 0">
                                            <td :colspan="block.records.columns.length || 1" style="font-size:var(--claro-font-size-s);color:var(--claro-color-text-light)"><?= esc($w['no_records'] ?? 'No records yet.') ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>

    <!-- Add bar (edit mode only) -->
    <div
        class="claro-workspace-add"
        x-show="editMode && blocks.length > 0"
        x-cloak
        style="margin-top:var(--claro-space-m)"
        @click="openDialog()"
        role="button"
        tabindex="0"
        @keydown.enter="openDialog()"
    >
        <span class="claro-workspace-add__icon">+</span>
        <span><?= esc($w['add_tile'] ?? 'Add block') ?></span>
    </div>

    <!-- Add / Edit block dialog -->
    <div x-cloak x-show="dialogOpen" class="claro-dialog" role="dialog" aria-modal="true">
        <div class="claro-dialog__overlay" @click="dialogOpen = false"></div>
        <div class="claro-dialog__panel" style="max-width:34rem" @click.stop>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:var(--claro-space-m) var(--claro-space-l);border-bottom:1px solid var(--claro-gray-100)">
                <h2 style="margin:0;font-size:var(--claro-font-size-base);font-weight:700" x-text="editingId ? '<?= esc($w['edit_block'] ?? 'Edit Block') ?>' : '<?= esc($w['add_block'] ?? 'Add Block') ?>'"></h2>
                <button type="button" class="claro-dialog__close" @click="dialogOpen = false" aria-label="Close">&times;</button>
            </div>

            <div style="padding:var(--claro-space-m) var(--claro-space-l)">
                <!-- Step 1: pick a type (only when adding) -->
                <div x-show="!editingId" class="claro-form-item">
                    <label class="claro-form-item__label"><?= esc($w['type_label'] ?? 'What would you like to add?') ?></label>
                    <div class="claro-workspace-type-grid">
                        <button type="button" class="claro-workspace-type-card" :class="{ 'claro-workspace-type-card--active': form.block_type === 'shortcut' }" @click="pickType('shortcut')">
                            <span class="claro-workspace-type-card__icon"><svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path d="M12.232 4.232a2.5 2.5 0 013.536 3.536l-1.225 1.224a.75.75 0 001.061 1.06l1.224-1.224a4 4 0 00-5.656-5.657l-3.5 3.5a4 4 0 000 5.656.75.75 0 001.06-1.06 2.5 2.5 0 010-3.536l3.5-3.5z"/><path d="M7.768 15.768a2.5 2.5 0 01-3.536-3.536l1.225-1.224a.75.75 0 00-1.061-1.06l-1.224 1.224a4 4 0 005.656 5.657l3.5-3.5a4 4 0 000-5.656.75.75 0 00-1.06 1.06 2.5 2.5 0 010 3.536l-3.5 3.5z"/></svg></span>
                            <span class="claro-workspace-type-card__title"><?= esc($w['type_shortcut'] ?? 'Shortcut') ?></span>
                            <span class="claro-workspace-type-card__desc"><?= esc($w['type_shortcut_desc'] ?? 'Link to a page or URL') ?></span>
                        </button>
                        <button type="button" class="claro-workspace-type-card" :class="{ 'claro-workspace-type-card--active': form.block_type === 'note' }" @click="pickType('note')">
                            <span class="claro-workspace-type-card__icon"><svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM12.379 4.793 3 14.172V17h2.828l9.379-9.379-2.828-2.828z"/></svg></span>
                            <span class="claro-workspace-type-card__title"><?= esc($w['type_note'] ?? 'Note') ?></span>
                            <span class="claro-workspace-type-card__desc"><?= esc($w['type_note_desc'] ?? 'Write a quick note') ?></span>
                        </button>
                        <button type="button" class="claro-workspace-type-card" :class="{ 'claro-workspace-type-card--active': form.block_type === 'entity_list' }" @click="pickType('entity_list')">
                            <span class="claro-workspace-type-card__icon"><svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 4.25A2.25 2.25 0 015.25 2h9.5A2.25 2.25 0 0117 4.25v11.5A2.25 2.25 0 0114.75 18h-9.5A2.25 2.25 0 013 15.75V4.25zM7 5.5a.75.75 0 000 1.5h6a.75.75 0 000-1.5H7zM6.25 9.5a.75.75 0 01.75-.75h6a.75.75 0 010 1.5H7a.75.75 0 01-.75-.75zm.75 2.5a.75.75 0 000 1.5h6a.75.75 0 000-1.5H7z" clip-rule="evenodd" /></svg></span>
                            <span class="claro-workspace-type-card__title"><?= esc($w['type_entity_list'] ?? 'Recent List') ?></span>
                            <span class="claro-workspace-type-card__desc"><?= esc($w['type_entity_list_desc'] ?? 'Show recent records of an entity') ?></span>
                        </button>
                        <button type="button" class="claro-workspace-type-card" :class="{ 'claro-workspace-type-card--active': form.block_type === 'count' }" @click="pickType('count')">
                            <span class="claro-workspace-type-card__icon"><svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 3.5A2.5 2.5 0 002.5 6v8A2.5 2.5 0 005 16.5h10a2.5 2.5 0 002.5-2.5V6A2.5 2.5 0 0015 3.5H5zM10 6a.75.75 0 01.75.75v2.5h2.5a.75.75 0 010 1.5h-2.5v2.5a.75.75 0 01-1.5 0v-2.5h-2.5a.75.75 0 010-1.5h2.5v-2.5A.75.75 0 0110 6z" clip-rule="evenodd" /></svg></span>
                            <span class="claro-workspace-type-card__title"><?= esc($w['type_count'] ?? 'Counter') ?></span>
                            <span class="claro-workspace-type-card__desc"><?= esc($w['type_count_desc'] ?? 'Show a live record count') ?></span>
                        </button>
                    </div>
                </div>

                <!-- Step 2: common fields -->
                <div class="claro-form-item" x-show="form.block_type === 'shortcut'">
                    <label class="claro-form-item__label"><?= esc($w['page_quick_pick'] ?? 'Or pick a page') ?></label>
                    <select class="claro-select" @change="applyPage($event.target.value)">
                        <option value="">-- <?= esc($w['pick_page'] ?? 'Pick a page…') ?> --</option>
                        <template x-for="page in pages" :key="page.url">
                            <option :value="page.url" x-text="page.label"></option>
                        </template>
                    </select>
                </div>

                <div class="claro-form-item">
                    <label class="claro-form-item__label"><?= esc($w['title_label'] ?? 'Title') ?></label>
                    <input type="text" class="claro-input" x-model="form.title" :placeholder="'<?= esc($w['title_placeholder'] ?? 'Block title') ?>'">
                </div>

                <div class="claro-form-item" x-show="form.block_type === 'shortcut'">
                    <label class="claro-form-item__label"><?= esc($w['url_label'] ?? 'URL') ?></label>
                    <input type="text" class="claro-input" x-model="form.data.url" :placeholder="'<?= esc($w['url_placeholder'] ?? '/desk/entities') ?>'">
                </div>

                <div class="claro-form-item" x-show="form.block_type === 'shortcut'">
                    <label class="claro-form-item__label"><?= esc($w['icon_label'] ?? 'Icon') ?></label>
                    <div class="claro-workspace-icon-grid">
                        <template x-for="icon in icons" :key="icon">
                            <button type="button" class="claro-workspace-icon" :class="{ 'claro-workspace-icon--active': form.data.icon === icon }" :title="icon" @click="form.data.icon = icon">
                                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                    <path x-show="icon === 'doc'" d="M4 4.5A2.5 2.5 0 016.5 2H12a.75.75 0 01.53.22l3.25 3.25a.75.75 0 01.22.53V15.5a2.5 2.5 0 01-2.5 2.5h-7A2.5 2.5 0 014 15.5v-11zM8.25 7.75a.75.75 0 000 1.5h4.5a.75.75 0 000-1.5h-4.5zm0 3.5a.75.75 0 000 1.5h4.5a.75.75 0 000-1.5h-4.5zM8 4.5h2.5v2.5H8V4.5z"/>
                                    <path x-show="icon === 'user'" fill-rule="evenodd" d="M10 1a4.5 4.5 0 100 9 4.5 4.5 0 000-9zM3.5 16.5c0-2.39 2.29-4.5 6.5-4.5s6.5 2.11 6.5 4.5v.5a.5.5 0 01-.5.5H4a.5.5 0 01-.5-.5v-.5z" clip-rule="evenodd"/>
                                    <path x-show="icon === 'shield'" fill-rule="evenodd" d="M10 1a.75.75 0 01.75.75 2.25 2.25 0 002.25 2.25h.5a.75.75 0 01.75.75v.5c0 .523.144 1.014.395 1.433a7.5 7.5 0 01-.395 9.668A7.5 7.5 0 0110 19a7.5 7.5 0 01-4.23-3.63 7.5 7.5 0 01.07-9.63A2.25 2.25 0 016.25 4.25h-.5a.75.75 0 01-.75-.75V3a.75.75 0 01.75-.75h.5A2.25 2.25 0 018.75.75H10zM10 6a1 1 0 00-1 1c0 .272.108.518.284.698a.75.75 0 001.432.302A1 1 0 0010 6zm-1.75 5.75a.75.75 0 000 1.5h3.5a.75.75 0 000-1.5h-3.5z" clip-rule="evenodd"/>
                                    <path x-show="icon === 'server'" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v2a2 2 0 01-2 2H5a2 2 0 01-2-2V5zm0 8a2 2 0 012-2h10a2 2 0 012 2v2a2 2 0 01-2 2H5a2 2 0 01-2-2v-2zM5 6a.75.75 0 100-1.5A.75.75 0 005 6zm8-.75a.75.75 0 01.75.75v.5a.75.75 0 01-1.5 0V6A.75.75 0 0113 5.25zm-8 9a.75.75 0 100-1.5.75.75 0 000 1.5zm8-.75a.75.75 0 01.75.75v.5a.75.75 0 01-1.5 0v-.5A.75.75 0 0113 13.5z"/>
                                    <path x-show="icon === 'chart'" d="M3 2.75A.75.75 0 012.25 2h2.5A.75.75 0 015.5 2.75v14.5a.75.75 0 01-.75.75h-2.5a.75.75 0 01-.75-.75V2.75zM9.75 2a.75.75 0 01.75.75v14.5a.75.75 0 01-.75.75h-2.5a.75.75 0 01-.75-.75V2.75A.75.75 0 019.75 2h2.5zm5.5 0a.75.75 0 01.75.75v14.5a.75.75 0 01-.75.75h-2.5a.75.75 0 01-.75-.75V2.75A.75.75 0 0112.75 2h2.5z"/>
                                    <path x-show="icon === 'folder'" d="M3.75 3A1.75 1.75 0 002 4.75v10.5c0 .966.784 1.75 1.75 1.75h12.5A1.75 1.75 0 0018 15.25v-8.5A1.75 1.75 0 0016.25 5h-5.19a.75.75 0 01-.53-.22L9.36 3.6a1.75 1.75 0 00-1.24-.51H3.75z"/>
                                    <path x-show="icon === 'link'" d="M12.232 4.232a2.5 2.5 0 013.536 3.536l-1.225 1.224a.75.75 0 001.061 1.06l1.224-1.224a4 4 0 00-5.656-5.657l-3.5 3.5a4 4 0 000 5.656.75.75 0 001.06-1.06 2.5 2.5 0 010-3.536l3.5-3.5zM7.768 15.768a2.5 2.5 0 01-3.536-3.536l1.225-1.224a.75.75 0 00-1.061-1.06l-1.224 1.224a4 4 0 005.656 5.657l3.5-3.5a4 4 0 000-5.656.75.75 0 00-1.06 1.06 2.5 2.5 0 010 3.536l-3.5 3.5z"/>
                                    <path x-show="icon === 'star'" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z"/>
                                </svg>
                            </button>
                        </template>
                    </div>
                </div>

                <div class="claro-form-item" x-show="form.block_type === 'note'">
                    <label class="claro-form-item__label"><?= esc($w['text_label'] ?? 'Text') ?></label>
                    <textarea class="claro-textarea" rows="4" x-model="form.data.text" :placeholder="'<?= esc($w['text_placeholder'] ?? 'Write your note here…') ?>'"></textarea>
                </div>

                <div class="claro-form-item" x-show="form.block_type === 'entity_list' || form.block_type === 'count'">
                    <label class="claro-form-item__label"><?= esc($w['entity_label'] ?? 'Entity') ?></label>
                    <template x-if="entities.length > 0">
                        <select class="claro-select" x-model="form.data.entity">
                            <option value="">-- <?= esc($w['select_entity'] ?? 'Select entity') ?> --</option>
                            <template x-for="entity in entities" :key="entity.name">
                                <option :value="entity.name" x-text="entity.label + (entity.module ? ' (' + entity.module + ')' : '')"></option>
                            </template>
                        </select>
                    </template>
                    <template x-if="entities.length === 0">
                        <div style="font-size:var(--claro-font-size-s);color:var(--claro-color-text-light)">
                            <?= esc($w['no_entities'] ?? 'No entities yet.') ?>
                            <span x-show="isAdmin"><a href="<?= site_url('desk/entity-builder') ?>"><?= esc($w['no_entities_hint'] ?? 'Create one in the Entity Builder.') ?></a></span>
                        </div>
                    </template>
                </div>

                <div class="claro-form-item" x-show="form.block_type === 'entity_list'">
                    <label class="claro-form-item__label"><?= esc($w['max_rows_label'] ?? 'Show') ?></label>
                    <select class="claro-select" x-model.number="form.data.max_rows" style="width:6rem">
                        <option value="3">3</option>
                        <option value="5">5</option>
                    </select>
                </div>

                <div class="claro-form-item">
                    <label class="claro-form-item__label"><?= esc($w['width_label'] ?? 'Width') ?></label>
                    <div class="claro-workspace-width">
                        <template x-for="n in [1, 2, 3]" :key="n">
                            <button type="button" class="claro-workspace-width__chip" :class="{ 'claro-workspace-width__chip--active': form.size === n }" @click="form.size = n">
                                <span class="claro-workspace-width__dot"></span>
                                <template x-for="i in n" :key="i"><span class="claro-workspace-width__dot"></span></template>
                                <span x-text="n + ' <?= esc($w['width_col'] ?? 'col') ?>'"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:var(--claro-space-s);border-top:1px solid var(--claro-gray-100);padding:var(--claro-space-s) var(--claro-space-l)">
                <button type="button" class="claro-button" @click="dialogOpen = false"><?= esc($w['cancel'] ?? 'Cancel') ?></button>
                <button type="button" class="claro-button claro-button--primary" @click="saveBlock()"><?= esc($w['save'] ?? 'Save') ?></button>
            </div>
        </div>
    </div>
</div>

<script src="<?= base_url('assets/vendor/sortablejs/Sortable.min.js') ?>"></script>
<script>
    function workspaceApp(config) {
        return {
            workspace: config.workspace,
            blocks: config.blocks,
            entities: config.entities,
            pages: config.pages,
            urls: config.urls,
            isAdmin: <?= (bool) ($isAdmin ?? false) ? 'true' : 'false' ?>,
            icons: <?= json_encode($icons, JSON_UNESCAPED_UNICODE) ?>,
            editMode: false,
            sortable: null,
            dialogOpen: false,
            editingId: 0,
            form: {
                block_type: 'shortcut',
                title: '',
                size: 1,
                data: { url: '', icon: 'link', text: '', entity: '', max_rows: 5 },
            },

            init() {
                var grid = this.$refs.grid;
                var self = this;
                if (grid && typeof Sortable !== 'undefined') {
                    this.sortable = Sortable.create(grid, {
                        handle: '.claro-workspace-block__drag',
                        draggable: '.claro-workspace-block',
                        disabled: true,
                        animation: 150,
                        ghostClass: 'claro-workspace-block--drag',
                        chosenClass: 'claro-workspace-block--drop',
                        onEnd(evt) {
                            var list = grid.querySelectorAll('.claro-workspace-block');
                            var order = [];
                            list.forEach(function (el) {
                                var id = el.getAttribute('data-block-id');
                                if (id) order.push(parseInt(id, 10));
                            });
                            if (order.length !== self.blocks.length) return;
                            var byId = {};
                            self.blocks.forEach(function (b) { byId[b.id] = b; });
                            self.blocks = order.map(function (id) { return byId[id]; });
                            self.persistOrder();
                        },
                    });
                }
                this.$watch('editMode', function (value) {
                    if (self.sortable) self.sortable.option('disabled', !value);
                });
            },

            pickType(type) {
                this.form.block_type = type;
                if (type === 'shortcut' && this.form.data.icon === '') this.form.data.icon = 'link';
            },

            applyPage(url) {
                if (!url) return;
                this.form.data.url = url;
                var page = this.pages.find(function (p) { return p.url === url; });
                if (page && (!this.form.title || this.form.title.trim() === '')) {
                    this.form.title = page.label;
                }
            },

            openDialog() {
                this.editingId = 0;
                this.form = { block_type: 'shortcut', title: '', size: 1, data: { url: '', icon: 'link', text: '', entity: '', max_rows: 5 } };
                this.dialogOpen = true;
            },

            editBlock(block) {
                this.editingId = block.id;
                this.form = {
                    block_type: block.block_type,
                    title: block.title || '',
                    size: block.size || 1,
                    data: Object.assign({ url: '', icon: 'link', text: '', entity: '', max_rows: 5 }, block.data || {}),
                };
                this.dialogOpen = true;
            },

            async saveBlock() {
                var payload = {
                    id: this.editingId,
                    block_type: this.form.block_type,
                    title: this.form.title,
                    size: this.form.size,
                    data: this.form.data,
                };

                try {
                    var res = await fetch(this.urls.saveBlock, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify(payload),
                    });
                    var json = await res.json();
                    if (json.status !== 'ok') { alert(json.message || 'Error'); return; }

                    if (this.editingId === 0) {
                        this.blocks.push(json.block);
                    } else {
                        var index = this.blocks.findIndex((b) => b.id === json.block.id);
                        if (index >= 0) this.blocks[index] = json.block;
                    }
                    this.dialogOpen = false;
                } catch (e) {
                    alert('<?= esc($w['save_failed'] ?? 'Save failed.') ?>');
                }
            },

            async deleteBlock(id) {
                if (!confirm('<?= esc($w['delete_confirm'] ?? 'Delete this block?') ?>')) return;

                try {
                    var res = await fetch(this.urls.deleteBlock, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({ id: id }),
                    });
                    var json = await res.json();
                    if (json.status === 'ok') {
                        this.blocks = this.blocks.filter((b) => b.id !== id);
                    }
                } catch (e) {
                    alert('<?= esc($w['delete_failed'] ?? 'Delete failed.') ?>');
                }
            },

            async persistOrder() {
                try {
                    await fetch(this.urls.reorder, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({ ids: this.blocks.map((b) => b.id) }),
                    });
                } catch (e) {
                    // Non-critical; order will re-sync on next load.
                }
            },

            async saveColumns() {
                try {
                    await fetch(this.urls.save, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({ columns: this.workspace.columns }),
                    });
                } catch (e) {
                    alert('<?= esc($w['save_failed'] ?? 'Save failed.') ?>');
                }
            },
        };
    }
</script>
