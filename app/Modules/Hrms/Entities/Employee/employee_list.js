function employeeListApp(boot) {
    return {
        title: boot.title || '',
        dataUrl: boot.dataUrl || '',
        createUrl: boot.createUrl || '',
        editUrlBase: boot.editUrlBase || '',
        deleteUrlBase: boot.deleteUrlBase || '',
        columns: boot.columns || [],
        linkTargets: boot.linkTargets || {},
        isSubmittable: !!boot.isSubmittable,
        submitUrlBase: boot.submitUrlBase || '',
        approveUrlBase: boot.approveUrlBase || '',
        cancelUrlBase: boot.cancelUrlBase || '',
        amendUrlBase: boot.amendUrlBase || '',
        query: '',
        loading: false,
        rows: [],
        page: 1,
        perPage: 50,
        total: 0,
        totalPages: 1,
        perPageOptions: [50, 100, 200, 500, 1000, 2500],
        requestUrl(url) {
            const resolved = new URL(String(url || ''), window.location.origin);
            if (resolved.origin === window.location.origin) {
                return resolved.toString();
            }

            return window.location.origin + resolved.pathname + resolved.search + resolved.hash;
        },
        async init() {
            await this.load(1);
        },
        cellValue(row, fieldname) {
            const value = row && Object.prototype.hasOwnProperty.call(row, fieldname) ? row[fieldname] : '';
            if (value === null || value === undefined || value === '') {
                return '-';
            }

            if (typeof value === 'object') {
                return JSON.stringify(value);
            }

            return value;
        },
        linkDisplayValue(column, row) {
            if (!column || !row) {
                return '-';
            }

            const code = String(row[column.fieldname] || '').trim();
            const display = String(row[column.fieldname + '__display'] || '').trim();
            if (code === '') {
                return '-';
            }

            if (display === '' || display === code) {
                return code;
            }

            return code + ' - ' + display;
        },
        isLinkColumn(column) {
            return String(column?.fieldtype || '') === 'Link';
        },
        linkTarget(column) {
            return this.linkTargets?.[column?.fieldname] || null;
        },
        canOpenLinkedRecord(column, row) {
            const target = this.linkTarget(column);
            const value = row && column ? row[column.fieldname] : '';
            return !!target && String(value || '').trim() !== '';
        },
        openLinkedRecord(column, row) {
            const target = this.linkTarget(column);
            const value = row && column ? row[column.fieldname] : '';
            if (!target || String(value || '').trim() === '') {
                return;
            }

            window.location.href = target.edit_url_base + '/' + encodeURIComponent(String(value).trim());
        },
        paginationText() {
            if (this.total === 0) {
                return '0 rows';
            }

            const start = ((this.page - 1) * this.perPage) + 1;
            const end = Math.min(this.total, this.page * this.perPage);
            return String(start) + '-' + String(end) + ' / ' + String(this.total);
        },
        openEdit(name) {
            if (!name) {
                return;
            }

            window.location.href = this.editUrlBase + '/' + encodeURIComponent(name);
        },
        async deleteRow(name) {
            if (!name || !window.confirm('Delete ' + name + '?')) {
                return;
            }

            const response = await fetch(this.requestUrl(this.deleteUrlBase + '/' + encodeURIComponent(name)), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: '',
            });
            const result = await response.json();
            if (!response.ok || result.status !== 'ok') {
                throw new Error(result.message || 'Unable to delete record.');
            }

            await this.load(this.page);
        },
        workflowStateBadgeClass(state) {
            const s = (state || '').toLowerCase();
            if (s === 'draft') return 'border-zinc-300 bg-zinc-100 text-zinc-700';
            if (s === 'submitted') return 'border-amber-400 bg-amber-50 text-amber-800';
            if (s === 'approved') return 'border-emerald-400 bg-emerald-50 text-emerald-800';
            if (s === 'cancelled') return 'border-red-300 bg-red-50 text-red-700';
            return 'border-zinc-300 bg-zinc-100 text-zinc-700';
        },
        async workflowAction(name, urlBase) {
            if (!name) return;
            const response = await fetch(this.requestUrl(urlBase + '/' + encodeURIComponent(name)), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const result = await response.json();
            if (!response.ok || result.status !== 'ok') {
                alert(result.message || 'Workflow action failed.');
                return;
            }
            await this.load(this.page);
        },
        async submitRow(name) {
            await this.workflowAction(name, this.submitUrlBase);
        },
        async approveRow(name) {
            await this.workflowAction(name, this.approveUrlBase);
        },
        async cancelRow(name) {
            await this.workflowAction(name, this.cancelUrlBase);
        },
        async amendRow(name) {
            await this.workflowAction(name, this.amendUrlBase);
        },
        async load(page = 1) {
            this.loading = true;
            this.page = Math.max(1, page);
            try {
                const params = new URLSearchParams({
                    page: String(this.page),
                    per_page: String(this.perPage),
                    q: this.query || '',
                });
                const response = await fetch(this.requestUrl(this.dataUrl + '?' + params.toString()), {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const result = await response.json();
                if (!response.ok || result.status !== 'ok') {
                    throw new Error(result.message || 'Unable to load list.');
                }

                this.rows = Array.isArray(result.rows) ? result.rows : [];
                this.page = Number(result.pagination?.page || this.page);
                this.perPage = Number(result.pagination?.per_page || this.perPage);
                this.total = Number(result.pagination?.total || 0);
                this.totalPages = Number(result.pagination?.total_pages || 1);
                this.perPageOptions = Array.isArray(result.pagination?.options) ? result.pagination.options : this.perPageOptions;
            } catch (error) {
                console.error(error);
                this.rows = [];
                this.total = 0;
                this.totalPages = 1;
            } finally {
                this.loading = false;
            }
        },
    };
}