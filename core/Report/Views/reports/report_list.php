<?php

$reports = $reports ?? [];
?>
<div>
    <div class="claro-table-toolbar">
        <div class="claro-table-toolbar__left">
            <div class="claro-page-header" style="margin-bottom:0">
                <h1 class="claro-page-header__title">Reports</h1>
            </div>
        </div>
        <div class="claro-table-toolbar__right">
            <a href="<?= site_url('desk/reports/create') ?>" class="claro-button claro-button--primary">
                + Create Report
            </a>
        </div>
    </div>

    <?php if ($reports === []): ?>
        <div class="claro-empty">
            <p class="claro-empty__text">No reports yet. Create your first report.</p>
        </div>
    <?php else: ?>
        <table class="claro-table">
            <thead>
                <tr>
                    <th>Label</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Module</th>
                    <th>Active</th>
                    <th class="claro-table__actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $report): ?>
                <tr>
                    <td style="font-weight:600"><?= esc($report['label'] ?? '') ?></td>
                    <td style="color:var(--claro-gray-600)"><?= esc($report['name'] ?? '') ?></td>
                    <td><code style="border-radius:var(--claro-border-radius);background:var(--claro-gray-050);padding:1px 6px;font-size:var(--claro-font-size-xs)"><?= esc($report['report_type'] ?? '') ?></code></td>
                    <td style="color:var(--claro-gray-600)"><?= esc($report['module'] ?? '') ?></td>
                    <td>
                        <?php if ((int) ($report['is_active'] ?? 0)): ?>
                            <span class="claro-badge claro-badge--success">Active</span>
                        <?php else: ?>
                            <span class="claro-badge">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td class="claro-table__actions">
                        <a href="<?= site_url('desk/reports/edit/' . urlencode($report['name'] ?? '')) ?>" class="claro-button claro-button--small">Edit</a>
                        <button onclick="runReport('<?= esc($report['name'] ?? '') ?>')" class="claro-button claro-button--small">Run</button>
                        <button onclick="deleteReport('<?= esc($report['name'] ?? '') ?>')" class="claro-button claro-button--small claro-button--danger">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
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
            html += '<td class="px-3 py-2 text-slate-700">' + (row[col.label] ?? '') + '</td>';
        });
        html += '</tr>';
    });
    html += '</tbody></table></div>';
    html += '<p class="mt-2 text-xs text-slate-400">Total: ' + data.total + ' rows</p>';
    container.innerHTML = html;
}
</script>
