(() => {
    // Keep server error keys and connect summaries to their actual visible controls.
    const fields = [...document.querySelectorAll('form input:not([type="hidden"]), form select, form textarea')];
    fields.forEach((input, index) => {
        const field = input.closest('.app-field');
        if (!input.id) input.id = field?.dataset.fieldId || `app-input-${index}`;
        if (!field) return;
        const described = new Set((input.getAttribute('aria-describedby') || '').split(' ').filter(Boolean));
        field.querySelectorAll('.app-field-help, .app-field-error').forEach(message => {
            described.add(message.id);
            if (message.classList.contains('app-field-error')) input.setAttribute('aria-invalid', 'true');
        });
        if (described.size) input.setAttribute('aria-describedby', [...described].join(' '));
    });
    document.querySelectorAll('[data-error-key]').forEach((summary, index) => {
        const key = summary.dataset.errorKey;
        const controls = fields.filter(input => input.name.replace(/\[([^\]]+)\]/g, '.$1') === key);
        if (!controls.length) return;
        const message = summary.textContent;
        const target = controls[0];
        const link = document.createElement('a');
        link.href = `#${target.id}`;
        const labelText = target.labels?.[0]?.cloneNode(true);
        labelText?.querySelectorAll('input, select, textarea, .app-field-help, .app-field-error').forEach(node => node.remove());
        const label = target.getAttribute('aria-label') || labelText?.textContent.trim() || key;
        link.textContent = `${label}: ${message}`;
        link.addEventListener('click', () => target.focus());
        summary.replaceChildren(link);
        controls.forEach((input, controlIndex) => {
            const error = document.createElement('p');
            error.id = `app-error-${index}-${controlIndex}`;
            error.className = 'app-field-error'; error.textContent = message;
            input.insertAdjacentElement('afterend', error);
            input.setAttribute('aria-invalid', 'true');
            input.setAttribute('aria-describedby', [input.getAttribute('aria-describedby'), error.id].filter(Boolean).join(' '));
        });
    });
    const drawer = document.getElementById('app-nav-dialog');
    const trigger = document.getElementById('app-nav-open');
    const close = document.getElementById('app-nav-close');
    const account = document.getElementById('app-account');
    const main = document.getElementById('app-main');
    const narrow = window.matchMedia('(width < 800px)');
    if (drawer && trigger && typeof drawer.showModal === 'function') {
        document.body.classList.add('app-navigation-ready');
        const sync = () => {
            trigger.hidden = !narrow.matches;
            if (!narrow.matches && drawer.open) drawer.close();
        };
        sync();
        narrow.addEventListener('change', sync);
        trigger.addEventListener('click', () => {
            if (account) account.open = false;
            drawer.showModal();
            trigger.setAttribute('aria-expanded', 'true');
        });
        close.addEventListener('click', () => drawer.close());
        drawer.addEventListener('close', () => {
            trigger.setAttribute('aria-expanded', 'false');
            (narrow.matches ? trigger : main).focus();
        });
        drawer.addEventListener('keydown', event => {
            if (event.key !== 'Tab') return;
            const controls = [...drawer.querySelectorAll('button:not([disabled]), a[href], summary')].filter(control => control.getClientRects().length);
            const first = controls[0], last = controls[controls.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault(); last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault(); first.focus();
            }
        });
        drawer.querySelectorAll('a[href]').forEach(link => link.addEventListener('click', () => drawer.close()));
    }
    if (account) {
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && account.open) {
                account.open = false;
                account.querySelector('summary').focus();
            }
        });
        document.addEventListener('click', event => {
            if (account.open && !account.contains(event.target)) account.open = false;
        });
    }
})();

// In-page decisions keep deliberate confirmation without browser-native dialogs.
(() => {
    const guards = [];
    const approved = new WeakSet();
    const inFlight = new WeakSet();
    let panel;
    window.appNavigationGuards = guards;
    window.appRequestDecision = (message, proceed, origin, actionLabel = 'Confirm action') => {
        const drawer = document.getElementById('app-nav-dialog');
        if (drawer?.open) {
            drawer.addEventListener('close', () => window.appRequestDecision(message, proceed, document.getElementById('app-nav-open'), actionLabel), { once: true });
            drawer.close();
            return;
        }
        if (panel) { panel.focus(); return; }
        const restore = origin || document.activeElement;
        panel = document.createElement('section');
        panel.className = 'warning'; panel.tabIndex = -1;
        panel.setAttribute('role', 'region'); panel.setAttribute('aria-label', 'Confirm action');
        const heading = document.createElement('h2'); heading.textContent = 'Confirm action';
        const text = document.createElement('p'); text.textContent = message;
        const actions = document.createElement('div'); actions.className = 'actions';
        const stay = document.createElement('button'); stay.type = 'button'; stay.textContent = 'Keep editing / cancel action';
        const accept = document.createElement('button'); accept.type = 'button'; accept.textContent = actionLabel; accept.className = 'discard';
        const close = () => { panel.remove(); panel = undefined; };
        const cancel = () => { close(); if (restore?.isConnected) restore.focus(); };
        stay.addEventListener('click', cancel);
        accept.addEventListener('click', () => { close(); proceed(); });
        panel.addEventListener('keydown', event => { if (event.key === 'Escape') { event.preventDefault(); cancel(); } });
        actions.append(stay, accept); panel.append(heading, text, actions);
        (origin?.closest('form') || document.querySelector('.app-content') || document.querySelector('main')).append(panel);
        panel.focus();
    };
    window.appNavigate = (href, origin) => {
        const guard = guards.find(candidate => candidate.shouldWarn(origin));
        const leave = () => { window.location.assign(href); };
        if (guard) window.appRequestDecision(guard.message, leave, origin, 'Leave without saving');
        else leave();
    };
    document.addEventListener('submit', event => {
        const button = event.submitter;
        if (!button?.hasAttribute('data-confirm')) return;
        if (inFlight.has(button.form)) { event.preventDefault(); return; }
        if (approved.has(button)) {
            approved.delete(button); inFlight.add(button.form);
            queueMicrotask(() => { if (event.defaultPrevented) inFlight.delete(button.form); else button.disabled = true; });
            return;
        }
        event.preventDefault();
        const form = button.form;
        window.appRequestDecision(button.dataset.confirm, () => {
            approved.add(button); form.requestSubmit(button); approved.delete(button);
        }, button, button.textContent.trim());
    }, true);
    document.addEventListener('click', event => {
        const link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.target === '_blank' || link.hasAttribute('download')) return;
        const href = link.getAttribute('href');
        if (!href || href.startsWith('#')) return;
        const guard = guards.find(candidate => candidate.shouldWarn(link));
        if (!guard) return;
        event.preventDefault();
        window.appRequestDecision(guard.message, () => { window.location.assign(link.href); }, link, 'Leave without saving');
    }, true);
})();
