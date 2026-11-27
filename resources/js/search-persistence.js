// Each enabled search instance owns a separate per-user storage key. Optional
// server persistence retains existing account-wide preferences across devices.
/**
 * Uses a per-user and per-instance browser key, optionally with a same-origin account preference endpoint. Server-rendered state controls initial values when an endpoint is configured. Use beforeSubmit to coordinate pending writes and resume when returning from browser history.
 *
 * Scope: Domain-specific (Inventory search)
 * Task: Persist criteria and scroll for an individual search
 * @example resources/js/inventory-search-controls.js
 */
export function mountSearchPersistence(root, config, {criteria, apply, changed}) {
    if (!config?.key || !/^[1-9]\d*$/.test(String(config.userId))) return null;
    const storageKey = `inventory-search:v1:user:${config.userId}:instance:${config.key}`;
    const form = root.closest('form');
    const notice = root.querySelector('[data-is-persistence-feedback]');
    let timer, queue = Promise.resolve(), navigating = false;
    const report = text => { notice.textContent = text; notice.hidden = !text; };
    let endpoint = null;
    if (config.endpoint !== undefined && config.endpoint !== null) {
        if (typeof config.endpoint === 'string' && config.endpoint.trim()) {
            try {
                const url = new URL(config.endpoint, location.href);
                if (url.protocol === location.protocol && url.origin === location.origin && !url.username && !url.password) endpoint = url;
            } catch { /* Invalid preference configuration does not prevent searching. */ }
        }
        if (!endpoint) {
            report('Search settings could not be remembered. Searching is still available.');
            return null;
        }
    }
    const read = () => {
        try { return JSON.parse(localStorage.getItem(storageKey)); } catch { return null; }
    };
    const saved = read();
    // Server-rendered defaults/session context take precedence when configured.
    if (!config.endpoint && config.restore !== false && saved?.version === 1 && saved.criteria) apply(saved.criteria);
    const remember = () => {
        try {
            localStorage.setItem(storageKey, JSON.stringify({version: 1, criteria: criteria(), scroll: Math.round(window.scrollY)}));
            return true;
        } catch {
            if (!config.endpoint) report('Search settings could not be remembered in this browser. Searching is still available.');
            return false;
        }
    };
    const payload = () => {
        const next = criteria(), data = new FormData();
        data.set('_token', form?.querySelector('[name="_token"]')?.value || '');
        data.set('search', next.search);
        next.collections.forEach(id => data.append('collections[]', String(id)));
        next.columns.forEach(key => data.append('columns[]', key));
        data.set('include_inactive', next.include_inactive ? '1' : '0');
        if (config.context) data.set('context', config.context);
        data.set('scroll', String(Math.round(window.scrollY)));
        data.set('destination', '');
        return data;
    };
    const save = () => {
        remember();
        if (!endpoint) return Promise.resolve();
        const data = payload();
        queue = queue.catch(() => {}).then(async () => {
            const failure = 'Search settings could not be remembered. Select Search to retry saving your settings.';
            try {
                const response = await fetch(endpoint, {method: 'POST', body: data, credentials: 'same-origin', headers: {Accept: 'application/json'}, signal: AbortSignal.timeout(10000)});
                report(response.ok ? '' : failure);
            } catch { report(failure); }
        });
        return queue;
    };
    const schedule = () => {
        clearTimeout(timer);
        if (!navigating) timer = setTimeout(save, 400);
    };
    window.addEventListener('pagehide', () => {
        if (navigating) return;
        clearTimeout(timer); remember();
        if (endpoint) navigator.sendBeacon(endpoint.href, payload());
    });
    if (config.scroll !== undefined || (!endpoint && saved?.scroll)) {
        requestAnimationFrame(() => window.scrollTo(0, Math.max(0, Number(config.scroll ?? saved.scroll) || 0)));
    }
    changed();
    return {
        schedule,
        flush: () => { clearTimeout(timer); return save(); },
        async beforeSubmit({flush = false} = {}) {
            clearTimeout(timer);
            navigating = true;
            if (flush) {
                await save();
            } else {
                remember();
                await queue.catch(() => {});
            }
        },
        resume() { navigating = false; },
        endpoint: endpoint?.href,
    };
}
