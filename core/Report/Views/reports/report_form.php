<?php

$report = $report ?? null;
$modules = $modules ?? [];
$roles = $roles ?? [];
$entities = $entities ?? [];
$isEdit = $report !== null;
$reportRoles = $isEdit && isset($report['roles']) ? (is_string($report['roles']) ? json_decode($report['roles'], true) : $report['roles']) : [];
if (! is_array($reportRoles)) $reportRoles = [];
?>
<script>
window.reportFormEntities = <?= json_encode($entities, JSON_UNESCAPED_UNICODE) ?>;
window.reportFormData = <?= json_encode($report, JSON_UNESCAPED_UNICODE) ?>;
</script>

<div class="space-y-4" x-data="reportForm()">
    <div class="rounded border border-slate-200 bg-white px-5 py-4">
        <a href="<?= site_url('desk/reports') ?>" class="mb-2 inline-flex items-center gap-1 text-sm text-slate-500">
            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor"><path d="M7.78 12.53a.75.75 0 01-1.06 0L2.47 8.28a.75.75 0 010-1.06l4.25-4.25a.75.75 0 011.06 1.06L4.81 7h8.44a.75.75 0 010 1.5H4.81l2.97 2.97a.75.75 0 010 1.06z"/></svg>
            Back to Reports
        </a>
        <h1 class="text-xl font-semibold text-slate-900" x-text="isEdit ? 'Edit Report' : 'Create Report'"></h1>
    </div>

    <form id="reportForm" @submit.prevent="save()" class="rounded border border-slate-200 bg-white">
        <input type="hidden" name="original_name" :value="form.name">

        <div class="p-5 space-y-4">
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Report Name</label>
                <input type="text" x-model="form.name" :readonly="isEdit" required
                    class="w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-900"
                    :class="isEdit ? 'bg-slate-50 text-slate-500' : ''"
                    placeholder="monthly_sales">
                <p class="mt-1 text-xs text-slate-400">Lowercase, underscores only.</p>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Label</label>
                <input type="text" x-model="form.label" required
                    class="w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-900"
                    placeholder="Monthly Sales Report">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Module</label>
                <select x-model="form.module" required class="w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-900">
                    <option value="">-- Select Module --</option>
                    <?php foreach ($modules as $mod): ?>
                    <option value="<?= esc($mod['name'] ?? '') ?>"><?= esc($mod['label'] ?? $mod['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Description</label>
                <textarea x-model="form.description" rows="2" class="w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-900" placeholder="Optional description"></textarea>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Report Type</label>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="radio" name="report_type" value="query" x-model="form.reportType"> Query
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="radio" name="report_type" value="pivot" x-model="form.reportType"> Pivot
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="radio" name="report_type" value="sql" x-model="form.reportType"> SQL
                    </label>
                </div>
            </div>
            <div>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" x-model="form.isActive"> Active
                </label>
            </div>
        </div>

        <!-- Query Builder Section -->
        <div x-show="form.reportType === 'query'" class="border-t border-slate-200">
            <div class="p-5 space-y-4">
                <h3 class="text-sm font-semibold text-slate-900">Entities</h3>
                <template x-for="(ent, idx) in form.entities" :key="idx">
                    <div class="flex flex-wrap items-center gap-2 rounded border border-slate-200 bg-slate-50 p-3">
                        <select x-model="ent.entity" @change="onEntityChange(idx)" class="rounded border border-slate-300 px-2 py-1 text-sm">
                            <option value="">-- Select Entity --</option>
                            <template x-for="e in availableEntities" :key="e.name">
                                <option :value="e.name" x-text="e.label"></option>
                            </template>
                        </select>
                        <input type="text" x-model="ent.alias" placeholder="Alias" class="w-20 rounded border border-slate-300 px-2 py-1 text-sm">
                        <template x-if="idx > 0">
                            <>
                                <select x-model="ent.joinType" class="rounded border border-slate-300 px-2 py-1 text-sm">
                                    <option value="LEFT">LEFT JOIN</option>
                                    <option value="INNER">INNER JOIN</option>
                                    <option value="RIGHT">RIGHT JOIN</option>
                                    <option value="FULL">FULL JOIN</option>
                                </select>
                                <input type="text" x-model="ent.joinOn" placeholder="e.id = other.e_id" class="min-w-[200px] rounded border border-slate-300 px-2 py-1 text-sm font-mono">
                            </>
                        </template>
                        <button type="button" @click="removeEntity(idx)" class="text-xs text-red-600">&times;</button>
                    </div>
                </template>
                <button type="button" @click="addEntity()" class="text-sm text-slate-600 hover:text-slate-900">+ Add Entity</button>

                <h3 class="text-sm font-semibold text-slate-900">Columns</h3>
                <template x-for="(col, idx) in form.columns" :key="idx">
                    <div class="flex flex-wrap items-center gap-2 rounded border border-slate-200 bg-slate-50 p-3">
                        <input type="text" x-model="col.field" placeholder="e.field_name" class="min-w-[180px] rounded border border-slate-300 px-2 py-1 text-sm font-mono" list="fieldSuggestions">
                        <input type="text" x-model="col.label" placeholder="Label" class="w-32 rounded border border-slate-300 px-2 py-1 text-sm">
                        <select x-model="col.aggregation" class="rounded border border-slate-300 px-2 py-1 text-sm">
                            <option value="">-- None --</option>
                            <option value="SUM">SUM</option>
                            <option value="COUNT">COUNT</option>
                            <option value="AVG">AVG</option>
                            <option value="MIN">MIN</option>
                            <option value="MAX">MAX</option>
                            <option value="COUNT_DISTINCT">COUNT DISTINCT</option>
                        </select>
                        <button type="button" @click="removeColumn(idx)" class="text-xs text-red-600">&times;</button>
                    </div>
                </template>
                <button type="button" @click="addColumn()" class="text-sm text-slate-600 hover:text-slate-900">+ Add Column</button>

                <datalist id="fieldSuggestions">
                    <template x-for="e in availableEntities" :key="e.name">
                        <template x-for="f in e.fields" :key="e.name + '.' + f.fieldname">
                            <option :value="e.name + '.' + f.fieldname" :label="e.label + ': ' + f.label"></option>
                        </template>
                    </template>
                </datalist>

                <h3 class="text-sm font-semibold text-slate-900">Filters</h3>
                <template x-for="(f, idx) in form.filters" :key="'f'+idx">
                    <div class="flex flex-wrap items-center gap-2 rounded border border-slate-200 bg-slate-50 p-3">
                        <input type="text" x-model="f.field" placeholder="e.field" class="w-36 rounded border border-slate-300 px-2 py-1 text-sm font-mono">
                        <select x-model="f.operator" class="rounded border border-slate-300 px-2 py-1 text-sm">
                            <option value="=">=</option>
                            <option value="!=">!=</option>
                            <option value=">">&gt;</option>
                            <option value=">=">&gt;=</option>
                            <option value="<">&lt;</option>
                            <option value="<=">&lt;=</option>
                            <option value="like">Like</option>
                            <option value="not like">Not Like</option>
                            <option value="in">In</option>
                            <option value="not in">Not In</option>
                            <option value="between">Between</option>
                        </select>
                        <input type="text" x-model="f.value" placeholder="Value" class="w-36 rounded border border-slate-300 px-2 py-1 text-sm">
                        <button type="button" @click="removeFilter(idx)" class="text-xs text-red-600">&times;</button>
                    </div>
                </template>
                <button type="button" @click="addFilter()" class="text-sm text-slate-600 hover:text-slate-900">+ Add Filter</button>

                <h3 class="text-sm font-semibold text-slate-900">Order By</h3>
                <template x-for="(o, idx) in form.orderBy" :key="'o'+idx">
                    <div class="flex flex-wrap items-center gap-2 rounded border border-slate-200 bg-slate-50 p-3">
                        <input type="text" x-model="o.field" placeholder="e.field" class="w-36 rounded border border-slate-300 px-2 py-1 text-sm font-mono">
                        <select x-model="o.dir" class="rounded border border-slate-300 px-2 py-1 text-sm">
                            <option value="asc">ASC</option>
                            <option value="desc">DESC</option>
                        </select>
                        <button type="button" @click="removeOrderBy(idx)" class="text-xs text-red-600">&times;</button>
                    </div>
                </template>
                <button type="button" @click="addOrderBy()" class="text-sm text-slate-600 hover:text-slate-900">+ Add Order</button>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Limit</label>
                    <input type="number" x-model="form.limit" min="1" max="5000" class="w-24 rounded border border-slate-300 px-2 py-1 text-sm">
                </div>
            </div>
        </div>

        <!-- Pivot Builder Section -->
        <div x-show="form.reportType === 'pivot'" class="border-t border-slate-200">
            <div class="p-5 space-y-4">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Entity</label>
                    <select x-model="form.pivotEntity" @change="onPivotEntityChange()" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        <option value="">-- Select Entity --</option>
                        <template x-for="e in availableEntities" :key="e.name">
                            <option :value="e.name" x-text="e.label"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Row Fields</label>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="f in pivotFieldOptions" :key="f.fieldname">
                            <label class="flex items-center gap-1 text-sm text-slate-700">
                                <input type="checkbox" :value="'e.'+f.fieldname" x-model="form.rowFields">
                                <span x-text="f.label"></span>
                            </label>
                        </template>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Column Field</label>
                    <select x-model="form.columnField" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        <option value="">-- Select --</option>
                        <template x-for="f in pivotFieldOptions" :key="f.fieldname">
                            <option :value="'e.'+f.fieldname" x-text="f.label"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Values</label>
                    <template x-for="(v, idx) in form.pivotValues" :key="'pv'+idx">
                        <div class="flex flex-wrap items-center gap-2 rounded border border-slate-200 bg-slate-50 p-3">
                            <select x-model="v.field" class="rounded border border-slate-300 px-2 py-1 text-sm">
                                <option value="">-- Field --</option>
                                <option value="*">* (Count All)</option>
                                <template x-for="f in pivotFieldOptions" :key="f.fieldname">
                                    <option :value="'e.'+f.fieldname" x-text="f.label"></option>
                                </template>
                            </select>
                            <select x-model="v.aggregation" class="rounded border border-slate-300 px-2 py-1 text-sm">
                                <option value="SUM">SUM</option>
                                <option value="COUNT">COUNT</option>
                                <option value="AVG">AVG</option>
                                <option value="MIN">MIN</option>
                                <option value="MAX">MAX</option>
                            </select>
                            <input type="text" x-model="v.label" placeholder="Label" class="w-28 rounded border border-slate-300 px-2 py-1 text-sm">
                            <button type="button" @click="removePivotValue(idx)" class="text-xs text-red-600">&times;</button>
                        </div>
                    </template>
                    <button type="button" @click="addPivotValue()" class="text-sm text-slate-600 hover:text-slate-900">+ Add Value</button>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Filters</label>
                    <template x-for="(f, idx) in form.filters" :key="'pf'+idx">
                        <div class="flex flex-wrap items-center gap-2 rounded border border-slate-200 bg-slate-50 p-3">
                            <select x-model="f.field" class="rounded border border-slate-300 px-2 py-1 text-sm">
                                <option value="">-- Field --</option>
                                <template x-for="fopt in pivotFieldOptions" :key="fopt.fieldname">
                                    <option :value="'e.'+fopt.fieldname" x-text="fopt.label"></option>
                                </template>
                            </select>
                            <select x-model="f.operator" class="rounded border border-slate-300 px-2 py-1 text-sm">
                                <option value="=">=</option>
                                <option value="!=">!=</option>
                                <option value=">">&gt;</option>
                                <option value=">=">&gt;=</option>
                                <option value="<">&lt;</option>
                                <option value="<=">&lt;=</option>
                                <option value="like">Like</option>
                            </select>
                            <input type="text" x-model="f.value" placeholder="Value" class="w-28 rounded border border-slate-300 px-2 py-1 text-sm">
                            <button type="button" @click="removeFilter(idx)" class="text-xs text-red-600">&times;</button>
                        </div>
                    </template>
                    <button type="button" @click="addFilter()" class="text-sm text-slate-600 hover:text-slate-900">+ Add Filter</button>
                </div>
            </div>
        </div>

        <!-- SQL Builder Section -->
        <div x-show="form.reportType === 'sql'" class="border-t border-slate-200">
            <div class="p-5">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">SQL Query</label>
                <p class="mb-2 text-xs text-slate-400">Only SELECT queries allowed. DDL/DML statements are blocked.</p>
                <textarea x-model="form.sql" rows="12" class="w-full rounded border border-slate-300 px-3 py-2 font-mono text-sm text-slate-900" placeholder="SELECT * FROM tab_employee LIMIT 100"></textarea>
            </div>
        </div>

        <!-- Roles -->
        <div class="border-t border-slate-200 p-5">
            <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Role Access</label>
            <p class="mb-3 text-xs text-slate-400">Leave empty for all authenticated users.</p>
            <div class="flex flex-wrap gap-4">
                <?php foreach ($roles as $role): ?>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" value="<?= esc($role['name'] ?? '') ?>" x-model="form.roles" <?= in_array($role['name'] ?? '', $reportRoles, true) ? 'checked' : '' ?>>
                    <?= esc($role['label'] ?? $role['name'] ?? '') ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-3 border-t border-slate-200 px-5 py-4">
            <button type="button" @click="testRun()" class="rounded border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">Test Run</button>
            <a href="<?= site_url('desk/reports') ?>" class="rounded border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">Cancel</a>
            <button type="submit" class="rounded border border-slate-600 bg-white px-4 py-2 text-sm font-semibold text-slate-900">Save Report</button>
        </div>
    </form>

    <div id="reportResult" class="hidden"></div>
</div>

<script>
function reportForm() {
    var initial = window.reportFormData;
    var isEdit = initial !== null && initial.name;

    var buildForm = function(src) {
        var q = (src && src.query) || {};
        return {
            name: (src && src.name) || '',
            label: (src && src.label) || '',
            module: (src && src.module) || '',
            description: (src && src.description) || '',
            reportType: (src && src.report_type) || 'query',
            isActive: src ? (!!parseInt(src.is_active || 1)) : true,
            entities: q.entities || [{ entity: '', alias: 'e' }],
            columns: q.columns || [],
            filters: q.filters || [],
            orderBy: q.order_by || [],
            limit: q.limit || 100,
            pivotEntity: q.pivot_entity || '',
            rowFields: q.row_fields || [],
            columnField: q.column_field || '',
            pivotValues: q.values || [{ field: '*', aggregation: 'COUNT', label: 'Count' }],
            sql: q.sql || '',
            roles: (src && src.roles) || [],
        };
    };

    return {
        isEdit: isEdit,
        availableEntities: window.reportFormEntities || [],
        form: buildForm(initial),
        pivotFieldOptions: [],
        loading: false,

        addEntity() {
            this.form.entities.push({ entity: '', alias: 'e' + (this.form.entities.length) });
        },
        removeEntity(idx) {
            if (this.form.entities.length > 1) this.form.entities.splice(idx, 1);
        },
        onEntityChange(idx) {
            var ent = this.form.entities[idx];
            if (!ent.alias || ent.alias === 'e') {
                ent.alias = (ent.entity || 'e').charAt(0);
            }
        },
        addColumn() {
            this.form.columns.push({ field: '', label: '', aggregation: '' });
        },
        removeColumn(idx) {
            this.form.columns.splice(idx, 1);
        },
        addFilter() {
            this.form.filters.push({ field: '', operator: '=', value: '' });
        },
        removeFilter(idx) {
            this.form.filters.splice(idx, 1);
        },
        addOrderBy() {
            this.form.orderBy.push({ field: '', dir: 'asc' });
        },
        removeOrderBy(idx) {
            this.form.orderBy.splice(idx, 1);
        },
        addPivotValue() {
            this.form.pivotValues.push({ field: '', aggregation: 'COUNT', label: '' });
        },
        removePivotValue(idx) {
            this.form.pivotValues.splice(idx, 1);
        },

        onPivotEntityChange() {
            var found = this.availableEntities.find(function(e) { return e.name === this.form.pivotEntity; }.bind(this));
            this.pivotFieldOptions = found ? found.fields : [];
        },

        getEntityObject(name) {
            return this.availableEntities.find(function(e) { return e.name === name; });
        },

        buildPayload() {
            var payload = {
                name: this.form.name,
                label: this.form.label,
                module: this.form.module,
                description: this.form.description,
                report_type: this.form.reportType,
                is_active: this.form.isActive ? 1 : 0,
                roles: this.form.roles,
                original_name: isEdit ? this.form.name : null,
            };

            if (this.form.reportType === 'query') {
                payload.query = {
                    entities: this.form.entities,
                    columns: this.form.columns,
                    filters: this.form.filters,
                    order_by: this.form.orderBy,
                    limit: parseInt(this.form.limit) || 100,
                };
                payload.columns = [];
            } else if (this.form.reportType === 'pivot') {
                var pivotEntity = this.form.pivotEntity;
                payload.query = {
                    type: 'pivot',
                    entities: [{ entity: pivotEntity, alias: 'e' }],
                    row_fields: this.form.rowFields,
                    column_field: this.form.columnField,
                    values: this.form.pivotValues,
                    filters: this.form.filters,
                    limit: 5000,
                };
                payload.columns = [];
            } else {
                payload.query = {
                    sql: this.form.sql,
                    columns: [],
                    limit: 100,
                };
                payload.columns = [];
            }

            payload.charts = [];
            return payload;
        },

        save() {
            var payload = this.buildPayload();
            this.loading = true;

            fetch('<?= site_url('api/reports/save') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(function(r) { return r.json(); })
            .then(function(result) {
                this.loading = false;
                if (result.success) {
                    window.location.href = '<?= site_url('desk/reports') ?>';
                } else {
                    alert('Error: ' + (result.error || 'Save failed.'));
                }
            }.bind(this))
            .catch(function() {
                this.loading = false;
                alert('Save failed.');
            }.bind(this));
        },

        testRun() {
            var payload = this.buildPayload();
            this.loading = true;
            var btn = document.querySelector('#reportForm button[type="submit"]');
            if (btn) btn.textContent = 'Testing...';

            var testQuery = payload.query;
            testQuery.limit = 10;

            fetch('<?= site_url('api/reports/run') ?>/_test', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ query: testQuery })
            })
            .then(function(r) { return r.json(); })
            .then(function(result) {
                this.loading = false;
                if (btn) btn.textContent = 'Save Report';
                if (!result.success) { alert('Error: ' + (result.error || 'Test failed.')); return; }
                showTestResult(result.data);
            }.bind(this))
            .catch(function() {
                this.loading = false;
                if (btn) btn.textContent = 'Save Report';
                alert('Test run failed.');
            }.bind(this));
        }
    };
}

