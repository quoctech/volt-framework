<?php

/** @var array<string, mixed> $logs */
/** @var array<int, string> $channels */
$logs = $logs ?? [];
$channels = $channels ?? [];
$rows = is_array($logs['rows'] ?? null) ? $logs['rows'] : [];
$meta = is_array($logs['meta'] ?? null) ? $logs['meta'] : [];
$filters = is_array($logs['filters'] ?? null) ? $logs['filters'] : [];
$summary = is_array($logs['summary'] ?? null) ? $logs['summary'] : [];

$page = max(1, (int) ($meta['page'] ?? 1));
$perPage = (int) ($meta['per_page'] ?? 50);
$totalPages = max(1, (int) ($meta['total_pages'] ?? 1));
$total = (int) ($meta['total'] ?? 0);
$perPageOptions = is_array($meta['per_page_options'] ?? null) ? $meta['per_page_options'] : [20, 50, 100, 200];

$level = (string) ($filters['level'] ?? '');
$channel = (string) ($filters['channel'] ?? '');
$query = (string) ($filters['q'] ?? '');

$levelClasses = [
    'error' => 'border-rose-200 bg-rose-50 text-rose-700',
    'warning' => 'border-amber-200 bg-amber-50 text-amber-700',
    'info' => 'border-sky-200 bg-sky-50 text-sky-700',
    'notice' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
    'debug' => 'border-zinc-200 bg-zinc-100 text-zinc-700',
    'critical' => 'border-rose-200 bg-rose-50 text-rose-700',
    'alert' => 'border-rose-200 bg-rose-50 text-rose-700',
    'emergency' => 'border-rose-200 bg-rose-50 text-rose-700',
];

