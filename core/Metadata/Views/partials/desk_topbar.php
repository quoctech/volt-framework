<?php

/**
 * Shared Desk top bar: brand, awesome bar trigger, user dropdown.
 *
 * @var string $currentUserName
 * @var bool   $isAdmin
 * @var string $deskActive  desk|entities|create-module|entity-builder|profile|roles|users|system-status|system-settings
 */
$currentUserName = $currentUserName ?? '';
$isAdmin = $isAdmin ?? false;
$deskActive = $deskActive ?? 'desk';
$initial = $currentUserName !== '' ? mb_strtoupper(mb_substr($currentUserName, 0, 1)) : '?';
$searchUrl = site_url('api/awesome-bar/search');
$currentActor = service('voltAuth')->currentUser();
$permissionResolver = service('voltPermissionResolver');
$canViewErrorLogs = $currentActor !== null && ($currentActor->isAdmin() || $permissionResolver->can('error_logs', 'read', null, null, $currentActor));

$lang = \Volt\Core\Config\Lang\LangService::load();
$nav = $lang['nav'] ?? [];
$common = $lang['common'] ?? [];
?>
<header
    class="claro-topbar"
    x-data="awesomeBar('<?= esc($searchUrl, 'js') ?>')"
    @keydown.window.ctrl.k.prevent="openModal()"
    @keydown.window.cmd.k.prevent="openModal()"
    @keydown.window.escape="closeModal()"