function showTestResult(data) {
    var container = document.getElementById('reportResult');
    container.className = 'mt-4 rounded border border-slate-200 bg-white p-4';
    var html = '<div class="mb-2 flex items-center justify-between"><h3 class="text-sm font-semibold text-slate-900">Test Result</h3>';
    html += '<button onclick="this.parentElement.parentElement.remove()" class="text-xs text-slate-500">Close</button></div>';
    html += '<p class="mb-2 text-xs text-slate-400">' + data.total + ' rows returned</p>';
    if (data.rows && data.rows.length > 0) {
        html += '<div class="overflow-x-auto text-sm"><table class="w-full">';
        html += '<thead><tr class="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">';
        (data.columns || []).forEach(function(col) {
            html += '<th class="px-3 py-2">' + (col.label || col.field) + '</th>';
        });
        html += '</tr></thead><tbody>';
        data.rows.forEach(function(row) {
            html += '<tr class="border-b border-slate-100">';
            (data.columns || []).forEach(function(col) {
                html += '<td class="px-3 py-2 text-slate-700">' + (row[col.field] ?? '') + '</td>';
            });
            html += '</tr>';
        });
        html += '</tbody></table></div>';
    } else {
        html += '<p class="text-sm text-slate-500">No rows returned.</p>';
    }
    container.innerHTML = html;
}
</script>
