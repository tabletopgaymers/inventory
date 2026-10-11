'use strict';
(() => {
    const start = () => {
        const toolbar = document.querySelector('[data-relocation-progress]');
        if (!toolbar) return;
        const button = document.querySelector('[data-save-progress]');
        if (!button) return;
        const label = button.querySelector('[data-save-label]');
        const feedback = document.querySelector('[data-save-feedback]');
        const forms = [...document.querySelectorAll('[data-relocation-form]')];
        const fields = form => [...form.elements].filter(input => input.name && !input.disabled && ['INPUT', 'SELECT', 'TEXTAREA'].includes(input.tagName) && input.type !== 'hidden');
        const values = form => fields(form).map(input => ({ name: input.name, type: input.type, value: input.value, checked: input.checked }));
        const signature = form => JSON.stringify(values(form));
        const initial = new Map(forms.map(form => [form, signature(form)]));
        const key = `relocation-progress:${location.origin}:${toolbar.dataset.saveKey}`;
        let dirty = toolbar.dataset.saveDirty === 'true';
        let busy = false;
        let locked = false;
        let generation = 0;
        let revision = toolbar.dataset.saveRevision;
        const show = (message, error = false) => {
            feedback.hidden = false;
            feedback.className = error ? 'error' : 'notice';
            feedback.setAttribute('role', error ? 'alert' : 'status');
            feedback.replaceChildren(document.createTextNode(message));
        };
        const render = () => {
            button.classList.toggle('app-save-dirty', dirty);
            button.disabled = busy || locked || !toolbar.dataset.saveUrl;
            button.setAttribute('aria-busy', busy ? 'true' : 'false');
            label.textContent = busy ? 'Saving…' : 'Save Progress';
        };
        const retain = () => {
            if (!dirty) return;
            const cached = { revision, version: toolbar.dataset.saveVersion, forms: Object.fromEntries(forms.map(form => [form.id, values(form)])) };
            try { sessionStorage.setItem(key, JSON.stringify(cached)); }
            catch { show('This browser cannot retain unsaved input. Save Progress before leaving this page.', true); }
        };
        const restore = (form, saved) => {
            if (!Array.isArray(saved)) return;
            if (form.dataset.relocationForm === 'work' && fields(form).length) {
                const names = new Set(saved.map(field => field.name));
                form.querySelectorAll('[data-request-quantity]').forEach(input => {
                    if (!names.has(input.name)) input.closest('[data-item-id]')?.remove();
                });
            }
            saved.forEach(field => {
                const input = fields(form).find(input => input.name === field.name && input.type === field.type && (!['checkbox', 'radio'].includes(input.type) || input.value === field.value));
                if (!input) return;
                const changedValue = input.value !== field.value;
                if (['checkbox', 'radio'].includes(input.type)) input.checked = Boolean(field.checked);
                else input.value = field.value;
                input.closest('details')?.setAttribute('open', '');
                input.dispatchEvent(new Event('input', { bubbles: true }));
                if (changedValue && ['source_location_id', 'destination_location_id'].includes(input.name)) input.dispatchEvent(new Event('change', { bubbles: true }));
            });
        };
        let cached;
        try { cached = JSON.parse(sessionStorage.getItem(key)); } catch {}
        if (cached && cached.forms && typeof cached.forms === 'object') {
            const sessionPage = toolbar.dataset.saveKey.includes(':work:') || toolbar.dataset.saveKey.includes(':counts:');
            if (!sessionPage || String(cached.version) === toolbar.dataset.saveVersion) {
                dirty = true;
                revision = String(cached.revision ?? revision);
                const missing = Object.keys(cached.forms).some(id => !forms.some(form => form.id === id));
                if (missing) {
                    locked = true;
                    show('Retained unsaved fields are no longer editable on this page. Nothing has been saved or discarded. Return to an authorized working page before saving.', true);
                } else {
                    forms.forEach(form => restore(form, cached.forms[form.id]));
                    if (revision !== toolbar.dataset.saveRevision) {
                        show('The saved request changed while you were away. Your older unsaved values are shown; saving with their old revision will be rejected. Review the current saved request before resolving these changes.', true);
                    }
                }
            } else {
                // A server session action retained the full input more recently than this cache.
                try { sessionStorage.removeItem(key); } catch {}
            }
        }
        forms.forEach(form => {
            const changed = () => { dirty = true; generation++; retain(); render(); };
            form.addEventListener('input', changed);
            form.addEventListener('change', changed);
        });
        const errors = data => {
            show(data.message || 'Progress could not be saved. Your entered values are retained.', true);
            Object.entries(data.errors || {}).forEach(([name, messages]) => {
                const input = forms.flatMap(form => [...form.elements]).find(input => input.name?.replace(/\[([^\]]+)\]/g, '.$1') === name);
                const line = document.createElement('p');
                const target = input || (name === 'lines' || name === 'selection' || name.startsWith('selection.') ? document.getElementById('requested-items') : null);
                if (target?.id) {
                    const link = document.createElement('a'); link.href = `#${target.id}`; link.textContent = messages.join(' ');
                    link.addEventListener('click', () => { target.closest('section[hidden]')?.removeAttribute('hidden'); target.closest('details')?.setAttribute('open', ''); target.focus(); });
                    line.append(link);
                } else line.textContent = messages.join(' ');
                feedback.append(line);
            });
            feedback.tabIndex = -1; feedback.focus();
        };
        const save = async () => {
            if (busy || locked || !toolbar.dataset.saveUrl || !forms.length) return;
            const details = forms.every(form => ['title', 'fulfillment', 'tracking'].includes(form.dataset.relocationForm));
            const included = details ? forms.filter(form => signature(form) !== initial.get(form)) : forms;
            const selected = included.length ? included : forms;
            const data = details ? new FormData() : new FormData(selected[0]);
            if (details) selected.forEach(form => {
                data.append('progress_sections[]', form.dataset.relocationForm);
                for (const [name, value] of new FormData(form)) if (!['_token', '_deliberate', 'revision'].includes(name)) data.append(name, value);
            });
            data.set('_token', forms[0].querySelector('[name="_token"]').value);
            data.set('_deliberate', '1'); data.set('action', 'progress'); data.set('revision', revision);
            const sentGeneration = generation;
            const sentValues = new Map(forms.map(form => [form, signature(form)]));
            retain(); busy = true; render();
            try {
                const response = await fetch(toolbar.dataset.saveUrl, { method: 'POST', body: data, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const result = (response.headers.get('content-type') || '').includes('application/json') ? await response.json() : {};
                if (!response.ok || result.saved !== true || !Number.isInteger(result.revision)) {
                    dirty = true; errors(result); return;
                }
                revision = String(result.revision); toolbar.dataset.saveRevision = revision;
                if (result.version !== undefined) toolbar.dataset.saveVersion = String(result.version);
                document.querySelectorAll('input[name="revision"]').forEach(input => { input.value = revision; });
                forms.forEach(form => initial.set(form, sentValues.get(form)));
                if (generation === sentGeneration) {
                    if (result.values) forms.forEach(form => {
                        [...form.elements].forEach(input => {
                            if (Object.hasOwn(result.values, input.name)) input.value = result.values[input.name] ?? '';
                            const item = input.name.match(/^lines\[(\d+)\]$/)?.[1];
                            if (item && Object.hasOwn(result.values.lines, item)) input.value = result.values.lines[item];
                        });
                        initial.set(form, signature(form));
                    });
                    if (result.counts) forms.forEach(form => [...form.elements].forEach(input => {
                        if (['shipped_date', 'received_date'].includes(input.name)) input.value = result.counts.date;
                        if (input.name === 'explanation') input.value = result.counts.explanation;
                        const item = input.name?.match(/^lines\[(\d+)\]\[(?:sent|received)\]$/)?.[1];
                        if (item && Object.hasOwn(result.counts.lines, item)) input.value = result.counts.lines[item];
                    }));
                    if (result.fields) forms.forEach(form => {
                        const canonical = result.fields[form.dataset.relocationForm];
                        if (canonical) fields(form).forEach(input => { if (Object.hasOwn(canonical, input.name)) input.value = canonical[input.name]; });
                    });
                    if (result.fields?.title) document.querySelector('.app-page-heading h1')?.replaceChildren(document.createTextNode(result.fields.title.title));
                    forms.forEach(form => initial.set(form, signature(form)));
                    if (result.values) {
                        document.querySelector('[data-relocation-title]')?.replaceChildren(document.createTextNode(result.values.title));
                        document.querySelector('[data-relocation-details]')?.replaceChildren(document.createTextNode(result.values.details));
                    }
                    dirty = false;
                    try { sessionStorage.removeItem(key); } catch {}
                } else { dirty = true; retain(); }
                show(result.message || 'Progress saved.');
                if (result.id) {
                    let identity = document.querySelector('[data-relocation-identity]');
                    if (!identity) { identity = document.createElement('p'); identity.dataset.relocationIdentity = ''; document.querySelector('.app-content').prepend(identity); }
                    identity.textContent = `Relocation #${result.id} · ${result.status || 'Draft'}`;
                }
            } catch { dirty = true; show('Progress could not be saved. Your entered values are retained; retry after checking the connection.', true); }
            finally { busy = false; if (dirty) retain(); render(); }
        };
        button.addEventListener('click', save);
        forms.forEach(form => form.addEventListener('submit', event => {
            if (busy || locked) { event.preventDefault(); return; }
            retain();
            if (['title', 'fulfillment', 'tracking'].includes(form.dataset.relocationForm)) { event.preventDefault(); save(); }
        }));
        toolbar.querySelectorAll('[data-relocation-work-action]').forEach(link => link.addEventListener('click', event => {
            event.preventDefault();
            if (busy || locked) return;
            const form = forms.find(form => form.dataset.relocationForm === 'work');
            if (!form) return;
            retain();
            const control = document.createElement('button'); control.type = 'submit'; control.name = 'action'; control.value = link.dataset.relocationWorkAction; control.formNoValidate = true; control.hidden = true;
            form.append(control); form.requestSubmit(control); control.remove();
        }));
        window.appNavigationGuards?.push({ shouldWarn: origin => !busy && dirty && !origin?.hasAttribute('data-relocation-work-action'), message: 'Progress has not been saved. Save Progress makes it durable; working values are retained in this browser or session.' });
        document.addEventListener('click', event => {
            if (busy && event.target.closest('a[href]')) { event.preventDefault(); show('Wait for Saving… to finish before leaving.'); }
        }, true);
        window.addEventListener('pagehide', retain);
        window.addEventListener('pageshow', () => { busy = false; render(); });
        if (dirty && forms.length) retain();
        render();
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true }); else start();
})();
