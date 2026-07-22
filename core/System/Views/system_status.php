<?php

/** @var array<string, mixed> $report */
$report = $report ?? [];
$summary = $report['summary'] ?? ['ok' => 0, 'warning' => 0, 'error' => 0, 'total' => 0];
$checks = $report['checks'] ?? [];
$resources = $report['resources'] ?? [];
$overallStatus = (string) ($report['overallStatus'] ?? 'warning');
$generatedAt = (string) ($report['generatedAt'] ?? '');

$lang = \Volt\Core\Config\Lang\LangService::load();
$ss = $lang['system_status_page'] ?? [];

$statusClasses = [
    'ok' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
    'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
    'error' => 'border-rose-200 bg-rose-50 text-rose-800',
];

$rowClasses = [
    'ok' => 'border-emerald-100 bg-emerald-50/60',
    'warning' => 'border-amber-100 bg-amber-50/60',
    'error' => 'border-rose-100 bg-rose-50/60',
];

$statusLabels = [
    'ok' => $ss['ok'] ?? 'OK',
    'warning' => $ss['warning'] ?? 'Warning',
    'error' => $ss['error'] ?? 'Error',
];
?>
<div class="space-y-4">
    <div class="rounded border border-slate-200 bg-white px-5 py-4">
        <div class="flex items-center justify-between gap-4">
            <div class="min-w-0">
                <h1 class="truncate text-xl font-semibold text-slate-900"><?= esc($ss['title'] ?? 'System Status') ?></h1>
            </div>
            <div class="flex shrink-0 items-center gap-3 whitespace-nowrap">
                <span class="inline-flex rounded border px-2.5 py-1 text-xs font-semibold uppercase tracking-wide <?= esc($statusClasses[$overallStatus] ?? $statusClasses['warning']) ?>">
                    <?= esc($statusLabels[$overallStatus] ?? 'Warning') ?>
                </span>
                <span class="text-xs text-slate-500"><?= esc($generatedAt) ?></span>
            </div>
        </div>
    </div>

    <?php if ($resources !== []): ?>
        <div class="grid gap-3 md:grid-cols-4">
            <?php foreach (array_slice($resources, 0, 4) as $item): ?>
                <div class="overflow-hidden rounded border border-slate-200 bg-white px-4 py-3">
                    <div class="truncate text-[11px] uppercase tracking-[0.18em] text-slate-500"><?= esc((string) ($item['label'] ?? '')) ?></div>
                    <div class="truncate text-sm font-semibold text-slate-900"><?= esc((string) ($item['value'] ?? '')) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="grid gap-3 md:grid-cols-4">
        <div class="overflow-hidden rounded border border-slate-200 bg-white px-4 py-3">
            <div class="text-xs uppercase tracking-[0.18em] text-slate-500"><?= esc($ss['checks'] ?? 'Checks') ?></div>
            <div class="mt-1 text-2xl font-semibold text-slate-900"><?= esc((string) ($summary['total'] ?? 0)) ?></div>
        </div>
        <div class="overflow-hidden rounded border border-emerald-200 bg-white px-4 py-3">
            <div class="text-xs uppercase tracking-[0.18em] text-emerald-700"><?= esc($ss['ok'] ?? 'OK') ?></div>
            <div class="mt-1 text-2xl font-semibold text-emerald-900"><?= esc((string) ($summary['ok'] ?? 0)) ?></div>
        </div>
        <div class="overflow-hidden rounded border border-amber-200 bg-white px-4 py-3">
            <div class="text-xs uppercase tracking-[0.18em] text-amber-700"><?= esc($ss['warning'] ?? 'Warning') ?></div>
            <div class="mt-1 text-2xl font-semibold text-amber-900"><?= esc((string) ($summary['warning'] ?? 0)) ?></div>
        </div>
        <div class="overflow-hidden rounded border border-rose-200 bg-white px-4 py-3">
            <div class="text-xs uppercase tracking-[0.18em] text-rose-700"><?= esc($ss['error'] ?? 'Error') ?></div>
            <div class="mt-1 text-2xl font-semibold text-rose-900"><?= esc((string) ($summary['error'] ?? 0)) ?></div>
        </div>
    </div>

    <div class="overflow-x-auto rounded border border-slate-200 bg-white">
        <div class="grid min-w-[980px] grid-cols-[280px_120px_1fr] gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
            <div><?= esc($ss['checks'] ?? 'Checks') ?></div>
            <div><?= esc($ss['status'] ?? 'Status') ?></div>
            <div><?= esc($ss['recommendation'] ?? 'Recommendation') ?></div>
        </div>

        <?php foreach ($checks as $check): ?>
            <?php $status = (string) ($check['status'] ?? 'warning'); ?>
            <div class="grid min-w-[980px] grid-cols-[280px_120px_1fr] gap-3 border-b px-4 py-3 last:border-b-0 <?= esc($rowClasses[$status] ?? $rowClasses['warning']) ?>">
                <div class="min-w-0 overflow-hidden whitespace-nowrap">
                    <div class="truncate text-sm font-medium text-slate-900"><?= esc((string) ($check['title'] ?? 'Untitled check')) ?></div>
                </div>
                <div class="flex items-center whitespace-nowrap">
                    <span class="inline-flex rounded border px-2 py-1 text-[11px] font-semibold uppercase tracking-wide <?= esc($statusClasses[$status] ?? $statusClasses['warning']) ?>">
                        <?= esc($statusLabels[$status] ?? 'Warning') ?>
                    </span>
                </div>
                <div class="min-w-0 overflow-hidden whitespace-nowrap text-sm text-slate-600">
                    <span class="truncate"><?= esc((string) ($check['summary'] ?? '')) ?> <span class="text-slate-400">•</span> <?= esc((string) ($check['recommendation'] ?? '')) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
