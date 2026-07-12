<?php

/** @var array<int, string> $modules */
/** @var string $csrfTokenName */
/** @var string $csrfHash */
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Module</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-screen bg-zinc-100 text-zinc-900">
    <main
        x-data="createModuleApp(<?= esc(json_encode([
            'modules' => $modules,
            'saveModuleUrl' => site_url('api/entity-builder/module/save'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>)"
        class="mx-auto max-w-5xl p-4 lg:p-8"
    >
        <div class="mb-6 flex items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">Create Module</h1>
                <p class="mt-1 text-sm text-zinc-500">Module được tạo riêng, sau đó mới dùng trong Entity Builder.</p>
            </div>
            <a href="<?= site_url('desk') ?>" class="border border-zinc-300 bg-white px-4 py-2 text-sm hover:bg-zinc-50">Back to Desk</a>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
            <section class="border border-zinc-300 bg-white p-4">
                <div class="grid gap-3 md:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500">Module Name</span>
                        <input x-model="form.name" type="text" class="w-full border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-500" placeholder="sales">
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500">Label</span>
                        <input x-model="form.label" type="text" class="w-full border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-500" placeholder="Sales">
                    </label>
                </div>

                <div class="mt-4 flex gap-2">
                    <button @click="saveModule()" type="button" class="border border-zinc-900 bg-zinc-900 px-4 py-2 text-sm text-white hover:bg-zinc-700">Create Module</button>
                    <a href="<?= site_url('desk/entity-builder') ?>" class="border border-zinc-300 px-4 py-2 text-sm hover:bg-zinc-50">Go to Builder</a>
                </div>
            </section>

            <aside class="border border-zinc-300 bg-white p-4">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500">Existing Modules</p>
                <div class="mt-3 space-y-2">
                    <template x-for="module in modules" :key="module">
                        <div class="border border-zinc-300 px-3 py-2 text-sm" x-text="module"></div>
                    </template>
                    <div x-show="modules.length === 0" x-cloak class="text-sm text-zinc-500">
                        Chưa có module nào.
                    </div>
                </div>
            </aside>
        </div>

        <div x-show="flash.message" x-cloak class="fixed bottom-4 right-4 border border-zinc-300 bg-white px-4 py-3 text-sm shadow-sm" :class="flash.type === 'error' ? 'text-red-700' : 'text-zinc-800'">
            <span x-text="flash.message"></span>
        </div>
    </main>

    <script>
        function createModuleApp(boot) {
            return {
                modules: boot.modules || [],
                saveModuleUrl: boot.saveModuleUrl,
                form: {
                    name: '',
                    label: '',
                },
                flash: { type: 'info', message: '' },
                requestUrl(url) {
                    const resolved = new URL(String(url || ''), window.location.origin);
                    if (resolved.origin === window.location.origin) {
                        return resolved.toString();
                    }

                    return `${window.location.origin}${resolved.pathname}${resolved.search}${resolved.hash}`;
                },
                async saveModule() {
                    try {
                        const name = this.slugify(this.form.name);
                        if (!name) {
                            throw new Error('Module name is required.');
                        }

                        const response = await fetch(this.requestUrl(this.saveModuleUrl), {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({
                                name,
                                label: this.form.label || this.titleize(name),
                            }),
                        });
                        const result = await response.json();

                        if (!response.ok || result.status !== 'ok') {
                            throw new Error(result.message || 'Module creation failed.');
                        }

                        if (!this.modules.includes(result.data.name)) {
                            this.modules.push(result.data.name);
                            this.modules.sort();
                        }

                        this.form = { name: '', label: '' };
                        this.toast('info', `Created module ${result.data.name}.`);
                    } catch (error) {
                        this.toast('error', error.message || 'Unable to create module.');
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
