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
<div>
    <div class="claro-table-toolbar">
        <div class="claro-table-toolbar__left">
            <div class="claro-page-header" style="margin-bottom:0">
                <h1 class="claro-page-header__title">Error Logs</h1>
                <p class="claro-page-header__subtitle">Compact runtime log view for Volt core.</p>
            </div>
        </div>
        <div class="claro-table-toolbar__right" style="display:flex;flex-wrap:wrap;gap:var(--claro-space-xs)">
            <span class="claro-badge" style="background:var(--claro-gray-100);color:var(--claro-gray-600)">Total <?= esc((string) ($summary['total'] ?? $total)) ?></span>
            <span class="claro-badge claro-badge--error">Error <?= esc((string) ($summary['error'] ?? 0)) ?></span>
            <span class="claro-badge claro-badge--warning">Warning <?= esc((string) ($summary['warning'] ?? 0)) ?></span>
            <span class="claro-badge claro-badge--info">Info <?= esc((string) ($summary['info'] ?? 0)) ?></span>
        </div>
    </div>

    <form method="get" action="<?= site_url('desk/error-logs') ?>" class="claro-card" style="padding:var(--claro-space-m) var(--claro-space-l);margin-bottom:var(--claro-space-m)">
        <div style="display:grid;gap:var(--claro-space-s);grid-template-columns:repeat(auto-fill,minmax(10rem,1fr))">
            <input type="text" name="q" value="<?= esc($query) ?>" placeholder="Search message, code, actor, URI" class="claro-input">
            <select name="level" class="claro-select">
                <option value="">All levels</option>
                <?php foreach (['error', 'warning', 'info', 'notice', 'debug', 'critical', 'alert', 'emergency'] as $option): ?>
                    <option value="<?= esc($option) ?>" <?= $level === $option ? 'selected' : '' ?>><?= esc($option) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="channel" class="claro-select">
                <option value="">All channels</option>
                <?php foreach ($channels as $option): ?>
                    <option value="<?= esc($option) ?>" <?= $channel === $option ? 'selected' : '' ?>><?= esc($option) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="per_page" class="claro-select">
                <?php foreach ($perPageOptions as $option): ?>
                    <option value="<?= esc((string) $option) ?>" <?= $perPage === (int) $option ? 'selected' : '' ?>><?= esc((string) $option) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="claro-form-actions" style="margin:0;align-items:flex-end">
                <button type="submit" class="claro-button claro-button--small" style="margin:0">Apply</button>
                <a href="<?= site_url('desk/error-logs') ?>" class="claro-button claro-button--small" style="margin:0">Reset</a>
            </div>
        </div>
    </form>

    <div class="claro-card" style="padding:0">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:var(--claro-space-s) var(--claro-space-l);border-bottom:1px solid var(--claro-gray-100);font-size:var(--claro-font-size-xs);font-weight:700;text-transform:uppercase;letter-spacing:0.12em;color:var(--claro-color-text-light)">
            <span><?= esc((string) $total) ?> log entries</span>
            <span>Page <?= esc((string) $page) ?> / <?= esc((string) $totalPages) ?></span>
        </div>

        <?php if ($rows === []): ?>
            <div class="claro-empty">No error logs match the current filter.</div>
        <?php else: ?>
            <?php foreach ($rows as $row): ?>
                <?php
                $hasDetails = $row['trace'] !== '' || $row['context'] !== [] || $row['file'] !== '' || $row['request_uri'] !== '';
                $messageLabel = trim(($row['code'] !== '' ? '[' . $row['code'] . '] ' : '') . $row['message']);
                $requestLabel = trim(($row['request_method'] !== '' ? $row['request_method'] . ' ' : '') . ($row['request_uri'] !== '' ? $row['request_uri'] : ''));
                $levelForBadge = $row['level'];
                ?>
                <section x-data="{ open: false }" style="border-bottom:1px solid var(--claro-gray-100)">
                    <div style="display:grid;gap:var(--claro-space-xs);padding:var(--claro-space-s) var(--claro-space-l);grid-template-columns:10rem 5rem 7rem 1fr 6rem 4rem">
                        <div style="font-size:var(--claro-font-size-xs);color:var(--claro-gray-500);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= esc((string) ($row['created_at'] !== '' ? $row['created_at'] : 'n/a')) ?></div>
                        <div><span class="claro-badge" style="<?= $levelForBadge === 'error' || $levelForBadge === 'critical' || $levelForBadge === 'alert' || $levelForBadge === 'emergency' ? 'background:var(--claro-color-error-bg);color:var(--claro-color-error)' : ($levelForBadge === 'warning' ? 'background:var(--claro-color-warning-bg);color:#7a5a00' : '') ?>"><?= esc((string) $row['level']) ?></span></div>
                        <div style="font-size:var(--claro-font-size-s);color:var(--claro-gray-700);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= esc((string) ($row['channel'] !== '' ? $row['channel'] : 'system')) ?></div>
                        <div style="min-width:0">
                            <div style="font-size:var(--claro-font-size-s);font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= esc($messageLabel) ?>"><?= esc($messageLabel) ?></div>
                            <?php if ($requestLabel !== ''): ?>
                                <div style="font-size:var(--claro-font-size-xs);color:var(--claro-gray-500);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= esc($requestLabel) ?></div>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:var(--claro-font-size-s);color:var(--claro-gray-600);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= esc((string) ($row['actor'] !== '' ? $row['actor'] : 'system')) ?></div>
                        <div style="text-align:right">
                            <?php if ($hasDetails): ?>
                                <button @click="open = !open" type="button" class="claro-button claro-button--extrasmall" style="margin:0"><?= esc($rp['view'] ?? 'View') ?></button>
                            <?php else: ?>
                                <span style="font-size:var(--claro-font-size-xs);color:var(--claro-gray-400)">n/a</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($hasDetails): ?>
                        <div x-show="open" x-cloak style="border-top:1px solid var(--claro-gray-100);background:var(--claro-gray-050);padding:var(--claro-space-s) var(--claro-space-l)">
                            <div style="display:grid;gap:var(--claro-space-s);grid-template-columns:1fr 5rem 10rem;margin-bottom:var(--claro-space-s)">
                                <div style="border:1px solid var(--claro-gray-200);border-radius:var(--claro-border-radius);background:var(--claro-color-bg);padding:var(--claro-space-xs) var(--claro-space-s)">
                                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.12em;color:var(--claro-gray-500)">File</div>
                                    <div style="margin-top:4px;font-size:var(--claro-font-size-s);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= esc((string) ($row['file'] !== '' ? $row['file'] : 'n/a')) ?></div>
                                </div>
                                <div style="border:1px solid var(--claro-gray-200);border-radius:var(--claro-border-radius);background:var(--claro-color-bg);padding:var(--claro-space-xs) var(--claro-space-s)">
                                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.12em;color:var(--claro-gray-500)">Line</div>
                                    <div style="margin-top:4px;font-size:var(--claro-font-size-s)"><?= esc((string) ($row['line'] ?? 'n/a')) ?></div>
                                </div>
                                <div style="border:1px solid var(--claro-gray-200);border-radius:var(--claro-border-radius);background:var(--claro-color-bg);padding:var(--claro-space-xs) var(--claro-space-s)">
                                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.12em;color:var(--claro-gray-500)">IP</div>
                                    <div style="margin-top:4px;font-size:var(--claro-font-size-s);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= esc((string) ($row['ip_address'] !== '' ? $row['ip_address'] : 'n/a')) ?></div>
                                </div>
                            </div>

                            <?php if ($row['context'] !== []): ?>
                                <div style="margin-top:var(--claro-space-s)">
                                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.12em;color:var(--claro-gray-500);margin-bottom:4px">Context</div>
                                    <pre style="overflow-x:auto;border:1px solid var(--claro-gray-200);border-radius:var(--claro-border-radius);background:var(--claro-color-text);padding:var(--claro-space-xs) var(--claro-space-s);font-size:11px;color:#e5e5e5"><?= esc((string) $row['context_text']) ?></pre>
                                </div>
                            <?php endif; ?>

                            <?php if ($row['trace'] !== ''): ?>
                                <div style="margin-top:var(--claro-space-s)">
                                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.12em;color:var(--claro-gray-500);margin-bottom:4px">Trace</div>
                                    <pre style="overflow-x:auto;border:1px solid var(--claro-gray-200);border-radius:var(--claro-border-radius);background:var(--claro-color-text);padding:var(--claro-space-xs) var(--claro-space-s);font-size:11px;color:#e5e5e5"><?= esc((string) $row['trace']) ?></pre>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="claro-form-actions" style="justify-content:space-between;border:1px solid var(--claro-gray-200);border-radius:var(--claro-border-radius);background:var(--claro-color-bg);padding:var(--claro-space-s) var(--claro-space-m);margin-top:var(--claro-space-m)">
            <span style="font-size:var(--claro-font-size-s);color:var(--claro-color-text-light)">Showing page <?= esc((string) $page) ?> of <?= esc((string) $totalPages) ?></span>
            <div style="display:flex;gap:var(--claro-space-xs)">
                <a href="<?= esc($buildPageUrl(max(1, $page - 1))) ?>" class="claro-button claro-button--small" style="<?= $page <= 1 ? 'opacity:0.5;pointer-events:none' : '' ?>">Previous</a>
                <a href="<?= esc($buildPageUrl(min($totalPages, $page + 1))) ?>" class="claro-button claro-button--small" style="<?= $page >= $totalPages ? 'opacity:0.5;pointer-events:none' : '' ?>">Next</a>
            </div>
        </div>
    <?php endif; ?>
</div>
