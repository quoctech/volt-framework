<?php

/** @var array<string, mixed> $report */
$report = $report ?? [];
$generatedAt = (string) ($report['generatedAt'] ?? '');
$phpVersion = (string) ($report['phpVersion'] ?? PHP_VERSION);
$extensions = $report['extensions'] ?? [];
$resources = $report['resources'] ?? [];

$lang = \Volt\Core\Config\Lang\LangService::load();
$ss = $lang['system_status_page'] ?? [];

$cpuLabels = [$ss['resource_cpu_load_1m'] ?? '', $ss['resource_cpu_load_5m'] ?? '', $ss['resource_cpu_load_15m'] ?? ''];
$ramLabels = [$ss['resource_ram_total'] ?? '', $ss['resource_ram_available'] ?? '', $ss['resource_ram_used'] ?? ''];
$phpResourceLabels = [$ss['resource_php_memory_usage'] ?? '', $ss['resource_php_peak_memory'] ?? ''];

$cpuItems = [];
$ramItems = [];
$phpResourceItems = [];

foreach ($resources as $item) {
    $label = (string) ($item['label'] ?? '');
    if (in_array($label, $cpuLabels, true)) {
        $cpuItems[] = $item;
    } elseif (in_array($label, $ramLabels, true)) {
        $ramItems[] = $item;
    } elseif (in_array($label, $phpResourceLabels, true)) {
        $phpResourceItems[] = $item;
    }
}
?>
<div>
    <div class="claro-table-toolbar">
        <div class="claro-table-toolbar__left">
            <div class="claro-page-header" style="margin-bottom:0">
                <h1 class="claro-page-header__title"><?= esc($ss['title'] ?? 'System Status') ?></h1>
                <p class="claro-page-header__subtitle">PHP <?= esc($phpVersion) ?> &middot; <?= esc($generatedAt) ?></p>
            </div>
        </div>
    </div>

    <div class="claro-card" style="margin-bottom:var(--claro-space-m)">
        <details style="padding:var(--claro-space-m) var(--claro-space-l)">
            <summary style="cursor:pointer;font-size:var(--claro-font-size-s);font-weight:700;color:var(--claro-color-text);user-select:none">CPU Load</summary>
            <div style="margin-top:var(--claro-space-m);border-top:1px solid var(--claro-gray-100);padding-top:var(--claro-space-m)">
                <?php foreach ($cpuItems as $item): ?>
                <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:var(--claro-font-size-s)">
                    <span style="color:var(--claro-gray-600)"><?= esc($item['label'] ?? '') ?></span>
                    <span style="font-weight:500"><?= esc($item['value'] ?? '') ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </details>
    </div>

    <div class="claro-card" style="margin-bottom:var(--claro-space-m)">
        <details style="padding:var(--claro-space-m) var(--claro-space-l)">
            <summary style="cursor:pointer;font-size:var(--claro-font-size-s);font-weight:700;color:var(--claro-color-text);user-select:none">Memory (RAM)</summary>
            <div style="margin-top:var(--claro-space-m);border-top:1px solid var(--claro-gray-100);padding-top:var(--claro-space-m)">
                <?php foreach ($ramItems as $item): ?>
                <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:var(--claro-font-size-s)">
                    <span style="color:var(--claro-gray-600)"><?= esc($item['label'] ?? '') ?></span>
                    <span style="font-weight:500"><?= esc($item['value'] ?? '') ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </details>
    </div>

    <div class="claro-card" style="margin-bottom:var(--claro-space-m)">
        <details style="padding:var(--claro-space-m) var(--claro-space-l)">
            <summary style="cursor:pointer;font-size:var(--claro-font-size-s);font-weight:700;color:var(--claro-color-text);user-select:none">PHP</summary>
            <div style="margin-top:var(--claro-space-m);border-top:1px solid var(--claro-gray-100);padding-top:var(--claro-space-m)">
                <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:var(--claro-font-size-s)">
                    <span style="color:var(--claro-gray-600)">Version</span>
                    <span style="font-weight:500"><?= esc($phpVersion) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:var(--claro-font-size-s)">
                    <span style="color:var(--claro-gray-600)">Memory Limit</span>
                    <span style="font-weight:500"><?= esc(ini_get('memory_limit') ?: 'n/a') ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:var(--claro-font-size-s)">
                    <span style="color:var(--claro-gray-600)">Max Execution</span>
                    <span style="font-weight:500"><?= esc(ini_get('max_execution_time') ?: 'n/a') ?>s</span>
                </div>
                <?php foreach ($phpResourceItems as $item): ?>
                <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:var(--claro-font-size-s)">
                    <span style="color:var(--claro-gray-600)"><?= esc($item['label'] ?? '') ?></span>
                    <span style="font-weight:500"><?= esc($item['value'] ?? '') ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </details>
    </div>

    <div class="claro-card" style="margin-bottom:var(--claro-space-m)">
        <details style="padding:var(--claro-space-m) var(--claro-space-l)">
            <summary style="cursor:pointer;font-size:var(--claro-font-size-s);font-weight:700;color:var(--claro-color-text);user-select:none">Required Extensions</summary>
            <div style="margin-top:var(--claro-space-m);border-top:1px solid var(--claro-gray-100);padding-top:var(--claro-space-m)">
                <?php foreach ($extensions as $ext): ?>
                <?php $ok = ($ext['value'] ?? '') === ($ss['loaded'] ?? 'Loaded'); ?>
                <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:var(--claro-font-size-s)">
                    <span style="color:var(--claro-gray-600)"><?= esc($ext['label'] ?? '') ?></span>
                    <span style="font-weight:500;color:<?= $ok ? 'var(--claro-color-success)' : 'var(--claro-color-error)' ?>"><?= esc($ext['value'] ?? '') ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </details>
    </div>
</div>
