'use strict';
(() => {
    const start = () => {
        const toolbar = document.querySelector('[data-purchase-progress]');
        const button = document.querySelector('[data-purchase-save-progress]');
        if (!toolbar || !button) return;
        const label = button.querySelector('[data-save-label]');
        const feedback = document.querySelector('[data-purchase-save-feedback]');
        const forms = [...document.querySelectorAll('[data-purchase-form]')];
        const fields = form => [...form.elements].filter(input => input.name && !input.disabled && ['INPUT', 'SELECT', 'TEXTAREA'].includes(input.tagName) && (input.type !== 'hidden' || input.hasAttribute('data-purchase-field')));
        const values = form => fields(form).map(input => ({ name: input.name, type: input.type, value: input.value, checked: input.checked }));
        const key = 'purchase-progress:' + location.origin + ':' + toolbar.dataset.saveKey;
        let dirty = toolbar.dataset.saveDirty === 'true';
        let busy = false;
        let locked = false;
        let generation = 0;
        let restoring = false;
        let revision = toolbar.dataset.saveRevision;
        let actionNonce = '';
        const work = toolbar.dataset.saveKey.includes(':work:');
        const workReview = forms.some(form => form.dataset.purchaseForm === 'work-review');
        const acknowledgeVersion = result => {
            if (!Number.isInteger(result.version)) return;
            toolbar.dataset.saveVersion = String(result.version);
            forms.forEach(form => { if (form.querySelector('[name="work_version"]')) form.querySelector('[name="work_version"]').value = result.version; });
        };
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
            label.textContent = busy ? 'Saving…' : 'Save progress';
        };
        const showRetained = savedForms => {
            if (!savedForms || feedback.querySelector('[data-retained-input]')) return;
            const details = document.createElement('details'); details.dataset.retainedInput = '';
            const summary = document.createElement('summary'); summary.textContent = 'Retained working input'; details.append(summary);
            const list = document.createElement('dl');
            Object.values(savedForms).flat().forEach(field => {
                if (['checkbox', 'radio'].includes(field.type) && !field.checked) return;
                const term = document.createElement('dt'); term.textContent = field.name;
                const value = document.createElement('dd'); value.className = 'plain-text'; value.textContent = field.value || '(blank)'; list.append(term, value);
            });
            details.append(list); feedback.append(details);
        };
        const retain = () => {
            if (!dirty || !forms.length || locked) return;
            const cached = { revision, version: toolbar.dataset.saveVersion, actionNonce, forms: Object.fromEntries(forms.map(form => [form.id, values(form)])) };
            try { sessionStorage.setItem(key, JSON.stringify(cached)); }
            catch { show('This browser cannot retain unsaved input. Save progress before leaving.', true); }
        };
        const restore = (form, saved) => {
            if (!Array.isArray(saved)) return;
            if (form.dataset.purchaseForm === 'preparation' && window.appRestorePurchaseLines?.(form, saved) === false) {
                locked = true;
                show('Some retained catalog rows are no longer available. Your retained input has not been saved or discarded. Review the current catalog and request before resolving these changes.', true);
            }
            saved.forEach(field => {
                const input = fields(form).find(input => input.name === field.name && (toolbar.dataset.saveKey.includes(':work:') || input.type === field.type) && (!['checkbox', 'radio'].includes(input.type) || input.value === field.value));
                if (!input) {
                    locked = true;
                    show('Some retained fields are no longer available. Nothing has been saved or discarded. Return to the original working page before saving.', true);
                    return;
                }
                const changedValue = ['checkbox', 'radio'].includes(input.type) ? input.checked !== Boolean(field.checked) : input.value !== field.value;
                if (['checkbox', 'radio'].includes(input.type)) input.checked = Boolean(field.checked);
                else input.value = field.value;
                input.closest('details')?.setAttribute('open', '');
                if (changedValue) input.dispatchEvent(new Event('input', { bubbles: true }));
            });
            // Refresh display totals without changing retained exact prices or basis.
            document.dispatchEvent(new Event('purchase-invoice-restored'));
        };
        let cached;
        try { cached = JSON.parse(sessionStorage.getItem(key)); } catch {}
        const legacyKey = document.querySelector('[data-purchase-legacy-key]')?.dataset.purchaseLegacyKey;
        if (!cached && legacyKey && forms.length) {
            try {
                const legacy = JSON.parse(sessionStorage.getItem('purchase-progress:' + location.origin + ':' + legacyKey));
                const saved = legacy?.forms && Object.values(legacy.forms).flat();
                if (saved?.length) cached = { revision, version: toolbar.dataset.saveVersion, forms: { [forms[0].id]: saved.filter(field => ['title', 'suggested_merchant', 'details'].includes(field.name)) } };
            } catch {}
        }
        if (workReview && cached?.forms && String(cached.version) === toolbar.dataset.saveVersion && Object.values(cached.forms).some(saved => saved.some(field => field.type !== 'hidden'))) {
            location.replace(toolbar.dataset.saveUrl + '?step=items');
            return;
        }
        if (cached?.forms && typeof cached.forms === 'object') {
            if (![':invoice:', ':work:'].some(part => toolbar.dataset.saveKey.includes(part)) || String(cached.version) === toolbar.dataset.saveVersion) {
                dirty = true;
                revision = String(cached.revision ?? revision);
                if (Object.keys(cached.forms).some(id => !forms.some(form => form.id === id))) {
                    locked = true;
                    show('Retained fields are no longer editable on this page. Nothing has been saved or discarded. Return to an authorized working page.', true);
                } else forms.forEach(form => restore(form, cached.forms[form.id]));
            } else if (cached.actionNonce && cached.actionNonce === toolbar.querySelector('[data-purchase-action-ack]')?.value) {
                // Add/Remove/Review retained the complete input in a newer server session.
                try { sessionStorage.removeItem(key); } catch {}
            } else {
                dirty = true; locked = true;
                show('The working request changed while this browser retained newer input. Your retained input has not been saved or discarded. Return to the original page to resolve these changes.', true);
                showRetained(cached.forms);
            }
        }
        if (revision !== toolbar.dataset.currentRevision && forms.length) {
            dirty = true;
            show('The saved purchase changed while you were away. Your older working values are retained with their old revision; saving or confirming will be rejected. Review the current saved purchase before resolving these changes.', true);
        }
        forms.forEach(form => {
            const changed = () => { if (restoring) return; actionNonce = ''; dirty = true; generation++; retain(); render(); };
            form.addEventListener('input', changed);
            form.addEventListener('change', changed);
        });
        // Notes and Mark shipped keep their separate actions and generic navigation guards.
        document.querySelectorAll('[data-purchase-retain-form]').forEach(form => {
            const auxKey = key + ':' + form.id;
            const originalAux = JSON.stringify(values(form));
            const retainAux = () => {
                if (JSON.stringify(values(form)) === originalAux) {
                    try { sessionStorage.removeItem(auxKey); } catch {}
                    return;
                }
                try { sessionStorage.setItem(auxKey, JSON.stringify({ fields: values(form), revision: form.querySelector('[name="revision"]')?.value })); }
                catch { show('This browser cannot retain this unsaved input. Keep this page open until its separate action is saved.', true); }
            };
            let saved;
            try {
                if (toolbar.dataset.retainedAck === form.id) sessionStorage.removeItem(auxKey);
                saved = JSON.parse(sessionStorage.getItem(auxKey));
            } catch {}
            if (saved?.fields) {
                restore(form, saved.fields);
                if (saved.revision !== undefined && form.querySelector('[name="revision"]')) form.querySelector('[name="revision"]').value = saved.revision;
            }
            form.addEventListener('input', retainAux);
            form.addEventListener('change', retainAux);
            window.addEventListener('pagehide', retainAux);
        });
        const errors = (data, status) => {
            show(data.message || (status === 409 ? 'The purchase changed. Your values are retained; review the saved purchase before resolving this conflict.' : 'Progress could not be saved. Your entered values are retained.'), true);
            Object.entries(data.errors || {}).forEach(([name, messages]) => {
                const input = forms.flatMap(form => [...form.elements]).find(input => input.name?.replace(/\[([^\]]+)\]/g, '.$1') === name);
                const line = document.createElement('p');
                if (input && !input.id) input.id = 'purchase-error-' + forms.flatMap(form => [...form.elements]).indexOf(input);
                if (input?.id) {
                    const link = document.createElement('a'); link.href = '#' + input.id; link.textContent = messages.join(' ');
                    link.addEventListener('click', () => { input.closest('section[hidden]')?.removeAttribute('hidden'); input.focus(); }); line.append(link);
                } else line.textContent = messages.join(' ');
                feedback.append(line);
            });
            feedback.tabIndex = -1; feedback.focus();
        };
        const save = async () => {
            if (busy || locked || !toolbar.dataset.saveUrl || !forms.length) return;
            const form = forms[0];
            const pending = form.querySelector('[name="add_item"], [data-purchase-item]');
            const pendingSelection = [...form.querySelectorAll('[name^="selection["]')].some(input => input.value !== '');
            if (pendingSelection) { dirty = true; retain(); render(); show('Use Add to purchase or clear pending quantities before saving progress.', true); return; }
            if (pending?.value) { dirty = true; retain(); render(); show('Use Add item or clear the item choice before saving progress.', true); return; }
            const data = new FormData(form);
            data.set('_deliberate', '1'); data.set('action', 'progress'); data.set('revision', revision);
            const sentGeneration = generation;
            retain(); busy = true; render();
            try {
                const response = await fetch(toolbar.dataset.saveUrl, { method: 'POST', body: data, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const result = (response.headers.get('content-type') || '').includes('application/json') ? await response.json() : {};
                if (!response.ok || result.saved !== true || !Number.isInteger(result.revision) || !result.fields) {
                    if (result.retained === true) acknowledgeVersion(result);
                    dirty = true; errors(result, response.status); return;
                }
                revision = String(result.revision); toolbar.dataset.saveRevision = revision; toolbar.dataset.currentRevision = revision;
                actionNonce = '';
                acknowledgeVersion(result);
                if (result.review_hash) forms.forEach(form => { if (form.querySelector('[name="review_hash"]')) form.querySelector('[name="review_hash"]').value = result.review_hash; });
                if (result.confirmation_hash) forms.forEach(form => { if (form.querySelector('[name="confirmation_hash"]')) form.querySelector('[name="confirmation_hash"]').value = result.confirmation_hash; });
                forms.forEach(form => { if (form.querySelector('[name="revision"]')) form.querySelector('[name="revision"]').value = revision; });
                if (generation === sentGeneration) {
                    forms.forEach(form => fields(form).forEach(input => {
                        if (Object.hasOwn(result.fields, input.name)) input.value = result.fields[input.name] ?? '';
                    }));
                    document.dispatchEvent(new Event('purchase-invoice-restored'));
                    document.querySelectorAll('[data-purchase-display]').forEach(element => { if (Object.hasOwn(result.fields, element.dataset.purchaseDisplay)) element.textContent = result.fields[element.dataset.purchaseDisplay] || 'Not provided'; });
                    dirty = false;
                    try { sessionStorage.removeItem(key); } catch {}
                } else { dirty = true; retain(); }
                show(result.message || 'Progress saved.');
                if (result.id) {
                    const review = document.querySelector('[data-purchase-review-link]');
                    if (review) { review.href = '/purchases/' + result.id; review.hidden = false; }
                    let identity = document.querySelector('[data-purchase-identity]');
                    if (!identity && form.dataset.purchaseForm.startsWith('work')) { identity = document.createElement('p'); identity.dataset.purchaseIdentity = ''; form.before(identity); }
                    if (identity) identity.textContent = 'Purchase #' + result.id + ' · ' + result.status;
                    const state = document.querySelector('[data-working-state]');
                    if (state) state.textContent = 'Purchase #' + result.id + ' · ' + result.status + '. Save progress keeps this page open; review and submission are separate.';
                }
            } catch { dirty = true; show('Progress could not be saved. Your entered values are retained; check the connection and retry.', true); }
            finally { busy = false; if (dirty) retain(); render(); }
        };
        button.addEventListener('click', save);
        forms.forEach(form => form.addEventListener('submit', event => {
            if (busy || locked) { event.preventDefault(); return; }
            const action = event.submitter?.value;
            if (form.dataset.purchaseForm === 'work') {
                const pending = [...form.querySelectorAll('[name^="selection["]')].some(input => input.value !== '');
                if (pending && ['review', 'search', 'show_all', 'reset'].includes(action)) { event.preventDefault(); show('Use Add to purchase or clear pending quantities before reviewing or changing search results.', true); retain(); return; }
                const remove = event.submitter?.dataset.purchaseRemove;
                if (remove) { let control = form.querySelector('[name="remove"]'); if (!control) { control = document.createElement('input'); control.type = 'hidden'; control.name = 'remove'; form.append(control); } control.value = remove; }
            }
            if (work && event.submitter && !event.defaultPrevented) {
                actionNonce = crypto.randomUUID();
                let nonce = form.querySelector('[name="action_nonce"]');
                if (!nonce) { nonce = document.createElement('input'); nonce.type = 'hidden'; nonce.name = 'action_nonce'; form.append(nonce); }
                nonce.value = actionNonce;
            }
            retain();
            if (['request', 'preparation'].includes(form.dataset.purchaseForm)) { event.preventDefault(); save(); }
        }));
        toolbar.querySelectorAll('[data-purchase-work-action]').forEach(link => link.addEventListener('click', event => {
            event.preventDefault();
            if (busy || locked) return;
            const form = forms.find(form => form.dataset.purchaseForm.startsWith('work'));
            if (!form) return;
            retain();
            const control = document.createElement('button'); control.type = 'submit'; control.name = 'action'; control.value = link.dataset.purchaseWorkAction; control.formNoValidate = true; control.hidden = true;
            if (form.dataset.purchaseForm === 'work-review') control.formAction = toolbar.dataset.saveUrl;
            form.append(control); form.requestSubmit(control); control.remove();
        }));
        window.appNavigationGuards?.push({ shouldWarn: origin => !busy && dirty && !origin?.hasAttribute('data-purchase-work-action'), message: 'Progress has not been saved. Save progress makes it durable; working values are retained in this browser or session.' });
        document.addEventListener('click', event => {
            if (busy && event.target.closest('a[href]')) { event.preventDefault(); show('Wait for Saving… to finish before leaving.'); }
        }, true);
        window.addEventListener('pagehide', () => retain());
        window.addEventListener('pageshow', async event => {
            busy = false;
            if (event.persisted && work) {
                let current;
                try { current = JSON.parse(sessionStorage.getItem(key)); } catch {}
                busy = true; render();
                try {
                    const response = await fetch(toolbar.dataset.saveUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    const state = response.ok ? await response.json() : null;
                    if (!state || !Number.isInteger(state.version)) throw new Error('Unavailable working page');
                    if (String(state.version) !== toolbar.dataset.saveVersion) {
                        if (!current?.forms || (current.actionNonce && current.actionNonce === state.action_ack) || String(current.version) === String(state.version)) { location.reload(); return; }
                        dirty = true; locked = true;
                        show('This working page is older than the current request. Your retained input has not been discarded. Review the current working page before resolving these changes.', true);
                        showRetained(current.forms);
                        return;
                    }
                } catch {
                    locked = true;
                    show('The current working request could not be checked. Your retained input is preserved; reload before saving or submitting.', true);
                    return;
                } finally { busy = false; render(); }
                if (current?.forms) {
                    if (String(current.version) !== toolbar.dataset.saveVersion) {
                        locked = true; show('Retained input belongs to another working version. Nothing has been discarded.', true); render(); return;
                    }
                    if (workReview && Object.values(current.forms).some(saved => saved.some(field => field.type !== 'hidden'))) { location.replace(toolbar.dataset.saveUrl + '?step=items'); return; }
                    dirty = true; restoring = true;
                    forms.forEach(form => restore(form, current.forms[form.id]));
                    restoring = false; revision = String(current.revision ?? revision); retain();
                }
            }
            if (event.persisted && forms.some(form => form.dataset.purchaseForm === 'invoice-review')) {
                let latest;
                try { latest = JSON.parse(sessionStorage.getItem(key)); } catch {}
                if (latest?.forms && Object.values(latest.forms).some(saved => saved.some(field => field.type !== 'hidden'))) {
                    dirty = true; locked = true;
                    show('Invoice details have unsaved working input. Return to details and review them again before confirming; your input is retained.', true);
                }
            }
            render();
        });
        if (dirty) retain();
        render();
    };
    if (document.readyState !== 'complete') document.addEventListener('DOMContentLoaded', start, { once: true }); else start();
})();
