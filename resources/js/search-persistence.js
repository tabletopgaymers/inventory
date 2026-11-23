// Each enabled search instance owns a separate per-user storage key. Optional
// server persistence retains existing account-wide preferences across devices.
export function mountSearchPersistence(root, config, {criteria, apply, changed}) {
    if (!config?.key || !/^[1-9]\d*$/.test(String(config.userId))) return null;
    const storageKey = `inventory-search:v1:user:${config.userId}:instance:${config.key}`;
    const form = root.closest('form');
    const notice = root.querySelector('[data-is-persistence-feedback]');
    let timer, queue = Promise.resolve(), navigating = false;
    const report = text => { notice.textContent = text; notice.hidden = !text; };
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
    let endpoint = null;
    if (config.endpoint) {
        endpoint = new URL(config.endpoint, location.href);
        if (endpoint.origin !== location.origin) throw new Error('Search persistence must use this site.');
    }
    const save = () => {
        remember();
        if (!endpoint) return Promise.resolve();
        const data = payload();
        queue = queue.catch(() => {}).then(async () => {
            try {
                const response = await fetch(endpoint, {method: 'POST', body: data, credentials: 'same-origin', headers: {Accept: 'application/json'}, signal: AbortSignal.timeout(10000)});
                if (!response.ok) throw new Error();
                report('');
            } catch { report('Search settings could not be remembered. Select Search to retry saving your settings.'); }
        });
        return queue;
    };
    const schedule = () => { clearTimeout(timer); timer = setTimeout(save, 400); };
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
        async beforeSubmit() { clearTimeout(timer); remember(); navigating = true; await queue.catch(() => {}); },
        resume() { navigating = false; },
        endpoint: endpoint?.href,
    };
}
