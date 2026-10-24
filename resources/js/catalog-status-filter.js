import './app.js';

(() => {
    const trigger = document.querySelector('.catalog-status-trigger');
    const menu = document.getElementById('catalog-status-filter');
    if (!trigger || !menu) return;
    const fields = [...menu.querySelectorAll('input[type="checkbox"]')];
    const allowedStatuses = fields.map(field => field.value);
    const storageKey = `catalog-status-filter:${menu.dataset.filterUser}:${location.pathname}`;
    const saveSelection = () => {
        try {
            localStorage.setItem(storageKey, JSON.stringify(fields.filter(field => field.checked).map(field => field.value)));
        } catch (_) { /* Filtering still works when browser storage is unavailable. */ }
    };
    const url = new URL(location.href);
    if (url.searchParams.has('status_filter')) {
        // An explicit URL selection takes precedence over the remembered selection.
        saveSelection();
    } else {
        try {
            const saved = JSON.parse(localStorage.getItem(storageKey));
            if (Array.isArray(saved) && saved.every(status => allowedStatuses.includes(status)) && saved.length < allowedStatuses.length) {
                url.searchParams.delete('statuses[]');
                url.searchParams.set('status_filter', '1');
                saved.forEach(status => url.searchParams.append('statuses[]', status));
                location.replace(url.href);
                return;
            }
        } catch (_) { /* Ignore unavailable storage or an invalid saved value. */ }
    }
    const close = (restoreFocus = false) => {
        menu.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        if (restoreFocus) trigger.focus();
    };
    trigger.addEventListener('click', () => {
        if (!menu.hidden) return close();
        menu.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        menu.querySelector('input[type="checkbox"]').focus();
        const anchor = trigger.getBoundingClientRect();
        const width = document.documentElement.clientWidth;
        const height = document.documentElement.clientHeight;
        menu.style.left = `${Math.max(8, Math.min(anchor.right - menu.offsetWidth, width - menu.offsetWidth - 8))}px`;
        menu.style.top = `${Math.max(8, Math.min(anchor.bottom + 6, height - menu.offsetHeight - 8))}px`;
    });
    document.addEventListener('click', event => {
        if (!menu.hidden && !menu.contains(event.target) && !trigger.contains(event.target)) close();
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !menu.hidden) {
            event.preventDefault();
            close(true);
        }
    });
    menu.addEventListener('focusout', event => {
        if (event.relatedTarget && !menu.contains(event.relatedTarget) && !trigger.contains(event.relatedTarget)) close();
    });
    menu.addEventListener('submit', () => {
        saveSelection();
        close();
    });
    window.addEventListener('resize', () => close());
    document.addEventListener('scroll', event => {
        if (!menu.contains(event.target)) close();
    }, true);
})();