>
    <a href="<?= site_url('desk') ?>" class="claro-topbar__brand">
        Volt Desk
    </a>

    <?php if ($isAdmin || $canViewErrorLogs): ?>
        <nav class="claro-topbar__nav">
            <?php if ($isAdmin): ?>
                <a href="<?= site_url('desk/system-settings') ?>" class="claro-topbar__link <?= $deskActive === 'system-settings' ? 'claro-topbar__link--active' : '' ?>">
                    <?= esc($nav['system_settings'] ?? 'System Settings') ?>
                </a>
                <a href="<?= site_url('desk/system-status') ?>" class="claro-topbar__link <?= $deskActive === 'system-status' ? 'claro-topbar__link--active' : '' ?>">
                    <?= esc($nav['system_status'] ?? 'System Status') ?>
                </a>
                <a href="<?= site_url('desk/pages') ?>" class="claro-topbar__link <?= $deskActive === 'pages' ? 'claro-topbar__link--active' : '' ?>">
                    Pages
                </a>
                <a href="<?= site_url('desk/reports') ?>" class="claro-topbar__link <?= $deskActive === 'reports' ? 'claro-topbar__link--active' : '' ?>">
                    Reports
                </a>
                <a href="<?= site_url('desk/tenants') ?>" class="claro-topbar__link <?= $deskActive === 'tenants' ? 'claro-topbar__link--active' : '' ?>">
                    <?= esc($nav['tenants'] ?? 'Tenants') ?>
                </a>
            <?php endif; ?>
            <?php if ($canViewErrorLogs): ?>
                <a href="<?= site_url('desk/error-logs') ?>" class="claro-topbar__link <?= $deskActive === 'error-logs' ? 'claro-topbar__link--active' : '' ?>">
                    <?= esc($nav['error_logs'] ?? 'Error Logs') ?>
                </a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

    <div class="claro-topbar__right">
        <button
            type="button"
            @click="openModal()"
            style="display:inline-flex;align-items:center;gap:var(--claro-space-xs);padding:var(--claro-space-xs) var(--claro-space-s);border:1px solid rgba(255,255,255,0.2);border-radius:var(--claro-border-radius);background:rgba(255,255,255,0.08);color:rgba(255,255,255,0.85);font-size:var(--claro-font-size-s);cursor:pointer"
        >
            <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
            </svg>
            <span class="hidden sm:inline"><?= esc($common['search_or_jump'] ?? 'Search or jump to...') ?></span>
            <kbd style="padding:1px 6px;border:1px solid rgba(255,255,255,0.2);border-radius:2px;font-size:10px;font-weight:500">Ctrl K</kbd>
        </button>

        <div
            class="claro-dropdown"
            x-data="claroDropdown"
            @click.outside="close()"
            @keydown.escape.window="close()"
        >
            <button
                type="button"
                class="claro-topbar__link"
                @click="toggle()"
                :aria-expanded="open.toString()"
                aria-haspopup="menu"
            >
                <span style="display:inline-flex;align-items:center;justify-content:center;width:1.75rem;height:1.75rem;border-radius:50%;background:rgba(255,255,255,0.2);font-size:var(--claro-font-size-xs);font-weight:700;color:#ffffff;margin-right:var(--claro-space-xs)">
                    <?= esc($initial) ?>
                </span>
                <span class="hidden sm:inline" style="max-width:10rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= esc($currentUserName) ?></span>
                <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd" />
                </svg>
            </button>

            <div
                x-cloak
                x-show="open"
                x-transition.origin.top.right
                class="claro-dropdown__menu"
                :class="{ 'claro-dropdown__menu--open': open }"
                role="menu"
            >
                <div style="padding:var(--claro-space-xs) var(--claro-space-m);border-bottom:1px solid var(--claro-gray-100)">
                    <p style="font-size:var(--claro-font-size-s);font-weight:500;margin:0"><?= esc($currentUserName) ?></p>
                    <p style="font-size:var(--claro-font-size-xs);color:var(--claro-color-text-light);margin:0"><?= $isAdmin ? esc($common['admin'] ?? 'Admin') : esc($common['user'] ?? 'User') ?></p>
                </div>
                <a
                    href="<?= site_url('desk/profile') ?>"
                    class="claro-dropdown__item"
                    role="menuitem"
                    @click="close()"
                ><?= esc($nav['profile'] ?? 'Edit Profile') ?></a>
                <div class="claro-dropdown__divider"></div>
                <form action="<?= site_url('logout') ?>" method="post">
                    <?= csrf_field() ?>
                    <button
                        type="submit"
                        class="claro-dropdown__item claro-dropdown__item--danger"
                        role="menuitem"
                    ><?= esc($nav['logout'] ?? 'Logout') ?></button>
                </form>
            </div>
        </div>
    </div>

    <div
        x-cloak
        x-show="modalOpen"
        class="claro-dialog"
        aria-modal="true"
        role="dialog"
    >
        <div class="claro-dialog__overlay" @click="closeModal()"></div>

        <div
            x-show="modalOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="claro-dialog__panel"
            style="max-width:40rem"
            @click.stop
        >
            <div class="claro-awesome-bar__header">
                <div class="claro-awesome-bar__input-wrapper">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" style="flex-shrink:0;color:var(--claro-gray-500)">
                        <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                    </svg>
                    <input
                        x-ref="modalSearchInput"
                        x-model="query"
                        @input.debounce.180ms="search()"
                        @keydown.down.prevent="nextResult()"
                        @keydown.up.prevent="prevResult()"
                        @keydown.enter.prevent="goResult()"
                        type="text"
                        class="claro-awesome-bar__input"
                        placeholder="<?= esc($common['search_placeholder'] ?? 'Search documents, pages, modules...') ?>"
                        autocomplete="off"
                    >
                    <div style="display:flex;align-items:center;gap:var(--claro-space-xs)">
                        <div x-show="loading" class="claro-awesome-bar__spinner"></div>
                        <kbd class="claro-awesome-bar__kbd">ESC</kbd>
                    </div>
                </div>
            </div>

            <div class="claro-awesome-bar__section">
                <span x-text="query.trim() === '' ? '<?= esc($common['quick_access'] ?? 'Quick Access') ?>' : '<?= esc($common['search_results'] ?? 'Search Results') ?>'"></span>
                <span x-text="results.length > 0 ? `${results.length} <?= esc($common['items'] ?? 'item(s)') ?>` : '<?= esc($common['no_selection'] ?? 'No selection') ?>'"></span>
            </div>

            <div class="claro-awesome-bar__results">
                <template x-for="(item, idx) in results" :key="item.item_type + '-' + item.item_name + '-' + idx">
                    <a
                        :href="item.route"
                        class="claro-awesome-bar__result"
                        :class="{ 'claro-awesome-bar__result--active': idx === activeIndex }"
                        @mouseenter="activeIndex = idx"
                        @click="closeModal()"
                    >
                        <div class="claro-awesome-bar__result-icon" x-text="item.item_type === 'entity' ? '<?= esc($common['document'] ?? 'Doc') ?>' : '<?= esc($common['page'] ?? 'Page') ?>'"></div>

                        <div class="claro-awesome-bar__result-body">
                            <div style="display:flex;align-items:center;gap:var(--claro-space-xs)">
                                <span class="claro-awesome-bar__result-label" x-text="item.label"></span>
                                <span x-show="item.is_core" class="claro-awesome-bar__result-badge"><?= esc($common['core'] ?? 'Core') ?></span>
                            </div>

                            <p class="claro-awesome-bar__result-desc" x-text="item.description || '<?= esc($common['no_description'] ?? 'No description.') ?>'"></p>

                            <div class="claro-awesome-bar__result-meta">
                                <span x-text="item.item_type === 'entity' ? '<?= esc($common['document'] ?? 'Document') ?>' : '<?= esc($common['page'] ?? 'Desk page') ?>'"></span>
                                <span>&middot;</span>
                                <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap" x-text="item.module || item.route"></span>
                            </div>
                        </div>

                        <div class="claro-awesome-bar__result-enter">↵</div>
                    </a>
                </template>

                <div
                    x-show="loading && results.length === 0"
                    style="padding:var(--claro-space-xl) var(--claro-space-l);text-align:center;font-size:var(--claro-font-size-s);color:var(--claro-gray-400)"
                ><?= esc($common['loading'] ?? 'Loading...') ?></div>

                <div
                    x-show="query.trim() !== '' && results.length === 0 && !loading"
                    style="padding:var(--claro-space-xl) var(--claro-space-l);text-align:center"
                >
                    <p style="font-size:var(--claro-font-size-s);font-weight:500;color:var(--claro-gray-700);margin:0"><?= esc($common['no_results'] ?? 'No matching results found.') ?></p>
                    <p style="font-size:var(--claro-font-size-xs);color:var(--claro-gray-400);margin:var(--claro-space-xs) 0 0"><?= esc($common['no_results_hint'] ?? '') ?></p>
                </div>

                <div
                    x-show="query.trim() === '' && results.length === 0 && !loading"
                    style="padding:var(--claro-space-xl) var(--claro-space-l);text-align:center"
                >
                    <p style="font-size:var(--claro-font-size-s);font-weight:500;color:var(--claro-gray-700);margin:0"><?= esc($common['start_typing'] ?? 'Start typing to search quickly.') ?></p>
                    <p style="font-size:var(--claro-font-size-xs);color:var(--claro-gray-400);margin:var(--claro-space-xs) 0 0"><?= esc($common['start_typing_hint'] ?? '') ?></p>
                </div>
            </div>

            <div class="claro-awesome-bar__footer">
                <span>&uarr;&darr; <?= esc($common['navigate'] ?? 'Navigate') ?></span>
                <span><?= esc($common['open'] ?? 'Enter to open') ?></span>
                <span><?= esc($common['close'] ?? 'Esc to close') ?></span>
            </div>
        </div>
    </div>