$buildPageUrl = static function (int $targetPage) use ($perPage, $level, $channel, $query): string {
    $params = array_filter([
        'page' => $targetPage > 1 ? $targetPage : null,
        'per_page' => $perPage !== 50 ? $perPage : null,
        'level' => $level !== '' ? $level : null,
        'channel' => $channel !== '' ? $channel : null,
        'q' => $query !== '' ? $query : null,
    ], static fn ($value): bool => $value !== null && $value !== '');

    $url = site_url('desk/error-logs');

    return $params === [] ? $url : $url . '?' . http_build_query($params);
};
?>
<div class="space-y-4">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Error Logs</h1>
            <p class="mt-1 text-sm text-slate-500">Compact runtime log view for Volt core.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="rounded border border-slate-200 bg-white px-3 py-1.5 text-slate-600">Total <?= esc((string) ($summary['total'] ?? $total)) ?></span>
            <span class="rounded border border-rose-200 bg-rose-50 px-3 py-1.5 text-rose-700">Error <?= esc((string) ($summary['error'] ?? 0)) ?></span>
            <span class="rounded border border-amber-200 bg-amber-50 px-3 py-1.5 text-amber-700">Warning <?= esc((string) ($summary['warning'] ?? 0)) ?></span>
            <span class="rounded border border-sky-200 bg-sky-50 px-3 py-1.5 text-sky-700">Info <?= esc((string) ($summary['info'] ?? 0)) ?></span>
        </div>
    </div>

    <form method="get" action="<?= site_url('desk/error-logs') ?>" class="rounded border border-slate-200 bg-white p-3">
        <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_140px_160px_110px_auto]">
            <input
                type="text"
                name="q"
                value="<?= esc($query) ?>"
                placeholder="Search message, code, actor, URI"
                class="w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-1 focus:ring-slate-500"
            >
            <select name="level" class="w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-1 focus:ring-slate-500">
                <option value="">All levels</option>
                <?php foreach (['error', 'warning', 'info', 'notice', 'debug', 'critical', 'alert', 'emergency'] as $option): ?>
                    <option value="<?= esc($option) ?>" <?= $level === $option ? 'selected' : '' ?>><?= esc($option) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="channel" class="w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-1 focus:ring-slate-500">
                <option value="">All channels</option>
                <?php foreach ($channels as $option): ?>
                    <option value="<?= esc($option) ?>" <?= $channel === $option ? 'selected' : '' ?>><?= esc($option) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="per_page" class="w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-slate-500 focus:ring-1 focus:ring-slate-500">
                <?php foreach ($perPageOptions as $option): ?>
                    <option value="<?= esc((string) $option) ?>" <?= $perPage === (int) $option ? 'selected' : '' ?>><?= esc((string) $option) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="flex gap-2">
                <button type="submit" class="rounded border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">Apply</button>
                <a href="<?= site_url('desk/error-logs') ?>" class="rounded border border-slate-300 bg-white px-4 py-2 text-sm text-slate-700 transition hover:bg-slate-50">Reset</a>
            </div>
        </div>
    </form>

    <div class="overflow-hidden rounded border border-slate-200 bg-white">
        <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-2 text-xs uppercase tracking-[0.12em] text-slate-500">
            <span><?= esc((string) $total) ?> log entries</span>
            <span>Page <?= esc((string) $page) ?> / <?= esc((string) $totalPages) ?></span>
        </div>

        <?php if ($rows === []): ?>
            <div class="px-4 py-8 text-center text-sm text-slate-500">No error logs match the current filter.</div>
        <?php else: ?>
            <div class="hidden grid-cols-[170px_92px_130px_minmax(0,1fr)_100px_72px] gap-3 border-b border-slate-200 px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500 lg:grid">
                <div>Time</div>
                <div>Level</div>
                <div>Channel</div>
                <div>Message</div>
                <div>Actor</div>
                <div class="text-right">More</div>
            </div>

            <?php foreach ($rows as $row): ?>
                <?php
                $badgeClass = $levelClasses[$row['level']] ?? 'border-zinc-200 bg-zinc-100 text-zinc-700';
                $hasDetails = $row['trace'] !== '' || $row['context'] !== [] || $row['file'] !== '' || $row['request_uri'] !== '';
                $messageLabel = trim(($row['code'] !== '' ? '[' . $row['code'] . '] ' : '') . $row['message']);
                $requestLabel = trim(($row['request_method'] !== '' ? $row['request_method'] . ' ' : '') . ($row['request_uri'] !== '' ? $row['request_uri'] : ''));
                ?>
                <section x-data="{ open: false }" class="border-b border-slate-200 last:border-b-0">
                    <div class="grid gap-2 px-4 py-3 lg:grid-cols-[170px_92px_130px_minmax(0,1fr)_100px_72px] lg:items-center">
                        <div class="truncate text-xs text-slate-500"><?= esc((string) ($row['created_at'] !== '' ? $row['created_at'] : 'n/a')) ?></div>
                        <div>
                            <span class="inline-flex rounded border px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide <?= esc($badgeClass) ?>">
                                <?= esc((string) $row['level']) ?>
                            </span>
                        </div>
                        <div class="truncate text-sm text-slate-700"><?= esc((string) ($row['channel'] !== '' ? $row['channel'] : 'system')) ?></div>
                        <div class="min-w-0">
                            <div class="truncate text-sm font-medium text-slate-900" title="<?= esc($messageLabel) ?>"><?= esc($messageLabel) ?></div>
                            <?php if ($requestLabel !== ''): ?>
                                <div class="truncate text-xs text-slate-500" title="<?= esc($requestLabel) ?>"><?= esc($requestLabel) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="truncate text-sm text-slate-600"><?= esc((string) ($row['actor'] !== '' ? $row['actor'] : 'system')) ?></div>
                        <div class="flex justify-end">
                            <?php if ($hasDetails): ?>
                                <button @click="open = !open" type="button" class="rounded border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 transition hover:bg-slate-50">
                                    <span x-text="open ? 'Hide' : 'View'"></span>
                                </button>
                            <?php else: ?>
                                <span class="text-xs text-slate-400">n/a</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($hasDetails): ?>
                        <div x-show="open" x-cloak class="border-t border-slate-100 bg-slate-50 px-4 py-3">
                            <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_80px_160px]">
                                <div class="min-w-0 rounded border border-slate-200 bg-white px-3 py-2">
                                    <div class="text-[11px] uppercase tracking-[0.12em] text-slate-500">File</div>
                                    <div class="mt-1 truncate text-sm text-slate-800" title="<?= esc((string) ($row['file'] !== '' ? $row['file'] : 'n/a')) ?>"><?= esc((string) ($row['file'] !== '' ? $row['file'] : 'n/a')) ?></div>
                                </div>
                                <div class="rounded border border-slate-200 bg-white px-3 py-2">
                                    <div class="text-[11px] uppercase tracking-[0.12em] text-slate-500">Line</div>
                                    <div class="mt-1 text-sm text-slate-800"><?= esc((string) ($row['line'] ?? 'n/a')) ?></div>
                                </div>
                                <div class="min-w-0 rounded border border-slate-200 bg-white px-3 py-2">
                                    <div class="text-[11px] uppercase tracking-[0.12em] text-slate-500">IP</div>
                                    <div class="mt-1 truncate text-sm text-slate-800" title="<?= esc((string) ($row['ip_address'] !== '' ? $row['ip_address'] : 'n/a')) ?>"><?= esc((string) ($row['ip_address'] !== '' ? $row['ip_address'] : 'n/a')) ?></div>
                                </div>
                            </div>

                            <?php if ($row['context'] !== []): ?>
                                <div class="mt-3">
                                    <div class="mb-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">Context</div>
                                    <pre class="overflow-x-auto rounded border border-slate-200 bg-slate-950 px-3 py-2 text-[11px] text-slate-100"><?= esc((string) $row['context_text']) ?></pre>
                                </div>
                            <?php endif; ?>

                            <?php if ($row['trace'] !== ''): ?>
                                <div class="mt-3">
                                    <div class="mb-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">Trace</div>
                                    <pre class="overflow-x-auto rounded border border-slate-200 bg-slate-950 px-3 py-2 text-[11px] text-slate-100"><?= esc((string) $row['trace']) ?></pre>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-between rounded border border-slate-200 bg-white px-4 py-3 text-sm">
            <div class="text-slate-500">Showing page <?= esc((string) $page) ?> of <?= esc((string) $totalPages) ?></div>
            <div class="flex gap-2">
                <a href="<?= esc($buildPageUrl(max(1, $page - 1))) ?>" class="rounded border border-slate-300 px-3 py-2 text-slate-700 transition hover:bg-slate-50 <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>">Previous</a>
                <a href="<?= esc($buildPageUrl(min($totalPages, $page + 1))) ?>" class="rounded border border-slate-300 px-3 py-2 text-slate-700 transition hover:bg-slate-50 <?= $page >= $totalPages ? 'pointer-events-none opacity-50' : '' ?>">Next</a>
            </div>
        </div>
    <?php endif; ?>
</div>
