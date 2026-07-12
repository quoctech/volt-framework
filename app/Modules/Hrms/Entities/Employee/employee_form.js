function employeeFormApp(boot) {
    return {
        title: boot.title || '',
        listUrl: boot.listUrl || '',
        saveUrl: boot.saveUrl || '',
        loadUrlBase: boot.loadUrlBase || '',
        recordName: boot.recordName || '',
        fields: boot.fields || [],
        sessions: boot.sessions || [],
        form: {},
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

                this.form[field.fieldname] = field.default_value ?? '';
            });

            if (this.recordName) {
                this.load();
            }
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
            });
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
                const value = result.data && Object.prototype.hasOwnProperty.call(result.data, field.fieldname)
                    ? result.data[field.fieldname]
                    : (field.default_value ?? '');
                this.form[field.fieldname] = field.fieldtype === 'Check'
                    ? String(value) === '1' || value === 1 || value === true
                    : value;
            });
        },
        async save() {
            const payload = {};
            this.fields.forEach((field) => {
                const value = this.form[field.fieldname];
                payload[field.fieldname] = field.fieldtype === 'Check' ? (value ? '1' : '0') : value;
            });
            if (this.recordName) {
                payload.name = this.recordName;
            }

            const response = await fetch(this.requestUrl(this.saveUrl), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new URLSearchParams(payload).toString(),
            });
            const result = await response.json();
            if (!response.ok || result.status !== 'ok') {
                throw new Error(result.message || 'Unable to save record.');
            }
            window.location.href = this.listUrl;
        },
    };
}