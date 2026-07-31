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

<div x-data="reportForm()">
    <div class="claro-page-header">
        <a href="<?= site_url('desk/reports') ?>" class="claro-button claro-button--link" style="font-size:var(--claro-font-size-s);margin-bottom:var(--claro-space-s);display:inline-flex;align-items:center;gap:var(--claro-space-xs)">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M7.78 12.53a.75.75 0 01-1.06 0L2.47 8.28a.75.75 0 010-1.06l4.25-4.25a.75.75 0 011.06 1.06L4.81 7h8.44a.75.75 0 010 1.5H4.81l2.97 2.97a.75.75 0 010 1.06z"/></svg>
            Back to Reports
        </a>
        <h1 class="claro-page-header__title" x-text="isEdit ? 'Edit Report' : 'Create Report'"></h1>
    </div>

    <form id="reportForm" @submit.prevent="save()" class="claro-card" style="padding:0">
        <input type="hidden" name="original_name" :value="form.name">

        <div style="padding:var(--claro-space-m) var(--claro-space-l)">
            <div class="claro-form-item">
                <label class="claro-form-item__label">Report Name</label>
                <input type="text" x-model="form.name" :readonly="isEdit" required class="claro-input" :class="isEdit ? 'claro-input--readonly' : ''" placeholder="monthly_sales">
                <div class="claro-form-item__description">Lowercase, underscores only.</div>
            </div>
            <div class="claro-form-item">
                <label class="claro-form-item__label">Label</label>
                <input type="text" x-model="form.label" required class="claro-input" placeholder="Monthly Sales Report">
            </div>
            <div class="claro-form-item">
                <label class="claro-form-item__label">Module</label>
                <select x-model="form.module" required class="claro-select">
                    <option value="">-- Select Module --</option>
                    <?php foreach ($modules as $mod): ?>
                    <option value="<?= esc($mod['name'] ?? '') ?>"><?= esc($mod['label'] ?? $mod['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="claro-form-item">
                <label class="claro-form-item__label">Description</label>
                <textarea x-model="form.description" rows="2" class="claro-textarea" placeholder="Optional description"></textarea>
            </div>
            <div class="claro-form-item">
                <label class="claro-form-item__label">Report Type</label>
                <div style="display:flex;gap:var(--claro-space-m)">
                    <label class="claro-radio">
                        <input type="radio" name="report_type" value="query" x-model="form.reportType"> Query
                    </label>
                    <label class="claro-radio">
                        <input type="radio" name="report_type" value="pivot" x-model="form.reportType"> Pivot
                    </label>
                    <label class="claro-radio">
                        <input type="radio" name="report_type" value="sql" x-model="form.reportType"> SQL
                    </label>
                </div>
            </div>
            <div class="claro-form-item">
                <label class="claro-checkbox">
                    <input type="checkbox" x-model="form.isActive"> Active
                </label>
            </div>
        </div>

        <!-- Query Builder Section -->
        <div x-show="form.reportType === 'query'" style="border-top:1px solid var(--claro-gray-200)">
            <div style="padding:var(--claro-space-m) var(--claro-space-l)">
                <h3 style="font-size:var(--claro-font-size-s);font-weight:700;margin:0 0 var(--claro-space-m)">Entities</h3>
                <template x-for="(ent, idx) in form.entities" :key="idx">
                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:var(--claro-space-xs);border:1px solid var(--claro-gray-200);border-radius:var(--claro-border-radius);background:var(--claro-gray-50);padding:var(--claro-space-s);margin-bottom:var(--claro-space-xs)">
                        <select x-model="ent.entity" @change="onEntityChange(idx)" class="claro-select" style="font-size:var(--claro-font-size-s);width:auto">
                            <option value="">-- Select Entity --</option>
                            <template x-for="e in availableEntities" :key="e.name">
                                <option :value="e.name" x-text="e.label"></option>
                            </template>
                        </select>
                        <input type="text" x-model="ent.alias" placeholder="Alias" class="claro-input" style="width:5rem;font-size:var(--claro-font-size-s)">
                        <template x-if="idx > 0">
                            <>
                                <select x-model="ent.joinType" class="claro-select" style="font-size:var(--claro-font-size-s);width:auto">
                                    <option value="LEFT">LEFT JOIN</option>
                                    <option value="INNER">INNER JOIN</option>
                                    <option value="RIGHT">RIGHT JOIN</option>
                                    <option value="FULL">FULL JOIN</option>
                                </select>
                                <input type="text" x-model="ent.joinOn" placeholder="e.id = other.e_id" class="claro-input" style="min-width:12rem;font-size:var(--claro-font-size-s);font-family:var(--claro-font-family-monospace)">
                            </>
                        </template>
                        <button type="button" @click="removeEntity(idx)" style="color:var(--claro-color-error);font-size:var(--claro-font-size-xs)">&times;</button>
                    </div>
                </template>
                <button type="button" @click="addEntity()" class="claro-button claro-button--link" style="font-size:var(--claro-font-size-s);display:inline">+ Add Entity</button>

                <h3 style="font-size:var(--claro-font-size-s);font-weight:700;margin:var(--claro-space-l) 0 var(--claro-space-m)">Columns</h3>
                <template x-for="(col, idx) in form.columns" :key="idx">
                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:var(--claro-space-xs);border:1px solid var(--claro-gray-200);border-radius:var(--claro-border-radius);background:var(--claro-gray-50);padding:var(--claro-space-s);margin-bottom:var(--claro-space-xs)">
                        <input type="text" x-model="col.field" placeholder="e.field_name" class="claro-input" style="min-width:10rem;font-size:var(--claro-font-size-s);font-family:var(--claro-font-family-monospace)" list="fieldSuggestions">
                        <input type="text" x-model="col.label" placeholder="Label" class="claro-input" style="width:8rem;font-size:var(--claro-font-size-s)">
                        <select x-model="col.aggregation" class="claro-select" style="font-size:var(--claro-font-size-s);width:auto">
                            <option value="">-- None --</option>
                            <option value="SUM">SUM</option>
                            <option value="COUNT">COUNT</option>
                            <option value="AVG">AVG</option>
                            <option value="MIN">MIN</option>
                            <option value="MAX">MAX</option>
                            <option value="COUNT_DISTINCT">COUNT DISTINCT</option>
                        </select>
                        <button type="button" @click="removeColumn(idx)" style="color:var(--claro-color-error);font-size:var(--claro-font-size-xs)">&times;</button>
                    </div>
                </template>
                <button type="button" @click="addColumn()" class="claro-button claro-button--link" style="font-size:var(--claro-font-size-s);display:inline">+ Add Column</button>

                <datalist id="fieldSuggestions">
                    <template x-for="e in availableEntities" :key="e.name">
                        <template x-for="f in e.fields" :key="e.name + '.' + f.fieldname">
                            <option :value="(e.name.charAt(0)) + '.' + f.fieldname" :label="e.label + ': ' + f.label"></option>
                        </template>
                    </template>
                </datalist>

                <h3 style="font-size:var(--claro-font-size-s);font-weight:700;margin:var(--claro-space-l) 0 var(--claro-space-m)">Filters</h3>
                <template x-for="(f, idx) in form.filters" :key="'f'+idx">
                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:var(--claro-space-xs);border:1px solid var(--claro-gray-200);border-radius:var(--claro-border-radius);background:var(--claro-gray-50);padding:var(--claro-space-s);margin-bottom:var(--claro-space-xs)">
                        <input type="text" x-model="f.field" placeholder="e.field" class="claro-input" style="width:8rem;font-size:var(--claro-font-size-s);font-family:var(--claro-font-family-monospace)">
                        <select x-model="f.operator" class="claro-select" style="font-size:var(--claro-font-size-s);width:auto">
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
                        <input type="text" x-model="f.value" placeholder="Value" class="claro-input" style="width:8rem;font-size:var(--claro-font-size-s)">
                        <button type="button" @click="removeFilter(idx)" style="color:var(--claro-color-error);font-size:var(--claro-font-size-xs)">&times;</button>
                    </div>
                </template>
                <button type="button" @click="addFilter()" class="claro-button claro-button--link" style="font-size:var(--claro-font-size-s);display:inline">+ Add Filter</button>

                <h3 style="font-size:var(--claro-font-size-s);font-weight:700;margin:var(--claro-space-l) 0 var(--claro-space-m)">Order By</h3>
                <template x-for="(o, idx) in form.orderBy" :key="'o'+idx">
                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:var(--claro-space-xs);border:1px solid var(--claro-gray-200);border-radius:var(--claro-border-radius);background:var(--claro-gray-50);padding:var(--claro-space-s);margin-bottom:var(--claro-space-xs)">
                        <input type="text" x-model="o.field" placeholder="e.field" class="claro-input" style="width:8rem;font-size:var(--claro-font-size-s);font-family:var(--claro-font-family-monospace)">
                        <select x-model="o.dir" class="claro-select" style="font-size:var(--claro-font-size-s);width:auto">
                            <option value="asc">ASC</option>
                            <option value="desc">DESC</option>
                        </select>
                        <button type="button" @click="removeOrderBy(idx)" style="color:var(--claro-color-error);font-size:var(--claro-font-size-xs)">&times;</button>
                    </div>
                </template>
                <button type="button" @click="addOrderBy()" class="claro-button claro-button--link" style="font-size:var(--claro-font-size-s);display:inline">+ Add Order</button>

                <div class="claro-form-item" style="margin-bottom:0">
                    <label class="claro-form-item__label">Limit</label>
                    <input type="number" x-model="form.limit" min="1" max="5000" class="claro-input" style="width:5rem">
                </div>
            </div>
        </div>

        <!-- Pivot Builder Section -->
        <div x-show="form.reportType === 'pivot'" style="border-top:1px solid var(--claro-gray-200)">
            <div style="padding:var(--claro-space-m) var(--claro-space-l)">
                <div class="claro-form-item">
                    <label class="claro-form-item__label">Entity</label>
                    <select x-model="form.pivotEntity" @change="onPivotEntityChange()" class="claro-select">
                        <option value="">-- Select Entity --</option>
                        <template x-for="e in availableEntities" :key="e.name">
                            <option :value="e.name" x-text="e.label"></option>
                        </template>
                    </select>
                </div>
                <div class="claro-form-item">
                    <label class="claro-form-item__label">Row Fields</label>
                    <div style="display:flex;flex-wrap:wrap;gap:var(--claro-space-xs)">
                        <template x-for="f in pivotFieldOptions" :key="f.fieldname">
                            <label class="claro-checkbox">
                                <input type="checkbox" :value="'e.'+f.fieldname" x-model="form.rowFields">
                                <span x-text="f.label"></span>
                            </label>
                        </template>
                    </div>
                </div>
                <div class="claro-form-item">
                    <label class="claro-form-item__label">Column Field</label>
                    <select x-model="form.columnField" class="claro-select">
                        <option value="">-- Select --</option>
                        <template x-for="f in pivotFieldOptions" :key="f.fieldname">
                            <option :value="'e.'+f.fieldname" x-text="f.label"></option>
                        </template>
                    </select>
                </div>
                <div class="claro-form-item">
                    <label class="claro-form-item__label">Values</label>
                    <template x-for="(v, idx) in form.pivotValues" :key="'pv'+idx">
                        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:var(--claro-space-xs);border:1px solid var(--claro-gray-200);border-radius:var(--claro-border-radius);background:var(--claro-gray-50);padding:var(--claro-space-s);margin-bottom:var(--claro-space-xs)">
                            <select x-model="v.field" class="claro-select" style="font-size:var(--claro-font-size-s);width:auto">
                                <option value="">-- Field --</option>
                                <option value="*">* (Count All)</option>
                                <template x-for="f in pivotFieldOptions" :key="f.fieldname">
                                    <option :value="'e.'+f.fieldname" x-text="f.label"></option>
                                </template>
                            </select>
                            <select x-model="v.aggregation" class="claro-select" style="font-size:var(--claro-font-size-s);width:auto">
                                <option value="SUM">SUM</option>
                                <option value="COUNT">COUNT</option>
                                <option value="AVG">AVG</option>
                                <option value="MIN">MIN</option>
                                <option value="MAX">MAX</option>
                            </select>
                            <input type="text" x-model="v.label" placeholder="Label" class="claro-input" style="width:7rem;font-size:var(--claro-font-size-s)">
                            <button type="button" @click="removePivotValue(idx)" style="color:var(--claro-color-error);font-size:var(--claro-font-size-xs)">&times;</button>
                        </div>
                    </template>
                    <button type="button" @click="addPivotValue()" class="claro-button claro-button--link" style="font-size:var(--claro-font-size-s);display:inline">+ Add Value</button>
                </div>
                <div class="claro-form-item" style="margin-bottom:0">
                    <label class="claro-form-item__label">Filters</label>
                    <template x-for="(f, idx) in form.filters" :key="'pf'+idx">
                        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:var(--claro-space-xs);border:1px solid var(--claro-gray-200);border-radius:var(--claro-border-radius);background:var(--claro-gray-50);padding:var(--claro-space-s);margin-bottom:var(--claro-space-xs)">
                            <select x-model="f.field" class="claro-select" style="font-size:var(--claro-font-size-s);width:auto">
                                <option value="">-- Field --</option>
                                <template x-for="fopt in pivotFieldOptions" :key="fopt.fieldname">
                                    <option :value="'e.'+fopt.fieldname" x-text="fopt.label"></option>
                                </template>
                            </select>
                            <select x-model="f.operator" class="claro-select" style="font-size:var(--claro-font-size-s);width:auto">
                                <option value="=">=</option>
                                <option value="!=">!=</option>
                                <option value=">">&gt;</option>
                                <option value=">=">&gt;=</option>
                                <option value="<">&lt;</option>
                                <option value="<=">&lt;=</option>
                                <option value="like">Like</option>
                            </select>
                            <input type="text" x-model="f.value" placeholder="Value" class="claro-input" style="width:7rem;font-size:var(--claro-font-size-s)">
                            <button type="button" @click="removeFilter(idx)" style="color:var(--claro-color-error);font-size:var(--claro-font-size-xs)">&times;</button>
                        </div>
                    </template>
                    <button type="button" @click="addFilter()" class="claro-button claro-button--link" style="font-size:var(--claro-font-size-s);display:inline">+ Add Filter</button>
                </div>
            </div>
        </div>

        <!-- SQL Builder Section -->
        <div x-show="form.reportType === 'sql'" style="border-top:1px solid var(--claro-gray-200)">
            <div style="padding:var(--claro-space-m) var(--claro-space-l)">
                <div class="claro-form-item" style="margin-bottom:0">
                    <label class="claro-form-item__label">SQL Query</label>
                    <div class="claro-form-item__description">Only SELECT queries allowed. DDL/DML statements are blocked.</div>
                    <textarea x-model="form.sql" rows="12" class="claro-textarea" style="font-family:var(--claro-font-family-monospace);font-size:var(--claro-font-size-s)" placeholder="SELECT * FROM tab_employee LIMIT 100"></textarea>
                </div>
            </div>
        </div>

        <!-- Roles -->
        <div style="border-top:1px solid var(--claro-gray-200);padding:var(--claro-space-m) var(--claro-space-l)">
            <div class="claro-form-item" style="margin-bottom:0">
                <label class="claro-form-item__label">Role Access</label>
                <div class="claro-form-item__description">Leave empty for all authenticated users.</div>
                <div style="display:flex;flex-wrap:wrap;gap:var(--claro-space-m)">
                    <?php foreach ($roles as $role): ?>
                    <label class="claro-checkbox">
                        <input type="checkbox" value="<?= esc($role['name'] ?? '') ?>" x-model="form.roles" <?= in_array($role['name'] ?? '', $reportRoles, true) ? 'checked' : '' ?>>
                        <?= esc($role['label'] ?? $role['name'] ?? '') ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div style="display:flex;justify-content:flex-end;gap:var(--claro-space-s);border-top:1px solid var(--claro-gray-200);padding:var(--claro-space-s) var(--claro-space-l)">
            <button type="button" @click="testRun()" class="claro-button">Test Run</button>
            <a href="<?= site_url('desk/reports') ?>" class="claro-button">Cancel</a>
            <button type="submit" class="claro-button claro-button--primary">Save Report</button>
        </div>
    </form>

    <div id="reportResult" style="display:none"></div>
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
    container.className = 'claro-card';
    container.style.marginTop = 'var(--claro-space-m)';
    var html = '<div class="claro-card__content">';
    html += '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--claro-space-s)"><h3 style="font-size:var(--claro-font-size-s);font-weight:700;margin:0">Test Result</h3>';
    html += '<button onclick="this.parentElement.parentElement.remove()" class="claro-button claro-button--link" style="font-size:var(--claro-font-size-xs);display:inline">Close</button></div>';
    html += '<p style="font-size:var(--claro-font-size-xs);margin-bottom:var(--claro-space-s)">' + data.total + ' rows returned</p>';
    if (data.rows && data.rows.length > 0) {
        html += '<div class="claro-table" style="overflow-x:auto"><table><thead><tr>';
        (data.columns || []).forEach(function(col) {
            html += '<th>' + (col.label || col.field) + '</th>';
        });
        html += '</tr></thead><tbody>';
        data.rows.forEach(function(row) {
            html += '<tr>';
            (data.columns || []).forEach(function(col) {
                html += '<td>' + (row[col.label] ?? '') + '</td>';
            });
            html += '</tr>';
        });
        html += '</tbody></table></div>';
    } else {
        html += '<p>No rows returned.</p>';
    }
    html += '</div>';
    container.innerHTML = html;
}
</script>
