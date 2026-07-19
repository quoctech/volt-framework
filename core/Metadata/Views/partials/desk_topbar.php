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
    x-data="awesomeBar('<?= $searchUrl ?>')"
    @keydown.window.ctrl.k.prevent="openModal()"
    @keydown.window.cmd.k.prevent="openModal()"
    @keydown.escape.window="closeModal()"
>
    <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3 lg:px-8">
        <div class="flex min-w-0 items-center gap-6">
            <a href="<?= site_url('desk') ?>" class="shrink-0 text-sm font-semibold tracking-wide text-slate-900">
                Volt Desk
            </a>
            <nav class="hidden items-center gap-1 text-sm sm:flex">
                <?php if ($deskActive !== 'desk'): ?>
                    <a
                        href="<?= site_url('desk/entities') ?>"
                        class="rounded px-2.5 py-1.5 <?= $deskActive === 'entities' ? 'bg-slate-100 font-medium text-slate-900' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>"
                    >Entity List</a>
                    <?php if ($isAdmin): ?>
                        <a
                            href="<?= site_url('desk/users') ?>"
                            class="rounded px-2.5 py-1.5 <?= $deskActive === 'users' ? 'bg-slate-100 font-medium text-slate-900' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>"
                        >User List</a>
                        <a
                            href="<?= site_url('desk/roles') ?>"
                            class="rounded px-2.5 py-1.5 <?= $deskActive === 'roles' ? 'bg-slate-100 font-medium text-slate-900' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>"
                        >Role List</a>
                        <a
                            href="<?= site_url('desk/system-status') ?>"
                            class="rounded px-2.5 py-1.5 <?= $deskActive === 'system-status' ? 'bg-slate-100 font-medium text-slate-900' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>"
                        >System Status</a>
                        <a
                            href="<?= site_url('desk/create-module') ?>"
                            class="rounded px-2.5 py-1.5 <?= $deskActive === 'create-module' ? 'bg-slate-100 font-medium text-slate-900' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>"
                        >Create Module</a>
                        <a
                            href="<?= site_url('desk/entity-builder') ?>"
                            class="rounded px-2.5 py-1.5 <?= $deskActive === 'entity-builder' ? 'bg-slate-100 font-medium text-slate-900' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>"
                        >Entity Builder</a>
                    <?php endif; ?>
                <?php endif; ?>
            </nav>
        </div>

        <div class="flex items-center gap-3">
            <button
                type="button"
                @click="openModal()"
                class="flex items-center gap-2 rounded border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-sm text-slate-500 hover:bg-slate-100 hover:text-slate-700"
            >
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                </svg>
                <span class="hidden sm:inline">Tìm kiếm...</span>
                <kbd class="hidden items-center gap-0.5 rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[10px] font-medium text-slate-400 sm:inline-flex">
                    <span>Ctrl</span><span>K</span>
                </kbd>
            </button>

            <!-- Modal backdrop -->
            <div x-show="modalOpen" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-[100] bg-black/30" @click="closeModal()"></div>

            <!-- Modal -->
            <div
                x-show="modalOpen" x-cloak
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-2 scale-95"
                class="fixed inset-0 z-[110] pointer-events-none"
            >
                <div class="flex h-full w-full items-start justify-center pt-[15vh]">
                    <div class="pointer-events-auto flex w-full max-w-xl flex-col border border-slate-200 bg-white shadow-xl">
                        <div class="flex items-center border-b border-slate-200 px-4">
                            <svg class="h-5 w-5 shrink-0 text-slate-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                            </svg>
                            <input
                                x-ref="modalSearchInput"
                                x-model="query"
                                @input.debounce.300ms="search()"
                                @keydown.down.prevent="nextResult()"
                                @keydown.up.prevent="prevResult()"
                                @keydown.enter.prevent="goResult()"
                                type="text"
                                class="w-full border-0 py-4 pl-3 pr-4 text-base outline-none placeholder:text-slate-400"
                                placeholder="Tìm kiếm..."
                                autocomplete="off"
                            >
                            <kbd class="shrink-0 rounded border border-slate-200 px-1.5 py-0.5 text-[10px] text-slate-400">ESC</kbd>
                        </div>

                        <div class="max-h-72 overflow-y-auto">
                            <template x-for="(item, idx) in results" :key="item.id">
                                <a
                                    :href="item.route"
                                    :class="idx === activeIndex ? 'bg-slate-100' : ''"
                                    class="flex items-center border-b border-slate-100 px-4 py-2.5 text-sm last:border-0 hover:bg-slate-50"
                                    @mouseenter="activeIndex = idx"
                                >
                                    <div class="min-w-0 flex-1">
                                        <span class="font-medium text-slate-900" x-text="item.label"></span>
                                        <span class="ml-2 text-xs text-slate-400" x-text="item.item_type === 'entity' ? item.module : ''"></span>
                                        <p class="truncate text-xs text-slate-500" x-text="item.description"></p>
                                    </div>
                                </a>
                            </template>

                            <div x-show="query !== '' && results.length === 0 && !loading" class="px-4 py-6 text-center text-sm text-slate-400">
                                Không tìm thấy kết quả.
                            </div>

                            <div x-show="query === '' && results.length === 0" class="px-4 py-6 text-center text-sm text-slate-400">
                                Gõ để bắt đầu tìm kiếm...
                            </div>
                        </div>

                        <div class="border-t border-slate-100 px-4 py-2 text-xs text-slate-400">
                            <span>&uarr;&darr; Điều hướng &middot; Enter chọn &middot; Esc đóng</span>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="relative"
                x-data="{ open: false }"
                @keydown.escape.window="open = false"
                @click.outside="open = false"
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

</header>

<script>
    function awesomeBar(searchUrl) {
        return {
            query: '',
            results: [],
            activeIndex: -1,
            modalOpen: false,
            loading: false,
            async search() {
                const q = this.query.trim();
                if (q === '') {
                    this.results = [];
                    this.activeIndex = -1;
                    return;
                }
                this.loading = true;
                try {
                    const resp = await fetch(`${searchUrl}?q=${encodeURIComponent(q)}`, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await resp.json();
                    this.results = data.results || [];
                    this.activeIndex = -1;
                } catch {
                    this.results = [];
                } finally {
                    this.loading = false;
                }
            },
            nextResult() {
                if (this.results.length === 0) return;
                this.activeIndex = (this.activeIndex + 1) % this.results.length;
            },
            prevResult() {
                if (this.results.length === 0) return;
                this.activeIndex = this.activeIndex <= 0 ? this.results.length - 1 : this.activeIndex - 1;
            },
            goResult() {
                if (this.activeIndex >= 0 && this.activeIndex < this.results.length) {
                    window.location.href = this.results[this.activeIndex].route;
                }
            },
            openModal() {
                this.modalOpen = true;
                this.query = '';
                this.results = [];
                this.activeIndex = -1;
                this.$nextTick(() => {
                    this.$refs.modalSearchInput?.focus();
                });
            },
            closeModal() {
                this.modalOpen = false;
                this.query = '';
                this.results = [];
                this.activeIndex = -1;
            },
        };
    }
</script>
