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
    <link rel="stylesheet" href="<?= base_url('assets/volt/claro.css') ?>">
    <script defer src="<?= base_url('assets/volt/claro.js') ?>"></script>
    <script defer src="<?= base_url('assets/vendor/alpinejs/alpine.min.js') ?>"></script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="claro-body">
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
        class="claro-page"
    >
        <div class="claro-page-header">
            <h1 class="claro-page-header__title"><?= esc($cm['heading'] ?? 'Create Module') ?></h1>
            <p class="claro-page-header__subtitle"><?= esc($cm['description'] ?? '') ?></p>
        </div>

        <div style="display:grid;gap:var(--claro-space-m);grid-template-columns:minmax(0,1fr) 280px">
            <div class="claro-card">
                <div class="claro-card__content">
                    <div style="display:grid;gap:var(--claro-space-m);grid-template-columns:repeat(auto-fit,minmax(14rem,1fr))">
                        <div class="claro-form-item" style="margin-bottom:0">
                            <label class="claro-form-item__label"><?= esc($cm['module_name_label'] ?? 'Module Name') ?></label>
                            <input x-model="form.name" type="text" class="claro-input" placeholder="sales">
                        </div>
                        <div class="claro-form-item" style="margin-bottom:0">
                            <label class="claro-form-item__label"><?= esc($cm['label_label'] ?? 'Label') ?></label>
                            <input x-model="form.label" type="text" class="claro-input" placeholder="Sales">
                        </div>
                    </div>
                    <div class="claro-form-actions" style="margin-top:var(--claro-space-l)">
                        <button @click="saveModule()" type="button" class="claro-button claro-button--primary"><?= esc($cm['create_button'] ?? 'Create Module') ?></button>
                        <a href="<?= site_url('desk/entity-builder') ?>" class="claro-button"><?= esc($cm['go_to_builder'] ?? 'Go to Builder') ?></a>
                    </div>
                </div>
            </div>

            <div class="claro-card">
                <div class="claro-card__content">
                    <p style="font-size:var(--claro-font-size-xs);font-weight:700;text-transform:uppercase;letter-spacing:0.18em;color:var(--claro-color-text-light);margin:0 0 var(--claro-space-m)"><?= esc($cm['existing'] ?? 'Existing Modules') ?></p>
                    <div style="display:flex;flex-direction:column;gap:var(--claro-space-xs)">
                        <template x-for="module in modules" :key="module">
                            <div style="border:1px solid var(--claro-gray-200);border-radius:var(--claro-border-radius);padding:var(--claro-space-xs) var(--claro-space-s);font-size:var(--claro-font-size-s)" x-text="module"></div>
                        </template>
                        <div x-show="modules.length === 0" x-cloak style="font-size:var(--claro-font-size-s);color:var(--claro-color-text-light)">
                            <?= esc($cm['empty'] ?? 'No modules yet.') ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div x-show="flash.message" x-cloak style="position:fixed;bottom:var(--claro-space-m);right:var(--claro-space-m);border:1px solid var(--claro-gray-200);border-radius:var(--claro-border-radius);background:var(--claro-color-bg);padding:var(--claro-space-s) var(--claro-space-m);font-size:var(--claro-font-size-s);box-shadow:var(--claro-shadow-dialog)" :style="flash.type === 'error' ? { color: 'var(--claro-color-error)' } : {}">
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
