(() => {
    const instances = new WeakMap();
    const mount = (root, options = {}) => {
        if (instances.has(root)) {
            const instance = instances.get(root);
            Object.assign(instance.options, options);
            return instance.api;
        }
        options = { ...options };
        const text = root.querySelector('[data-is-text]');
        const filters = root.querySelector('[data-is-filters]');
        const feedback = root.querySelector('[data-is-feedback]');
        const pending = root.querySelector('[data-is-pending]');
        let busy = false;
        let result = null;
        let submittedInputs = null;
        const emit = (name, detail, cancelable = false) => root.dispatchEvent(new CustomEvent('inventory-search:' + name, { bubbles: true, detail, cancelable }));
        const criteria = () => ({ search: text.value.trim(),
            collections: [...root.querySelectorAll('[data-is-collection]:checked')].map(field => Number(field.value)).sort((a,b) => a-b),
            columns: [...root.querySelectorAll('[data-is-column]:checked')].map(field => field.value).sort(),
            include_inactive: root.querySelector('[data-is-inactive]')?.checked || false });
        const changed = () => {
            const current = criteria();
            pending.hidden = !result || JSON.stringify(current) === JSON.stringify(submittedInputs);
            const count = current.collections.length + current.columns.length + Number(current.include_inactive);
            root.querySelector('[data-is-filter-count]')?.replaceChildren(document.createTextNode(count ? ` — ${count} selected` : ''));
            filters?.classList.toggle('active-filter', count > 0);
            emit('pending', { criteria: current, changed: !pending.hidden });
        };
        const message = (value, error = false) => {
            feedback.textContent = value;
            feedback.hidden = !value;
            feedback.className = error ? 'error' : 'app-field-help';
            feedback.setAttribute('role', error ? 'alert' : 'status');
        };
        const search = async (action = 'search') => {
            if (busy) return;
            const next = criteria();
            if (action === 'all') next.search = '';
            if (!emit('before-search', { criteria: next, action }, true)) return;
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
            try {
                const url = new URL(options.endpoint || root.dataset.endpoint, location.href);
                if (url.origin !== location.origin) throw new Error('Search must use an endpoint on this site.');
                url.searchParams.set('search', next.search);
                url.searchParams.set('include_inactive', next.include_inactive ? '1' : '0');
                for (const key of ['collections[]', 'columns[]']) url.searchParams.delete(key);
                next.collections.forEach(id => url.searchParams.append('collections[]', id));
                next.columns.forEach(key => url.searchParams.append('columns[]', key));
                options.prepareUrl?.(url, next);
                if (url.origin !== location.origin) throw new Error('Search must use an endpoint on this site.');
                let data;
                if (options.request) data = await options.request(url, next, action);
                else {
                    const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    data = await response.json();
                    if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'Search could not be completed.');
                }
                if (!Array.isArray(data.rows) || !data.columns || data.schema_version !== 1) throw new Error('The search response could not be read.');
                options.render?.(data, root);
                result = data;
                submittedInputs = next;
                emit('results', data);
                message(`${data.count} ${data.count === 1 ? 'item' : 'items'} found.`);
                changed();
                return data;
            } catch (error) {
                message(error instanceof SyntaxError || error instanceof TypeError ? 'Search could not be completed. Check your connection or sign in again.' : error.message, true);
                emit('error', { message: feedback.textContent });
                changed();
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
            if (reset) {
                text.value = '';
                result = null;
                submittedInputs = null;
                if (filters) filters.open = false;
                message('');
                emit('reset', null);
            }
            changed();
        };
        root.querySelectorAll('[data-is-action]').forEach(button => button.addEventListener('click', () => {
            const action = button.dataset.isAction;
            if (action === 'reset' || action === 'clear') clear(action === 'reset');
            else search(action);
        }));
        text.addEventListener('keydown', event => {
            if (event.key === 'Enter' && !event.isComposing) { event.preventDefault(); event.stopPropagation(); search(); }
        });
        root.addEventListener('input', changed);
        root.addEventListener('change', changed);
        const api = { search: () => search(), showAll: () => search('all'), reset: () => clear(true), clearFilters: () => clear(),
            getState: () => ({ pending: criteria(), submitted: result?.criteria || null, result, busy }) };
        instances.set(root, { api, options });
        changed();
        return api;
    };
    window.appInventorySearch = { mount, get: root => instances.get(root)?.api };
    document.querySelectorAll('[data-inventory-search]').forEach(root => mount(root));
})();
