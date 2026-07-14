<?php

/**
 * Shared Desk top bar: brand, active nav highlight, user dropdown.
 *
 * @var string $currentUserName
 * @var bool   $isAdmin
 * @var string $deskActive  desk|entities|create-module|entity-builder|profile
 */
$currentUserName = $currentUserName ?? '';
$isAdmin = $isAdmin ?? false;
$deskActive = $deskActive ?? 'desk';
$initial = $currentUserName !== '' ? mb_strtoupper(mb_substr($currentUserName, 0, 1)) : '?';
?>
<header class="border-b border-slate-300 bg-white">
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
</header>
