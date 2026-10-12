(() => {
    const form = document.querySelector('[data-catalog-progress]');
    const button = document.querySelector('[data-catalog-save]');
    const feedback = document.querySelector('[data-catalog-save-feedback]');
    if (!form || !button || !feedback) return;
    const label = button.querySelector('[data-save-label]');
    const creating = form.dataset.saveMode === 'create';
    const signature = () => JSON.stringify([...form.elements].filter(field => field.name && field.type !== 'hidden')
        .map(field => [field.name, field.value, ['checkbox', 'radio'].includes(field.type) ? field.checked : null]));
    let baseline = signature();
    let retainedError = form.dataset.saveDirty === 'true';
    let busy = false;
    let lockedFields = [];
    const errorAttributes = new Map();
    const clearErrors = () => {
        errorAttributes.forEach((previous, field) => {
            for (const [attribute, value] of Object.entries(previous)) {
                if (value === null) field.removeAttribute(attribute);
                else field.setAttribute(attribute, value);
            }
        });
        errorAttributes.clear();
        const errors = [...form.querySelectorAll('.app-field-error')];
        const errorIds = new Set(errors.map(error => error.id).filter(Boolean));
        for (const field of form.elements) {
            const described = (field.getAttribute('aria-describedby') || '').split(' ').filter(Boolean);
            if (described.some(id => errorIds.has(id))) {
                const remaining = described.filter(id => !errorIds.has(id));
                if (remaining.length) field.setAttribute('aria-describedby', remaining.join(' '));
                else field.removeAttribute('aria-describedby');
                field.removeAttribute('aria-invalid');
            }
        }
        errors.forEach(error => error.remove());
        document.querySelector('[aria-label="Validation errors"]:not([data-catalog-save-feedback])')?.remove();
        feedback.replaceChildren();
        feedback.hidden = true;
    };
    const dirty = () => retainedError || signature() !== baseline;
    const show = (message, error = false) => {
        feedback.textContent = message;
        feedback.className = error ? 'error' : 'notice';
        feedback.setAttribute('role', error ? 'alert' : 'status');
        feedback.hidden = false;
        feedback.tabIndex = -1;
        feedback.removeAttribute('aria-label');
    };
    const showValidation = errors => {
        show('The record could not be saved. Correct the fields below and try again.', true);
        feedback.setAttribute('aria-label', 'Validation errors');
        const heading = document.createElement('p');
        heading.textContent = feedback.textContent;
        feedback.replaceChildren(heading);
        let errorIndex = 0;
        const aliases = { categories: 'category_id', collections: 'collection_id', purposes: 'purpose_id' };
        for (const [key, rawMessages] of Object.entries(errors)) {
            const fieldKey = aliases[key] || key;
            const controls = [...form.elements].filter(field => {
                if (!field.name || field.type === 'hidden') return false;
                const name = field.name.replace(/\[([^\]]*)\]/g, '.$1').replace(/\.$/, '');
                return name === fieldKey || fieldKey.startsWith(name + '.');
            });
            for (const message of Array.isArray(rawMessages) ? rawMessages : [rawMessages]) {
                const summary = document.createElement('p');
                summary.dataset.errorKey = key;
                const target = controls[0];
                if (target) {
                    if (!target.id) target.id = 'catalog-error-target-' + errorIndex;
                    const link = document.createElement('a');
                    link.href = '#' + target.id;
                    const label = target.labels?.[0]?.cloneNode(true);
                    label?.querySelectorAll('input, select, textarea, .app-field-help, .app-field-error').forEach(node => node.remove());
                    link.textContent = (target.getAttribute('aria-label') || label?.textContent.trim() || key) + ': ' + message;
                    link.addEventListener('click', () => target.focus());
                    summary.append(link);
                } else summary.textContent = message;
                feedback.append(summary);
                controls.forEach((field, index) => {
                    if (!errorAttributes.has(field)) errorAttributes.set(field, {
                        'aria-invalid': field.getAttribute('aria-invalid'),
                        'aria-describedby': field.getAttribute('aria-describedby'),
                    });
                    const inline = document.createElement('p');
                    inline.id = 'catalog-field-error-' + errorIndex + '-' + index;
                    inline.className = 'app-field-error';
                    inline.textContent = message;
                    (field.closest('label') || field).insertAdjacentElement('afterend', inline);
                    field.setAttribute('aria-invalid', 'true');
                    field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), inline.id].filter(Boolean).join(' '));
                });
                errorIndex++;
            }
        }
    };
    const render = () => {
        button.classList.toggle('app-save-dirty', dirty());
        button.disabled = busy;
        button.setAttribute('aria-busy', String(busy));
        form.dataset.saveBusy = String(busy);
        label.textContent = busy ? (creating ? 'Creating…' : 'Saving…') : (creating ? 'Create' : 'Save');
        const archiveNotice = document.querySelector('[data-catalog-archive-unsaved]');
        if (archiveNotice) archiveNotice.hidden = !dirty();
    };
    window.appNavigationGuards.push({ modal: true, shouldWarn: () => dirty(), message: 'Your changes have not been saved. Keep editing or leave without saving.' });
    form.addEventListener('input', render);
    form.addEventListener('change', render);
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy) return;
        clearErrors();
        const submitted = signature();
        const payload = new FormData(form);
        const editable = [...form.elements].filter(field => !field.disabled && ['INPUT', 'SELECT', 'TEXTAREA'].includes(field.tagName) && field.type !== 'hidden');
        lockedFields = editable;
        let navigating = false;
        let focusFeedback = false;
        busy = true;
        editable.forEach(field => { field.disabled = true; });
        render();
        try {
            const response = await fetch(form.action, {
                method: 'POST', credentials: 'same-origin', body: payload,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const result = await response.json();
            if (!response.ok) {
                if (result.errors && typeof result.errors === 'object' && Object.keys(result.errors).length) {
                    retainedError = true;
                    showValidation(result.errors);
                    focusFeedback = true;
                    return;
                }
                throw new Error(result.message || 'The record could not be saved. Your inputs are retained.');
            }
            if (!Number.isInteger(result.id) || result.id < 1) throw new Error('The save response could not be confirmed. Your inputs are retained.');
            baseline = submitted;
            retainedError = false;
            window.location.assign(form.dataset.recordBase + result.id);
            navigating = true;
        } catch (error) {
            retainedError = true;
            focusFeedback = true;
            show(error instanceof SyntaxError || error instanceof TypeError ? 'The record could not be saved. Your inputs are retained; check your connection or sign in again if your session expired.' : error.message, true);
        } finally {
            if (!navigating) {
                busy = false;
                editable.forEach(field => { field.disabled = false; });
                lockedFields = [];
                render();
                if (focusFeedback) feedback.focus();
            }
        }
    });
    const approved = new WeakSet();
    // Do not leave or change status while the current save is still in flight.
    window.addEventListener('click', event => {
        if (busy && event.target.closest('a[href], [data-catalog-archive-trigger]')) {
            event.preventDefault(); event.stopPropagation(); show('Wait for the save to finish before leaving.');
        }
    }, true);
    document.addEventListener('submit', event => {
        if (event.target === form) return;
        if (busy) { event.preventDefault(); show('Wait for the save to finish before changing status.'); return; }
        if (event.target.id === 'catalog-archive-form' || approved.has(event.target) || !dirty()) return;
        event.preventDefault();
        const target = event.target;
        const submitter = event.submitter;
        window.appRequestDecision('Your edits have not been saved. Changing status will leave without saving those edits.', () => {
            approved.add(target);
            target.requestSubmit(submitter);
            approved.delete(target);
        }, submitter, 'Leave without saving', true);
    }, true);
    window.addEventListener('pageshow', () => {
        busy = false;
        lockedFields.forEach(field => { field.disabled = false; });
        lockedFields = [];
        render();
    });
    render();
})();
