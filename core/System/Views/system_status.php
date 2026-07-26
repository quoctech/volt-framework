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
<div class="space-y-3">
    <div class="rounded border border-slate-200 bg-white px-5 py-4">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-slate-900"><?= esc($ss['title'] ?? 'System Status') ?></h1>
            <span class="text-xs text-slate-500"><?= esc($generatedAt) ?></span>
        </div>
        <div class="mt-1 text-sm text-slate-500">PHP <?= esc($phpVersion) ?></div>
    </div>

    <details class="group rounded border border-slate-200 bg-white open:border-slate-300">
        <summary class="flex cursor-pointer items-center gap-2 px-5 py-3 text-sm font-semibold text-slate-900 select-none">
            <svg class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-90" viewBox="0 0 16 16" fill="currentColor"><path d="M6 4l4 4-4 4V4z"/></svg>
            CPU Load
        </summary>
        <div class="border-t border-slate-100 px-5 py-3">
            <?php foreach ($cpuItems as $item): ?>
            <div class="flex justify-between py-1 text-sm">
                <span class="text-slate-600"><?= esc($item['label'] ?? '') ?></span>
                <span class="font-medium text-slate-900"><?= esc($item['value'] ?? '') ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </details>

    <details class="group rounded border border-slate-200 bg-white open:border-slate-300">
        <summary class="flex cursor-pointer items-center gap-2 px-5 py-3 text-sm font-semibold text-slate-900 select-none">
            <svg class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-90" viewBox="0 0 16 16" fill="currentColor"><path d="M6 4l4 4-4 4V4z"/></svg>
            Memory (RAM)
        </summary>
        <div class="border-t border-slate-100 px-5 py-3">
            <?php foreach ($ramItems as $item): ?>
            <div class="flex justify-between py-1 text-sm">
                <span class="text-slate-600"><?= esc($item['label'] ?? '') ?></span>
                <span class="font-medium text-slate-900"><?= esc($item['value'] ?? '') ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </details>

    <details class="group rounded border border-slate-200 bg-white open:border-slate-300">
        <summary class="flex cursor-pointer items-center gap-2 px-5 py-3 text-sm font-semibold text-slate-900 select-none">
            <svg class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-90" viewBox="0 0 16 16" fill="currentColor"><path d="M6 4l4 4-4 4V4z"/></svg>
            PHP
        </summary>
        <div class="border-t border-slate-100 px-5 py-3">
            <div class="flex justify-between py-1 text-sm">
                <span class="text-slate-600">Version</span>
                <span class="font-medium text-slate-900"><?= esc($phpVersion) ?></span>
            </div>
            <div class="flex justify-between py-1 text-sm">
                <span class="text-slate-600">Memory Limit</span>
                <span class="font-medium text-slate-900"><?= esc(ini_get('memory_limit') ?: 'n/a') ?></span>
            </div>
            <div class="flex justify-between py-1 text-sm">
                <span class="text-slate-600">Max Execution</span>
                <span class="font-medium text-slate-900"><?= esc(ini_get('max_execution_time') ?: 'n/a') ?>s</span>
            </div>
            <?php foreach ($phpResourceItems as $item): ?>
            <div class="flex justify-between py-1 text-sm">
                <span class="text-slate-600"><?= esc($item['label'] ?? '') ?></span>
                <span class="font-medium text-slate-900"><?= esc($item['value'] ?? '') ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </details>

    <details class="group rounded border border-slate-200 bg-white open:border-slate-300">
        <summary class="flex cursor-pointer items-center gap-2 px-5 py-3 text-sm font-semibold text-slate-900 select-none">
            <svg class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-90" viewBox="0 0 16 16" fill="currentColor"><path d="M6 4l4 4-4 4V4z"/></svg>
            Required Extensions
        </summary>
        <div class="border-t border-slate-100 px-5 py-3">
            <?php foreach ($extensions as $ext): ?>
            <?php $ok = ($ext['value'] ?? '') === ($ss['loaded'] ?? 'Loaded'); ?>
            <div class="flex justify-between py-1 text-sm">
                <span class="text-slate-600"><?= esc($ext['label'] ?? '') ?></span>
                <span class="font-medium <?= $ok ? 'text-emerald-600' : 'text-rose-600' ?>"><?= esc($ext['value'] ?? '') ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </details>
</div>
