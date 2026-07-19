<?php

/** @var array<int, string> $modules */
/** @var bool $isAdmin */
/** @var string $currentUserName */
$isAdmin = $isAdmin ?? true;
$currentUserName = $currentUserName ?? '';
$deskActive = 'create-module';

$lang = \Volt\Core\Config\Lang\LangService::load();
$cm = $lang['create_module'] ?? [];
$c = $lang['common'] ?? [];
$htmlLang = $lang['code'] ?? 'en';
?>
<!doctype html>
<html lang="<?= esc($htmlLang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($cm['title'] ?? 'Create Module · Volt Desk') ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/vendor/tailwindcss/tailwind.min.css') ?>">
    <script defer src="<?= base_url('assets/vendor/alpinejs/alpine.min.js') ?>"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <?= view('Volt\\Core\\Metadata\\Views\\partials\\desk_topbar', compact('currentUserName', 'isAdmin', 'deskActive')) ?>

    <main
        x-data="createModuleApp(<?= esc(json_encode([
            'modules' => $modules,
            'saveModuleUrl' => site_url('api/entity-builder/module/save'),
            'lang' => [
                'nameRequired' => $cm['error_name_required'] ?? 'Module name is required.',
                'creationFailed' => $cm['error_creation_failed'] ?? 'Module creation failed.',
                'unableToCreate' => $cm['error_unable_to_create'] ?? 'Unable to create module.',
                'created' => $cm['success_created'] ?? 'Created module {name}.',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>)"
        class="mx-auto max-w-5xl p-4 lg:p-8"
    >
        <div class="mb-6">
            <h1 class="text-2xl font-semibold"><?= esc($cm['heading'] ?? 'Create Module') ?></h1>
            <p class="mt-1 text-sm text-slate-500"><?= esc($cm['description'] ?? '') ?></p>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
            <section class="border border-zinc-300 bg-white p-4">
                <div class="grid gap-3 md:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500"><?= esc($cm['module_name_label'] ?? 'Module Name') ?></span>
                        <input x-model="form.name" type="text" class="w-full border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-500" placeholder="sales">
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500"><?= esc($cm['label_label'] ?? 'Label') ?></span>
                        <input x-model="form.label" type="text" class="w-full border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-500" placeholder="Sales">
                    </label>
                </div>

                <div class="mt-4 flex gap-2">
                    <button @click="saveModule()" type="button" class="border border-zinc-900 bg-zinc-900 px-4 py-2 text-sm text-white hover:bg-zinc-700"><?= esc($cm['create_button'] ?? 'Create Module') ?></button>
                    <a href="<?= site_url('desk/entity-builder') ?>" class="border border-zinc-300 px-4 py-2 text-sm hover:bg-zinc-50"><?= esc($cm['go_to_builder'] ?? 'Go to Builder') ?></a>
                </div>
            </section>

            <aside class="border border-zinc-300 bg-white p-4">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500"><?= esc($cm['existing'] ?? 'Existing Modules') ?></p>
                <div class="mt-3 space-y-2">
                    <template x-for="module in modules" :key="module">
                        <div class="border border-zinc-300 px-3 py-2 text-sm" x-text="module"></div>
                    </template>
                    <div x-show="modules.length === 0" x-cloak class="text-sm text-zinc-500">
                        <?= esc($cm['empty'] ?? 'No modules yet.') ?>
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
            const lang = boot.lang || {};
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
                            throw new Error(lang.nameRequired);
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
                            throw new Error(result.message || lang.creationFailed);
                        }

                        if (!this.modules.includes(result.data.name)) {
                            this.modules.push(result.data.name);
                            this.modules.sort();
                        }

                        this.form = { name: '', label: '' };
                        this.toast('info', lang.created.replace('{name}', result.data.name));
                    } catch (error) {
                        this.toast('error', error.message || lang.unableToCreate);
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
