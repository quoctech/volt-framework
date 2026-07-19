<?php

/** @var array<string, string> $settings */
/** @var array<int, array{code:string, name:string}> $supportedLangs */
/** @var bool|null $saved */
/** @var array $lang */
$settings = $settings ?? [];
$supportedLangs = $supportedLangs ?? [];
$saved = $saved ?? false;
$lang = $lang ?? [];
$sys = $lang['system'] ?? [];
$common = $lang['common'] ?? [];
$timezones = [
    'UTC'                    => 'UTC (Coordinated Universal Time)',
    'Asia/Ho_Chi_Minh'       => 'Asia/Ho_Chi_Minh (UTC+7)',
    'Asia/Bangkok'           => 'Asia/Bangkok (UTC+7)',
    'Asia/Singapore'         => 'Asia/Singapore (UTC+8)',
    'Asia/Tokyo'             => 'Asia/Tokyo (UTC+9)',
    'Asia/Seoul'             => 'Asia/Seoul (UTC+9)',
    'Asia/Shanghai'          => 'Asia/Shanghai (UTC+8)',
    'Asia/Taipei'            => 'Asia/Taipei (UTC+8)',
    'Asia/Kolkata'           => 'Asia/Kolkata (UTC+5:30)',
    'Asia/Jakarta'           => 'Asia/Jakarta (UTC+7)',
    'Europe/London'          => 'Europe/London (UTC+0/+1)',
    'Europe/Paris'           => 'Europe/Paris (UTC+1/+2)',
    'Europe/Berlin'          => 'Europe/Berlin (UTC+1/+2)',
    'America/New_York'       => 'America/New_York (UTC-5/-4)',
    'America/Chicago'        => 'America/Chicago (UTC-6/-5)',
    'America/Denver'         => 'America/Denver (UTC-7/-6)',
    'America/Los_Angeles'    => 'America/Los_Angeles (UTC-8/-7)',
    'Pacific/Auckland'       => 'Pacific/Auckland (UTC+12/+13)',
    'Australia/Sydney'       => 'Australia/Sydney (UTC+10/+11)',
];
$currentLanguage = $settings['language'] ?? 'en';
$currentTimezone = $settings['timezone'] ?? 'UTC';
?>
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900"><?= esc($sys['title'] ?? 'System Settings') ?></h1>
        <p class="mt-1 text-sm text-slate-600"><?= esc($sys['description'] ?? '') ?></p>
    </div>

    <?php if ($saved): ?>
        <div class="rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <?= esc($sys['saved'] ?? 'Settings saved successfully.') ?>
        </div>
    <?php endif; ?>

    <form action="<?= site_url('desk/system-settings/save') ?>" method="post" class="space-y-6">
        <?= csrf_field() ?>

        <section class="rounded border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold text-slate-900"><?= esc($sys['language'] ?? 'Language') ?></h2>
            <p class="mt-1 text-sm text-slate-600"><?= esc($sys['language_hint'] ?? '') ?></p>

            <div class="mt-4">
                <label for="language" class="block text-sm font-medium text-slate-700"><?= esc($sys['language_label'] ?? 'Interface Language') ?></label>
                <select name="language" id="language" class="mt-1 block w-full max-w-sm rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
                    <?php foreach ($supportedLangs as $l): ?>
                        <option value="<?= esc($l['code']) ?>" <?= $l['code'] === $currentLanguage ? 'selected' : '' ?>>
                            <?= esc($l['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </section>

        <section class="rounded border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold text-slate-900"><?= esc($sys['timezone'] ?? 'Timezone') ?></h2>
            <p class="mt-1 text-sm text-slate-600"><?= esc($sys['timezone_hint'] ?? '') ?></p>

            <div class="mt-4">
                <label for="timezone" class="block text-sm font-medium text-slate-700"><?= esc($sys['timezone_label'] ?? 'System Timezone') ?></label>
                <select name="timezone" id="timezone" class="mt-1 block w-full max-w-sm rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
                    <?php foreach ($timezones as $tz => $label): ?>
                        <option value="<?= esc($tz) ?>" <?= $tz === $currentTimezone ? 'selected' : '' ?>>
                            <?= esc($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </section>

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded border border-slate-900 bg-slate-900 px-6 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">
                <?= esc($sys['save'] ?? 'Save Settings') ?>
            </button>
        </div>
    </form>
</div>
