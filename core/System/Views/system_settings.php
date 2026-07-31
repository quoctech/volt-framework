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
<div>
    <div class="claro-page-header">
        <h1 class="claro-page-header__title"><?= esc($sys['title'] ?? 'System Settings') ?></h1>
        <p class="claro-page-header__subtitle"><?= esc($sys['description'] ?? '') ?></p>
    </div>

    <?php if ($saved): ?>
        <div class="claro-message claro-message--status"><?= esc($sys['saved'] ?? 'Settings saved successfully.') ?></div>
    <?php endif; ?>

    <form action="<?= site_url('desk/system-settings/save') ?>" method="post">
        <?= csrf_field() ?>

        <div class="claro-card" style="margin-bottom:var(--claro-space-l)">
            <div class="claro-card__content">
                <h2 style="font-size:var(--claro-font-size-h5);font-weight:700;margin:0 0 var(--claro-space-xs)"><?= esc($sys['language'] ?? 'Language') ?></h2>
                <p style="font-size:var(--claro-font-size-s);color:var(--claro-color-text-light);margin:0 0 var(--claro-space-m)"><?= esc($sys['language_hint'] ?? '') ?></p>
                <div class="claro-form-item" style="margin-bottom:0">
                    <label class="claro-form-item__label" for="language"><?= esc($sys['language_label'] ?? 'Interface Language') ?></label>
                    <select name="language" id="language" class="claro-select" style="max-width:24rem">
                        <?php foreach ($supportedLangs as $l): ?>
                            <option value="<?= esc($l['code']) ?>" <?= $l['code'] === $currentLanguage ? 'selected' : '' ?>><?= esc($l['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="claro-card" style="margin-bottom:var(--claro-space-l)">
            <div class="claro-card__content">
                <h2 style="font-size:var(--claro-font-size-h5);font-weight:700;margin:0 0 var(--claro-space-xs)"><?= esc($sys['timezone'] ?? 'Timezone') ?></h2>
                <p style="font-size:var(--claro-font-size-s);color:var(--claro-color-text-light);margin:0 0 var(--claro-space-m)"><?= esc($sys['timezone_hint'] ?? '') ?></p>
                <div class="claro-form-item" style="margin-bottom:0">
                    <label class="claro-form-item__label" for="timezone"><?= esc($sys['timezone_label'] ?? 'System Timezone') ?></label>
                    <select name="timezone" id="timezone" class="claro-select" style="max-width:24rem">
                        <?php foreach ($timezones as $tz => $label): ?>
                            <option value="<?= esc($tz) ?>" <?= $tz === $currentTimezone ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="claro-form-actions">
            <button type="submit" class="claro-button claro-button--primary"><?= esc($sys['save'] ?? 'Save Settings') ?></button>
        </div>
    </form>
</div>
