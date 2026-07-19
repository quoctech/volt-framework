<?php

/** @var array<string, mixed> $report */
$report = $report ?? [];
$summary = $report['summary'] ?? ['ok' => 0, 'warning' => 0, 'error' => 0, 'total' => 0];
$checks = $report['checks'] ?? [];
$environment = $report['environment'] ?? [];
$statistics = $report['statistics'] ?? [];
$extensions = $report['extensions'] ?? [];
$resources = $report['resources'] ?? [];
$overallStatus = (string) ($report['overallStatus'] ?? 'warning');
$generatedAt = (string) ($report['generatedAt'] ?? '');

$statusClasses = [
    'ok' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
    'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
    'error' => 'border-rose-200 bg-rose-50 text-rose-800',
];

$statusLabels = [
    'ok' => 'OK',
    'warning' => 'Warning',
    'error' => 'Error',
];
?>
<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Trạng Thái hệ thống</h1>
            <p class="mt-1 text-sm text-slate-600">Kiểm tra nhanh tình trạng runtime, database, cache và các thành phần cốt lõi của Volt.</p>
        </div>
        <div class="rounded border px-3 py-2 text-sm <?= esc($statusClasses[$overallStatus] ?? $statusClasses['warning']) ?>">
            <div class="font-semibold"><?= esc($statusLabels[$overallStatus] ?? 'Warning') ?></div>
            <div class="text-xs">Cập nhật lúc <?= esc($generatedAt) ?></div>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded border border-slate-200 bg-white p-4">
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Checks</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900"><?= esc((string) ($summary['total'] ?? 0)) ?></div>
            <div class="mt-1 text-sm text-slate-500">Tổng số kiểm tra hệ thống</div>
        </div>
        <div class="rounded border border-emerald-200 bg-emerald-50 p-4">
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">OK</div>
            <div class="mt-2 text-3xl font-semibold text-emerald-900"><?= esc((string) ($summary['ok'] ?? 0)) ?></div>
            <div class="mt-1 text-sm text-emerald-800">Thành phần hoạt động ổn định</div>
        </div>
        <div class="rounded border border-amber-200 bg-amber-50 p-4">
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Warning</div>
            <div class="mt-2 text-3xl font-semibold text-amber-900"><?= esc((string) ($summary['warning'] ?? 0)) ?></div>
            <div class="mt-1 text-sm text-amber-800">Cần theo dõi hoặc tối ưu thêm</div>
        </div>
        <div class="rounded border border-rose-200 bg-rose-50 p-4">
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-rose-700">Error</div>
            <div class="mt-2 text-3xl font-semibold text-rose-900"><?= esc((string) ($summary['error'] ?? 0)) ?></div>
            <div class="mt-1 text-sm text-rose-800">Cần xử lý trước khi vận hành</div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[2fr_1fr]">
        <div class="space-y-4">
            <?php foreach ($checks as $check): ?>
                <?php
                $status = (string) ($check['status'] ?? 'warning');
                $details = is_array($check['details'] ?? null) ? $check['details'] : [];
                ?>
                <section class="rounded border border-slate-200 bg-white p-5">
                    <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900"><?= esc((string) ($check['title'] ?? 'Untitled check')) ?></h2>
                            <p class="mt-1 text-sm text-slate-600"><?= esc((string) ($check['summary'] ?? '')) ?></p>
                        </div>
                        <span class="inline-flex rounded border px-2.5 py-1 text-xs font-semibold uppercase tracking-wide <?= esc($statusClasses[$status] ?? $statusClasses['warning']) ?>">
                            <?= esc($statusLabels[$status] ?? 'Warning') ?>
                        </span>
                    </div>

                    <?php if ($details !== []): ?>
                        <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                            <?php foreach ($details as $label => $value): ?>
                                <div class="rounded bg-slate-50 px-3 py-2">
                                    <dt class="text-xs uppercase tracking-wide text-slate-500"><?= esc((string) $label) ?></dt>
                                    <dd class="mt-1 break-all text-sm text-slate-800"><?= esc((string) $value) ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    <?php endif; ?>

                    <p class="mt-4 text-sm text-slate-600">
                        <span class="font-medium text-slate-900">Khuyến nghị:</span>
                        <?= esc((string) ($check['recommendation'] ?? '')) ?>
                    </p>
                </section>
            <?php endforeach; ?>
        </div>

        <div class="space-y-4">
            <section class="rounded border border-slate-200 bg-white p-5">
                <h2 class="text-lg font-semibold text-slate-900">Tài nguyên hiện tại</h2>
                <dl class="mt-4 space-y-3">
                    <?php foreach ($resources as $item): ?>
                        <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3 last:border-b-0 last:pb-0">
                            <dt class="text-sm text-slate-500"><?= esc((string) ($item['label'] ?? '')) ?></dt>
                            <dd class="text-right text-sm font-medium text-slate-900"><?= esc((string) ($item['value'] ?? '')) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </section>

            <section class="rounded border border-slate-200 bg-white p-5">
                <h2 class="text-lg font-semibold text-slate-900">Môi trường</h2>
                <dl class="mt-4 space-y-3">
                    <?php foreach ($environment as $item): ?>
                        <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3 last:border-b-0 last:pb-0">
                            <dt class="text-sm text-slate-500"><?= esc((string) ($item['label'] ?? '')) ?></dt>
                            <dd class="text-right text-sm font-medium text-slate-900"><?= esc((string) ($item['value'] ?? '')) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </section>

            <section class="rounded border border-slate-200 bg-white p-5">
                <h2 class="text-lg font-semibold text-slate-900">Thống kê hệ thống</h2>
                <dl class="mt-4 space-y-3">
                    <?php foreach ($statistics as $item): ?>
                        <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3 last:border-b-0 last:pb-0">
                            <dt class="text-sm text-slate-500"><?= esc((string) ($item['label'] ?? '')) ?></dt>
                            <dd class="text-right text-sm font-medium text-slate-900"><?= esc((string) ($item['value'] ?? '')) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </section>

            <section class="rounded border border-slate-200 bg-white p-5">
                <h2 class="text-lg font-semibold text-slate-900">PHP extensions</h2>
                <dl class="mt-4 space-y-3">
                    <?php foreach ($extensions as $item): ?>
                        <?php $isLoaded = (string) ($item['value'] ?? '') === 'Loaded'; ?>
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3 last:border-b-0 last:pb-0">
                            <dt class="text-sm text-slate-500"><?= esc((string) ($item['label'] ?? '')) ?></dt>
                            <dd class="rounded px-2 py-1 text-xs font-semibold <?= $isLoaded ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800' ?>">
                                <?= esc((string) ($item['value'] ?? '')) ?>
                            </dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </section>
        </div>
    </div>
</div>
