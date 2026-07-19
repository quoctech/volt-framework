<?php

/**
 * Shared Desk top bar: brand, awesome bar trigger, user dropdown.
 *
 * @var string $currentUserName
 * @var bool   $isAdmin
 * @var string $deskActive  desk|entities|create-module|entity-builder|profile|roles|users|system-status
 */
$currentUserName = $currentUserName ?? '';
$isAdmin = $isAdmin ?? false;
$deskActive = $deskActive ?? 'desk';
$initial = $currentUserName !== '' ? mb_strtoupper(mb_substr($currentUserName, 0, 1)) : '?';
$searchUrl = site_url('api/awesome-bar/search');
?>
<header
    class="border-b border-slate-300 bg-white"
    x-data="awesomeBar('<?= esc($searchUrl, 'js') ?>')"
    @keydown.window.ctrl.k.prevent="openModal()"
    @keydown.window.cmd.k.prevent="openModal()"
    @keydown.window.escape="closeModal()"
>
    <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3 lg:px-8">
        <div class="flex min-w-0 items-center gap-6">
            <a href="<?= site_url('desk') ?>" class="shrink-0 text-sm font-semibold tracking-wide text-slate-900">
                Volt Desk
            </a>
        </div>

        <div class="flex items-center gap-3">
            <button
                type="button"
                @click="openModal()"
                class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm text-slate-500 transition hover:border-slate-300 hover:bg-white hover:text-slate-700"
            >
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                </svg>
                <span class="hidden sm:inline">Search or jump to...</span>
                <kbd class="hidden rounded-full border border-slate-200 bg-white px-2 py-0.5 text-[10px] font-medium text-slate-400 sm:inline-flex">Ctrl K</kbd>
            </button>

            <div
                class="relative"
                x-data="{ open: false }"
                @click.outside="open = false"
                @keydown.escape.window="open = false"
            >
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded border border-slate-300 bg-white px-2.5 py-1.5 text-sm text-slate-800 hover:bg-slate-50"
                    @click="open = !open"
                    :aria-expanded="open.toString()"
                    aria-haspopup="menu"
                >
                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white">
                        <?= esc($initial) ?>
                    </span>
                    <span class="hidden max-w-[10rem] truncate sm:inline"><?= esc($currentUserName) ?></span>
                    <svg class="h-4 w-4 text-slate-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd" />
                    </svg>
                </button>

                <div
                    x-cloak
                    x-show="open"
                    x-transition.origin.top.right
                    class="absolute right-0 z-50 mt-2 w-56 overflow-hidden rounded border border-slate-300 bg-white shadow-lg"
                    role="menu"
                >
                    <div class="border-b border-slate-200 px-3 py-2">
                        <p class="truncate text-sm font-medium text-slate-900"><?= esc($currentUserName) ?></p>
                        <p class="text-xs text-slate-500"><?= $isAdmin ? 'Admin' : 'User' ?></p>
                    </div>
                    <a
                        href="<?= site_url('desk/profile') ?>"
                        class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
                        role="menuitem"
                        @click="open = false"
                    >Edit profile</a>
                    <form action="<?= site_url('logout') ?>" method="post">
                        <?= csrf_field() ?>
                        <button
                            type="submit"
                            class="block w-full px-3 py-2 text-left text-sm text-red-700 hover:bg-red-50"
                            role="menuitem"
                        >Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div
        x-cloak
        x-show="modalOpen"
        class="fixed inset-0 z-[120] flex items-center justify-center p-4 sm:p-6"
        aria-modal="true"
        role="dialog"
    >
        <div
            x-show="modalOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute inset-0 bg-slate-950/45"
            @click="closeModal()"
        ></div>

        <div
            x-show="modalOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative z-[121] flex w-full max-w-2xl flex-col overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-[0_32px_80px_rgba(15,23,42,0.28)]"
            style="will-change: transform, opacity"
            @click.stop
        >
            <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                    <svg class="h-5 w-5 shrink-0 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
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
                        class="w-full border-0 bg-transparent text-base text-slate-900 outline-none placeholder:text-slate-400"
                        placeholder="Search documents, pages, modules..."
                        autocomplete="off"
                    >
                    <div class="flex items-center gap-2">
                        <div
                            x-show="loading"
                            class="h-4 w-4 animate-spin rounded-full border-2 border-slate-200 border-t-slate-500"
                        ></div>
                        <kbd class="rounded-full border border-slate-200 px-2 py-1 text-[10px] font-medium text-slate-400">ESC</kbd>
                    </div>
                </div>
            </div>

            <div class="border-b border-slate-100 bg-white px-4 py-2">
                <div class="flex items-center justify-between text-[11px] uppercase tracking-[0.18em] text-slate-400">
                    <span x-text="query.trim() === '' ? 'Quick Access' : 'Search Results'"></span>
                    <span x-text="results.length > 0 ? `${results.length} item(s)` : 'No selection'"></span>
                </div>
            </div>

            <div class="max-h-[min(60vh,34rem)] overflow-y-auto bg-white">
                <template x-for="(item, idx) in results" :key="item.item_type + '-' + item.item_name + '-' + idx">
                    <a
                        :href="item.route"
                        :class="idx === activeIndex ? 'bg-slate-900 text-white' : 'bg-white text-slate-900'"
                        class="flex items-start gap-3 border-b border-slate-100 px-4 py-3.5 transition last:border-b-0"
                        @mouseenter="activeIndex = idx"
                        @click="closeModal()"
                    >
                        <div
                            :class="idx === activeIndex ? 'border-white/10 bg-white/10 text-white' : 'border-slate-200 bg-slate-50 text-slate-600'"
                            class="mt-0.5 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl border text-xs font-semibold uppercase tracking-wide"
                            x-text="item.item_type === 'entity' ? 'Doc' : 'Page'"
                        ></div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="truncate text-sm font-semibold" x-text="item.label"></span>
                                <span
                                    x-show="item.is_core"
                                    :class="idx === activeIndex ? 'bg-white/10 text-white/80' : 'bg-slate-100 text-slate-500'"
                                    class="rounded-full px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide"
                                >Core</span>
                            </div>

                            <p
                                :class="idx === activeIndex ? 'text-white/70' : 'text-slate-500'"
                                class="mt-1 truncate text-sm"
                                x-text="item.description || 'Không có mô tả.'"
                            ></p>

                            <div
                                :class="idx === activeIndex ? 'text-white/60' : 'text-slate-400'"
                                class="mt-2 flex items-center gap-2 text-xs"
                            >
                                <span x-text="item.item_type === 'entity' ? 'Document' : 'Desk page'"></span>
                                <span>&middot;</span>
                                <span class="truncate" x-text="item.module || item.route"></span>
                            </div>
                        </div>

                        <div
                            :class="idx === activeIndex ? 'text-white/60' : 'text-slate-300'"
                            class="hidden pt-1 text-xs sm:block"
                        >↵</div>
                    </a>
                </template>

                <div
                    x-show="loading && results.length === 0"
                    class="px-4 py-10 text-center text-sm text-slate-400"
                >Đang tải kết quả...</div>

                <div
                    x-show="query.trim() !== '' && results.length === 0 && !loading"
                    class="px-4 py-10 text-center"
                >
                    <p class="text-sm font-medium text-slate-700">Không tìm thấy kết quả phù hợp.</p>
                    <p class="mt-1 text-sm text-slate-400">Thử tìm theo tên entity, module hoặc trang quản trị.</p>
                </div>

                <div
                    x-show="query.trim() === '' && results.length === 0 && !loading"
                    class="px-4 py-10 text-center"
                >
                    <p class="text-sm font-medium text-slate-700">Bắt đầu gõ để tìm nhanh.</p>
                    <p class="mt-1 text-sm text-slate-400">Các trang core và entity gần đây sẽ hiện ở đây.</p>
                </div>
            </div>

            <div class="flex items-center justify-between border-t border-slate-100 bg-slate-50 px-4 py-3 text-xs text-slate-400">
                <span>&uarr;&darr; điều hướng</span>
                <span>Enter mở</span>
                <span>Esc đóng</span>
            </div>
        </div>
    </div>
</header>

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
