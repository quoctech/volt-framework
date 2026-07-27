<?php

$reports = $reports ?? [];
$lang = \Volt\Core\Config\Lang\LangService::load();
$d = $lang['desk'] ?? [];
?>
<div x-data="dashboardApp()" class="space-y-4">
    <div class="rounded border border-slate-200 bg-white px-5 py-4">
        <h1 class="text-xl font-semibold text-slate-900">Dashboard</h1>
        <p class="mt-1 text-sm text-slate-500">Visualize your reports with charts.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <?php foreach ($reports as $report): ?>
        <?php if (! (int) ($report['is_active'] ?? 0)) continue; ?>
        <div class="rounded border border-slate-200 bg-white p-4">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-slate-900"><?= esc($report['label'] ?? '') ?></h3>
                <span class="text-xs text-slate-400"><?= esc($report['report_type'] ?? '') ?></span>
            </div>
            <div class="relative" style="height: 220px;">
                <canvas x-init="initChart($el, '<?= esc($report['name'], 'js') ?>')"></canvas>
            </div>
            <div class="mt-2 text-right">
                <button @click="refreshChart('<?= esc($report['name'], 'js') ?>')" class="text-xs text-slate-500 hover:text-slate-900">Refresh</button>
                <a href="<?= site_url('desk/reports/edit/' . urlencode($report['name'] ?? '')) ?>" class="ml-2 text-xs text-slate-500 hover:text-slate-900">Edit</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (count(array_filter($reports, fn($r) => (int)($r['is_active'] ?? 0))) === 0): ?>
    <div class="rounded border border-slate-200 bg-white px-5 py-12 text-center text-sm text-slate-500">
        No active reports found. <a href="<?= site_url('desk/reports/create') ?>" class="text-slate-900 underline">Create a report</a> first.
    </div>
    <?php endif; ?>
</div>

<script>
var chartInstances = {};

function dashboardApp() {
    return {};
}

function initChart(canvas, reportName) {
    var parentDiv = canvas.parentElement;

    fetch('<?= site_url('api/reports/run') ?>/' + encodeURIComponent(reportName), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ limit: 50 })
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (!result.success || !result.data || !result.data.rows || result.data.rows.length === 0) {
            parentDiv.innerHTML = '<div class="flex h-full items-center justify-center text-xs text-slate-400">No data</div>';
            return;
        }

        if (chartInstances[reportName]) {
            chartInstances[reportName].destroy();
        }

        var cols = result.data.columns || [];
        if (cols.length < 2) {
            parentDiv.innerHTML = '<div class="flex h-full items-center justify-center text-xs text-slate-400">Need at least 2 columns</div>';
            return;
        }

        var labels = result.data.rows.map(function(r) { return r[cols[0].label] || ''; });
        var values = result.data.rows.map(function(r) { return parseFloat(r[cols[1].label]) || 0; });

        var ctx = canvas.getContext('2d');
        chartInstances[reportName] = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: cols[1].label || cols[1].field,
                    data: values,
                    backgroundColor: 'rgba(59, 130, 246, 0.7)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                    x: { grid: { display: false }, ticks: { maxRotation: 45 } }
                }
            }
        });
    })
    .catch(function() {
        parentDiv.innerHTML = '<div class="flex h-full items-center justify-center text-xs text-red-500">Load failed</div>';
    });
}

function refreshChart(reportName) {
    var canvases = document.querySelectorAll('canvas');
    for (var i = 0; i < canvases.length; i++) {
        var parentText = canvases[i].parentElement.parentElement.querySelector('h3');
        if (parentText && parentText.textContent.trim().toLowerCase().indexOf(reportName.toLowerCase()) >= 0) {
            initChart(canvases[i], reportName);
            break;
        }
    }
}
</script>
