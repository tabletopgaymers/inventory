(() => {
    const form = document.getElementById('inventory-search');
    const table = document.getElementById('inventory-results');
    const state = JSON.parse(document.getElementById('browse-state').textContent);
    const notice = document.getElementById('pending-notice');
    const error = document.getElementById('preference-error');
    const criteria = () => {
        const data = new FormData(form);
        return { search: String(data.get('search') || '').trim(), collections: data.getAll('collections[]').map(Number).sort((a,b)=>a-b), columns: data.getAll('columns[]').sort(), include_inactive: data.get('include_inactive') === '1' };
    };
    const update = () => {
        const pending = criteria();
        const count = pending.collections.length + pending.columns.length;
        const active = count > 0 || pending.include_inactive;
        document.getElementById('filter-summary').textContent = `— ${count} selected${pending.include_inactive ? ' · includes inactive zero-stock' : ''}`;
        document.getElementById('inventory-filters').classList.toggle('active-filter', active);
        notice.hidden = !state.submitted || JSON.stringify(pending) === JSON.stringify(state.submitted);
    };
    let timer;
    let navigating = false;
    let pendingSave = Promise.resolve();
    let submitting = false;
    const searchButton = form.querySelector('[value="search"]');
    const loading = active => {
        submitting = active;
        searchButton.setAttribute('aria-busy', String(active));
        form.querySelector('[data-search-status]').textContent = active ? 'Searching inventory…' : '';
    };
    const save = () => {
        const data = new FormData(form);
        data.set('scroll', String(Math.round(table.scrollTop)));
        pendingSave = pendingSave.catch(()=>{}).then(async () => {
            try {
                const response = await fetch('/inventory/preferences', {method:'POST', body:data, headers:{'Accept':'application/json'}});
                if (!response.ok) throw new Error();
                error.hidden = true;
            } catch (_) {
                error.textContent = 'Search settings could not be remembered. Select Search to retry saving your settings.';
                error.hidden = false;
            }
        });
    };
    form.addEventListener('input', () => { update(); clearTimeout(timer); timer = setTimeout(save, 400); });
    form.addEventListener('change', () => { update(); clearTimeout(timer); timer = setTimeout(save, 400); });
    document.getElementById('clear-filters').addEventListener('click', () => {
        form.querySelectorAll('input[type="checkbox"]').forEach(input => { input.checked = false; });
        document.getElementById('inventory-filters').open = false;
        update(); clearTimeout(timer); save();
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (submitting) return;
        loading(true); clearTimeout(timer); navigating = true;
        const action = document.createElement('input'); action.type = 'hidden'; action.name = 'action'; action.value = event.submitter?.value || 'search';
        form.append(action); await pendingSave.catch(()=>{}); form.submit();
    });
    document.querySelectorAll('[data-browse-link]').forEach(link => link.addEventListener('click', async event => {
        event.preventDefault(); clearTimeout(timer); navigating = true; await pendingSave.catch(()=>{});
        form.elements.destination.value = new URL(link.href).pathname;
        form.elements.scroll.value = String(Math.round(table.scrollTop));
        form.action = '/inventory/preferences'; form.submit();
    }));
    window.addEventListener('pagehide', () => {
        if (navigating) return;
        clearTimeout(timer);
        const data = new FormData(form); data.set('scroll', String(Math.round(table.scrollTop))); data.set('destination','');
        navigator.sendBeacon('/inventory/preferences', data);
    });
    window.addEventListener('pageshow', event => { loading(false); if (event.persisted && state.submitted) window.location.reload(); });
    update(); requestAnimationFrame(() => { table.scrollTop = state.scroll || 0; });
})();
