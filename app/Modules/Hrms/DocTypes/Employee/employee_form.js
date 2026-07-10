function employeeFormApp(boot) {
    return {
        title: boot.title || '',
        listUrl: boot.listUrl || '',
        saveUrl: boot.saveUrl || '',
        loadUrlBase: boot.loadUrlBase || '',
        recordName: boot.recordName || '',
        fields: boot.fields || [],
        csrfTokenName: boot.csrfTokenName || '',
        csrfHash: boot.csrfHash || '',
        form: {},
        init() {
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
            const response = await fetch(this.loadUrlBase + '/' + encodeURIComponent(this.recordName), {
                headers: { Accept: 'application/json' },
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

            const response = await fetch(this.saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfHash,
                },
                body: new URLSearchParams({
                    ...payload,
                    [this.csrfTokenName]: this.csrfHash,
                }).toString(),
            });
            const result = await response.json();
            if (!response.ok || result.status !== 'ok') {
                throw new Error(result.message || 'Unable to save record.');
            }

            window.location.href = this.listUrl;
        },
    };
}
