function employeeFormApp(boot) {
    return {
        title: boot.title || '',
        listUrl: boot.listUrl || '',
        saveUrl: boot.saveUrl || '',
        loadUrlBase: boot.loadUrlBase || '',
        recordName: boot.recordName || '',
        fields: boot.fields || [],
        sessions: boot.sessions || [],
        linkTargets: boot.linkTargets || {},
        form: {},
        linkLookups: {},
        requestUrl(url) {
            const resolved = new URL(String(url || ''), window.location.origin);
            if (resolved.origin === window.location.origin) {
                return resolved.toString();
            }

            return window.location.origin + resolved.pathname + resolved.search + resolved.hash;
        },
        init() {
            if (!Array.isArray(this.sessions) || this.sessions.length === 0) {
                this.sessions = [{ uid: 'primary', title: 'Primary', description: '', column_count: 1 }];
            }

            this.fields.forEach((field) => {
                if (field.fieldtype === 'Check') {
                    this.form[field.fieldname] = String(field.default_value || '') === '1';
                    return;
                }

                if (field.fieldtype === 'Table') {
                    this.form[field.fieldname] = [];
                    return;
                }

                this.form[field.fieldname] = field.default_value ?? '';
            });

            if (this.recordName) {
                this.load();
            }
        },
        addChildRow(fieldname) {
            if (!Array.isArray(this.form[fieldname])) {
                this.form[fieldname] = [];
            }

            this.form[fieldname].push({});
        },
        removeChildRow(fieldname, index) {
            if (!Array.isArray(this.form[fieldname])) {
                return;
            }

            this.form[fieldname].splice(index, 1);
        },
        parseOptions(options) {
            return String(options || '')
                .split(/\n|,/)
                .map((item) => item.trim())
                .filter(Boolean);
        },
        sessionColumnNumbers(session) {
            const count = Math.min(4, Math.max(1, Number(session?.column_count || 1)));
            return Array.from({ length: count }, (_, index) => index + 1);
        },
        sessionGridStyle(session) {
            const count = Math.min(4, Math.max(1, Number(session?.column_count || 1)));
            return 'grid-template-columns: repeat(' + String(count) + ', minmax(0, 1fr));';
        },
        sessionFieldsByColumn(sessionUid, columnNumber) {
            return this.fields.filter((field) => {
                const fieldSession = field.session_uid || this.sessions[0]?.uid || 'primary';
                const fieldColumn = Math.min(4, Math.max(1, Number(field.column || 1)));
                return fieldSession === sessionUid && fieldColumn === columnNumber;
            }).sort((a, b) => a.idx - b.idx);
        },
        inputType(fieldType) {
            if (fieldType === 'Int' || fieldType === 'Float') {
                return 'number';
            }

            if (fieldType === 'Date') {
                return 'date';
            }

            return 'text';
        },
        linkTarget(field) {
            return this.linkTargets?.[field?.fieldname] || null;
        },
        linkLookupState(fieldname) {
            if (!this.linkLookups[fieldname]) {
                this.linkLookups[fieldname] = {
                    open: false,
                    loading: false,
                    query: '',
                    items: [],
                };
            }

            return this.linkLookups[fieldname];
        },
        linkLookupOpen(fieldname) {
            return !!this.linkLookupState(fieldname).open;
        },
        closeLinkLookup(fieldname) {
            this.linkLookupState(fieldname).open = false;
        },
        openLinkLookup(field) {
            if (!field || field.read_only) {
                return;
            }

            const state = this.linkLookupState(field.fieldname);
            state.query = String(this.form[field.fieldname] || '').trim();
            state.open = true;
            this.searchLinkLookup(field);
        },
        handleLinkInput(field) {
            if (!field || field.read_only) {
                return;
            }

            const state = this.linkLookupState(field.fieldname);
            state.query = String(this.form[field.fieldname] || '').trim();
            state.open = true;
            this.searchLinkLookup(field);
        },
        async searchLinkLookup(field) {
            const target = this.linkTarget(field);
            if (!field || !target || !target.data_url) {
                return;
            }

            const state = this.linkLookupState(field.fieldname);
            state.loading = true;

            try {
                const params = new URLSearchParams({
                    page: '1',
                    per_page: '50',
                    q: state.query || '',
                });
                const response = await fetch(this.requestUrl(target.data_url + '?' + params.toString()), {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const result = await response.json();
                if (!response.ok || result.status !== 'ok') {
                    throw new Error(result.message || 'Unable to load linked records.');
                }

                state.items = Array.isArray(result.rows) ? result.rows.slice(0, 50) : [];
            } catch (error) {
                console.error(error);
                state.items = [];
            } finally {
                state.loading = false;
            }
        },
        selectLinkLookupItem(field, item) {
            if (!field || !item || !item.name) {
                return;
            }

            this.form[field.fieldname] = item.name;
            this.linkLookupState(field.fieldname).query = item.name;
            this.closeLinkLookup(field.fieldname);
            this.handleLinkChange(field);
        },
        linkLookupCodeText(item) {
            return String(item?.name || '');
        },
        linkLookupPrimaryText(field, item) {
            const target = this.linkTarget(field);
            const displayField = String(target?.display_field || 'name');
            const displayValue = item && Object.prototype.hasOwnProperty.call(item, displayField)
                ? item[displayField]
                : '';

            if (displayValue !== null && displayValue !== undefined && String(displayValue).trim() !== '') {
                return String(displayValue);
            }

            return String(item?.name || '');
        },
        async handleLinkChange(field) {
            if (!field || field.fieldtype !== 'Link') {
                return;
            }

            const target = this.linkTarget(field);
            const linkValue = String(this.form[field.fieldname] || '').trim();
            if (!target || linkValue === '') {
                this.applyFetchedValues(field, {});
                return;
            }

            try {
                const response = await fetch(this.requestUrl(target.load_url_base + '/' + encodeURIComponent(linkValue)), {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const result = await response.json();
                if (!response.ok || result.status !== 'ok') {
                    throw new Error(result.message || 'Unable to load linked record.');
                }

                this.applyFetchedValues(field, result.data || {});
            } catch (error) {
                console.error(error);
            }
        },
        applyFetchedValues(linkField, linkedRow) {
            const prefix = String(linkField?.fieldname || '') + '.';
            if (prefix === '.') {
                return;
            }

            this.fields.forEach((field) => {
                const fetchFrom = String(field.fetch_from || '').trim();
                if (!fetchFrom.startsWith(prefix)) {
                    return;
                }

                const sourceFieldname = fetchFrom.slice(prefix.length);
                const fetchedValue = linkedRow && Object.prototype.hasOwnProperty.call(linkedRow, sourceFieldname)
                    ? linkedRow[sourceFieldname]
                    : '';
                this.form[field.fieldname] = field.fieldtype === 'Check'
                    ? String(fetchedValue) === '1' || fetchedValue === 1 || fetchedValue === true
                    : fetchedValue;
            });
        },
        async load() {
            const response = await fetch(this.requestUrl(this.loadUrlBase + '/' + encodeURIComponent(this.recordName)), {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const result = await response.json();
            if (!response.ok || result.status !== 'ok') {
                throw new Error(result.message || 'Unable to load record.');
            }

            this.fields.forEach((field) => {
                const hasData = result.data && Object.prototype.hasOwnProperty.call(result.data, field.fieldname);
                const value = hasData ? result.data[field.fieldname] : null;

                if (field.fieldtype === 'Check') {
                    this.form[field.fieldname] = hasData
                        ? String(value) === '1' || value === 1 || value === true
                        : false;
                } else if (field.fieldtype === 'Table') {
                    this.form[field.fieldname] = hasData && Array.isArray(value) ? value : [];
                } else {
                    this.form[field.fieldname] = hasData ? value : (field.default_value ?? '');
                }
            });
        },
        async save() {
            const payload = {};
            this.fields.forEach((field) => {
                const value = this.form[field.fieldname];
                payload[field.fieldname] = value;
            });
            if (this.recordName) {
                payload.name = this.recordName;
            }

            const response = await fetch(this.requestUrl(this.saveUrl), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json; charset=UTF-8',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });
            const result = await response.json();
            if (!response.ok || result.status !== 'ok') {
                throw new Error(result.message || 'Unable to save record.');
            }
            window.location.href = this.listUrl;
        },
    };
}