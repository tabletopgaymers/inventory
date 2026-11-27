import { mountSearchPersistence } from './search-persistence.js';
import { mountClearableInput } from './clearable-input.js';

(() => {
    const instances = new WeakMap();
    const mount = (root, options = {}) => {
        if (instances.has(root)) {
            const instance = instances.get(root);
            Object.assign(instance.options, options);
            return instance.api;
        }
        options = { ...options };
        const config = JSON.parse(root.querySelector('[data-is-config]')?.textContent || '{}');
        const text = root.querySelector('[data-is-text]');
        const filters = root.querySelector('[data-is-filters]');
        const feedback = root.querySelector('[data-is-feedback]');
        const pending = root.querySelector('[data-is-pending]');
        let busy = false;
        let result = null;
        let submittedInputs = config.submitted || null;
        let persistence = null;
        let clearInput = null;
        const emit = (name, detail, cancelable = false) => root.dispatchEvent(new CustomEvent('inventory-search:' + name, { bubbles: true, detail, cancelable }));
        const criteria = () => {
            const next = { search: text.value.trim(),
            collections: [...root.querySelectorAll('[data-is-collection]:checked')].map(field => Number(field.value)).sort((a,b) => a-b),
            columns: [...root.querySelectorAll('[data-is-column]:checked')].map(field => field.value).sort(),
            include_inactive: root.querySelector('[data-is-inactive]')?.checked || false };
            const fields = [...root.querySelectorAll('[data-is-extra][name]')];
            if (fields.length) next.extras = Object.fromEntries(fields.map(field => [field.name, field.type === 'checkbox' ? field.checked : field.value]));
            return next;
        };
        const changed = () => {
            clearInput?.update();
            const current = criteria();
            pending.hidden = !submittedInputs || JSON.stringify(current) === JSON.stringify(submittedInputs);
            const count = current.collections.length + current.columns.length + Number(current.include_inactive)
                + root.querySelectorAll('[data-is-extra][type="checkbox"]:checked').length;
            root.querySelector('[data-is-filter-count]')?.replaceChildren(document.createTextNode(count ? ` (${count} selected)` : ''));
            filters?.classList.toggle('active-filter', count > 0);
            emit('pending', { criteria: current, changed: !pending.hidden });
        };
        const message = (value, error = false) => {
            feedback.textContent = value;
            feedback.hidden = !value;
            feedback.className = error ? 'error' : 'app-field-help';
            feedback.setAttribute('role', error ? 'alert' : 'status');
        };
        const apply = saved => {
            if (!saved || typeof saved.search !== 'string' || saved.search.length > 255
                || !Array.isArray(saved.collections) || !saved.collections.every(Number.isSafeInteger)
                || !Array.isArray(saved.columns) || !saved.columns.every(key => typeof key === 'string')
                || typeof saved.include_inactive !== 'boolean') return;
            text.value = saved.search;
            root.querySelectorAll('[data-is-collection]').forEach(field => { field.checked = saved.collections.includes(Number(field.value)); });
            root.querySelectorAll('[data-is-column]').forEach(field => { field.checked = saved.columns.includes(field.value); });
            const inactive = root.querySelector('[data-is-inactive]');
            if (inactive) inactive.checked = saved.include_inactive;
            root.querySelectorAll('[data-is-extra][name]').forEach(field => {
                const value = saved.extras?.[field.name];
                if (field.type === 'checkbox') { if (typeof value === 'boolean') field.checked = value; }
                else if (typeof value === 'string' && value.length <= 255
                    && (field.tagName !== 'SELECT' || [...field.options].some(option => option.value === value && !option.disabled))) field.value = value;
            });
        };
        const submitForm = async (action, destination = null) => {
            if (busy) return;
            const form = root.closest('form');
            if (!form) throw new Error('Form search requires a surrounding form.');
            if (!form.reportValidity()) return;
            const endpoint = new URL(destination ? persistence.endpoint : root.dataset.endpoint, location.href);
            if (endpoint.origin !== location.origin) throw new Error('Search must use this site.');
            if (action === 'all') text.value = '';
            if (action === 'reset') apply({search: '', collections: [], columns: [], include_inactive: false});
            if (filters) filters.open = false;
            busy = true; root.setAttribute('aria-busy', 'true');
            root.querySelector(`[data-is-action="${action}"]`)?.setAttribute('aria-busy', 'true');
            message(action === 'save' ? 'Saving…' : 'Searching…'); emit('busy', {busy: true});
            await persistence?.beforeSubmit();
            if (form.elements.scroll) form.elements.scroll.value = String(Math.round(window.scrollY));
            if (form.elements.destination) form.elements.destination.value = destination || '';
            form.action = endpoint.href;
            let field = form.querySelector('[data-is-form-action]');
            if (!field) { field = document.createElement('input'); field.type = 'hidden'; field.name = 'action'; field.dataset.isFormAction = ''; form.append(field); }
            field.value = action === 'all' ? 'show_all' : action;
            form.submit();
        };
        const search = async (action = 'search') => {
            if (busy) return;
            const next = criteria();
            if (action === 'all') next.search = '';
            if (!emit('before-search', { criteria: next, action }, true)) return;
            if (config.formMode) return submitForm(action);
            if (action === 'all') text.value = '';
            if (filters) filters.open = false;
            const controls = [...root.querySelectorAll('input, button')].map(control => [control, control.disabled]);
            const button = root.querySelector(`[data-is-action="${action}"]`);
            busy = true;
            controls.forEach(([control]) => { control.disabled = true; });
            button?.setAttribute('aria-busy', 'true');
            root.setAttribute('aria-busy', 'true');
            emit('busy', { busy: true });
            message('Searching…');
            const reportFailure = reason => {
                message(reason, true);
                emit('error', { message: feedback.textContent });
                changed();
            };
            const originError = 'Search must use an endpoint on this site.';
            try {
                const url = new URL(options.endpoint || root.dataset.endpoint, location.href);
                if (url.origin !== location.origin) {
                    reportFailure(originError);
                    return;
                }
                url.searchParams.set('search', next.search);
                url.searchParams.set('include_inactive', next.include_inactive ? '1' : '0');
                for (const key of ['collections[]', 'columns[]']) url.searchParams.delete(key);
                next.collections.forEach(id => url.searchParams.append('collections[]', id));
                next.columns.forEach(key => url.searchParams.append('columns[]', key));
                options.prepareUrl?.(url, next);
                if (url.origin !== location.origin) {
                    reportFailure(originError);
                    return;
                }
                let data;
                if (options.request) data = await options.request(url, next, action);
                else {
                    const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    data = await response.json();
                    if (!response.ok) {
                        reportFailure(Object.values(data.errors || {}).flat().join(' ') || data.message || 'Search could not be completed.');
                        return;
                    }
                }
                if (!Array.isArray(data.rows) || !data.columns || data.schema_version !== 1) {
                    reportFailure('The search response could not be read.');
                    return;
                }
                options.render?.(data, root);
                result = data;
                submittedInputs = next;
                emit('results', data);
                message(`${data.count} ${data.count === 1 ? 'item' : 'items'} found.`);
                changed();
                persistence?.schedule();
                return data;
            } catch (error) {
                reportFailure(error instanceof SyntaxError || error instanceof TypeError ? 'Search could not be completed. Check your connection or sign in again.' : error.message);
            } finally {
                busy = false;
                controls.forEach(([control, disabled]) => { control.disabled = disabled; });
                button?.setAttribute('aria-busy', 'false');
                root.setAttribute('aria-busy', 'false');
                emit('busy', { busy: false });
            }
        };
        const clear = (reset = false) => {
            if (busy) return;
            if (!emit('before-reset', { reset }, true)) return;
            root.querySelectorAll('input[type="checkbox"]').forEach(field => { field.checked = false; });
            if (config.formMode && filters) filters.open = false;
            if (reset) {
                text.value = '';
                result = null;
                submittedInputs = null;
                if (filters) filters.open = false;
                message('');
                emit('reset', null);
            }
            changed();
            persistence?.schedule();
        };
        text.addEventListener('keydown', event => {
            if (event.key === 'Enter' && !event.isComposing) { event.preventDefault(); event.stopPropagation(); search(); }
        });
        const edited = () => { changed(); persistence?.schedule(); };
        root.addEventListener('input', edited);
        root.addEventListener('change', edited);
        persistence = mountSearchPersistence(root, config.persistence, {criteria, apply, changed});
        clearInput = mountClearableInput(text, root.querySelector('[data-is-clear-text]'));
        if (config.formMode) {
            root.closest('form').addEventListener('submit', event => {
                event.preventDefault();
                if (event.submitter && !root.contains(event.submitter)) submitForm(event.submitter.value || 'search');
                else search();
            });
            window.addEventListener('pageshow', event => {
                busy = false; persistence?.resume(); root.setAttribute('aria-busy', 'false');
                root.querySelectorAll('[data-is-action]').forEach(button => button.setAttribute('aria-busy', 'false'));
                if (event.persisted && submittedInputs) window.location.reload();
            });
        }
        const api = { search: () => search(), showAll: () => search('all'), reset: () => config.formMode ? submitForm('reset') : clear(true), clearFilters: () => clear(),
            getState: () => ({ pending: criteria(), submitted: result?.criteria || submittedInputs, result, busy }),
            persistence,
            navigateWithPreferences: destination => { if (config.formMode && persistence?.endpoint) return submitForm('search', destination); } };
        root.querySelectorAll('[data-is-action]').forEach(button => button.addEventListener('click', event => {
            event.stopPropagation();
            if (config.formMode) event.preventDefault();
            const action = button.dataset.isAction;
            if (action === 'reset') api.reset();
            else if (action === 'clear') api.clearFilters();
            else if (action === 'all') api.showAll();
            else search(action);
        }));
        instances.set(root, { api, options });
        changed();
        return api;
    };
    /**
     * Supports native form navigation and same-origin AJAX searches. AJAX callers can prepare the URL and render validated results. Each root retains its own current criteria, submitted criteria, and result API.
     *
     * Scope: Domain-specific (Inventory search)
     * Task: Mount or retrieve a shared search instance
     * @example resources/js/relocation.js
     */
    window.appInventorySearch = { mount, get: root => instances.get(root)?.api };
    document.querySelectorAll('[data-inventory-search]').forEach(root => mount(root));
})();