</header>

<style>
@keyframes claro-spin {
    to { transform: rotate(360deg); }
}
</style>

<script>
    function awesomeBar(searchUrl) {
        return {
            query: '',
            results: [],
            activeIndex: -1,
            modalOpen: false,
            loading: false,
            abortController: null,
            currentRequestId: 0,
            async search() {
                return this.fetchResults(this.query.trim());
            },
            async fetchResults(query) {
                const requestId = ++this.currentRequestId;

                if (this.abortController) {
                    this.abortController.abort();
                }

                this.abortController = new AbortController();
                this.loading = true;

                try {
                    const response = await fetch(`${searchUrl}?q=${encodeURIComponent(query)}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: this.abortController.signal,
                    });

                    if (!response.ok) {
                        throw new Error('Search failed');
                    }

                    const data = await response.json();

                    if (requestId !== this.currentRequestId) {
                        return;
                    }

                    this.results = Array.isArray(data.results) ? data.results : [];
                    this.activeIndex = this.results.length > 0 ? 0 : -1;
                } catch (error) {
                    if (error.name === 'AbortError') {
                        return;
                    }

                    this.results = [];
                    this.activeIndex = -1;
                } finally {
                    if (requestId === this.currentRequestId) {
                        this.loading = false;
                    }
                }
            },
            nextResult() {
                if (this.results.length === 0) {
                    return;
                }

                this.activeIndex = (this.activeIndex + 1) % this.results.length;
            },
            prevResult() {
                if (this.results.length === 0) {
                    return;
                }

                this.activeIndex = this.activeIndex <= 0 ? this.results.length - 1 : this.activeIndex - 1;
            },
            goResult() {
                if (this.activeIndex >= 0 && this.activeIndex < this.results.length) {
                    window.location.href = this.results[this.activeIndex].route;
                }
            },
            openModal() {
                if (this.modalOpen) {
                    this.$nextTick(() => {
                        this.$refs.modalSearchInput?.focus();
                        this.$refs.modalSearchInput?.select();
                    });
                    return;
                }

                this.modalOpen = true;
                this.query = '';
                this.results = [];
                this.activeIndex = -1;
                document.body.classList.add('overflow-hidden');

                this.$nextTick(() => {
                    this.$refs.modalSearchInput?.focus();
                    this.fetchResults('');
                });
            },
            closeModal() {
                if (!this.modalOpen) {
                    return;
                }

                this.modalOpen = false;
                this.query = '';
                this.results = [];
                this.activeIndex = -1;
                this.loading = false;

                if (this.abortController) {
                    this.abortController.abort();
                    this.abortController = null;
                }

                document.body.classList.remove('overflow-hidden');
            },
        };
    }
</script>
