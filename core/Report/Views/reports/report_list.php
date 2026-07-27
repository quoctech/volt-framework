<?php

$reports = $reports ?? [];
?>
<div class="space-y-4">
    <div class="flex items-center justify-between gap-4 rounded border border-slate-200 bg-white px-5 py-4">
        <h1 class="text-xl font-semibold text-slate-900">Reports</h1>
        <a href="<?= site_url('desk/reports/create') ?>" class="inline-flex items-center gap-1 rounded border border-slate-600 bg-white px-3 py-1.5 text-sm font-semibold text-slate-900">
            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor"><path d="M8 2a.75.75 0 01.75.75v4.5h4.5a.75.75 0 010 1.5h-4.5v4.5a.75.75 0 01-1.5 0v-4.5h-4.5a.75.75 0 010-1.5h4.5v-4.5A.75.75 0 018 2z"/></svg>
            Create Report
        </a>
    </div>

    <div class="overflow-x-auto rounded border border-slate-200 bg-white">
        <?php if ($reports === []): ?>
            <div class="px-5 py-12 text-center text-sm text-slate-500">No reports yet. Create your first report.</div>
        <?php else: ?>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                        <th class="px-5 py-3">Label</th>
                        <th class="px-5 py-3">Name</th>
                        <th class="px-5 py-3">Type</th>
                        <th class="px-5 py-3">Module</th>
                        <th class="px-5 py-3">Active</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $report): ?>
                    <tr class="border-b border-slate-100 last:border-b-0">
                        <td class="px-5 py-3 font-medium text-slate-900"><?= esc($report['label'] ?? '') ?></td>
                        <td class="px-5 py-3 text-slate-600"><?= esc($report['name'] ?? '') ?></td>
                        <td class="px-5 py-3"><code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs"><?= esc($report['report_type'] ?? '') ?></code></td>
                        <td class="px-5 py-3 text-slate-600"><?= esc($report['module'] ?? '') ?></td>
                        <td class="px-5 py-3">
                            <?php if ((int) ($report['is_active'] ?? 0)): ?>
                                <span class="inline-flex rounded bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Active</span>
                            <?php else: ?>
                                <span class="inline-flex rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <a href="<?= site_url('desk/reports/edit/' . urlencode($report['name'] ?? '')) ?>" class="inline-flex items-center gap-1 rounded border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700">Edit</a>
                            <button onclick="runReport('<?= esc($report['name'] ?? '') ?>')" class="inline-flex items-center gap-1 rounded border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700">Run</button>
                            <button onclick="deleteReport('<?= esc($report['name'] ?? '') ?>')" class="inline-flex items-center gap-1 rounded border border-red-300 bg-white px-2.5 py-1 text-xs font-medium text-red-600">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div id="reportResult" class="hidden"></div>

<script>
function deleteReport(name) {
    if (!confirm('Delete report "' + name + '"?')) return;
    fetch('<?= site_url('api/reports/delete') ?>/' + encodeURIComponent(name), { method: 'POST' })
        .then(r => r.json())
        .then(data => { if (data.success) location.reload(); })
        .catch(() => alert('Delete failed.'));
}

function runReport(name) {
    var btn = event.target;
    btn.textContent = 'Running...';
    btn.disabled = true;

    fetch('<?= site_url('api/reports/run') ?>/' + encodeURIComponent(name), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: '{}'
    })
    .then(r => r.json())
    .then(function(result) {
        btn.textContent = 'Run';
        btn.disabled = false;
        if (!result.success) { alert('Error: ' + (result.error || 'Failed.')); return; }
        showReportResult(name, result.data);
    })
    .catch(function() {
        btn.textContent = 'Run';
        btn.disabled = false;
        alert('Run failed.');
    });
}

function showReportResult(name, data) {
    var container = document.getElementById('reportResult');
    container.className = 'mt-4 rounded border border-slate-200 bg-white p-4';
    var html = '<div class="mb-2 flex items-center justify-between"><h3 class="text-sm font-semibold text-slate-900">Result: ' + name + '</h3><button onclick="this.parentElement.parentElement.remove()" class="text-xs text-slate-500">Close</button></div>';
    html += '<div class="overflow-x-auto text-sm"><table class="w-full">';
    html += '<thead><tr class="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">';
    data.columns.forEach(function(col) {
        html += '<th class="px-3 py-2">' + col.label + '</th>';
    });
    html += '</tr></thead><tbody>';
    data.rows.forEach(function(row) {
        html += '<tr class="border-b border-slate-100">';
        data.columns.forEach(function(col) {
            html += '<td class="px-3 py-2 text-slate-700">' + (row[col.field] ?? '') + '</td>';
        });
        html += '</tr>';
    });
    html += '</tbody></table></div>';
    html += '<p class="mt-2 text-xs text-slate-400">Total: ' + data.total + ' rows</p>';
    container.innerHTML = html;
}
</script>
