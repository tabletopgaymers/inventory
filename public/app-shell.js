(() => {
    // Preview only a committed selection in the viewer's own profile; Save persists it.
    const density = document.querySelector('#profile-form select[data-density-preview]');
    density?.addEventListener('change', () => {
        if (['compact', 'standard', 'spacious'].includes(density.value)) {
            document.body.dataset.density = density.value;
        }
    });
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
        panel.dataset.noticeDismissible = 'false';
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

// Keep field-label qualifiers tied to explicit metadata and native field state.
(() => {
    const supportedTypes = new Set(['text', 'search', 'url', 'tel', 'email', 'password', 'date', 'month', 'week', 'time', 'datetime-local', 'number']);
    const ariaLabels = new WeakMap();
    const qualify = (label, field, text) => {
        label = label.querySelector('[data-field-label]') || label;
        const existing = label.querySelector(':scope > [data-field-qualifier]');
        if (!text) { existing?.remove(); return; }
        if (existing) { if (existing.textContent !== text) existing.textContent = text; return; }
        const suffix = document.createElement('span');
        suffix.dataset.fieldQualifier = '';
        suffix.textContent = text;
        let anchor = field;
        if (label.contains(field) && !['checkbox', 'radio'].includes(field.type)) {
            while (anchor.parentElement !== label) anchor = anchor.parentElement;
            label.insertBefore(suffix, anchor);
        } else label.append(suffix);
    };
    const update = () => {
        document.querySelectorAll('label').forEach(label => {
            const field = label.control;
            const readOnly = field && (field instanceof HTMLTextAreaElement || (field instanceof HTMLInputElement && supportedTypes.has(field.type)))
                && field.readOnly && !field.matches(':disabled');
            const optional = field?.hasAttribute('data-optional') && !field.required && !field.readOnly;
            qualify(label, field, readOnly ? ' (read only)' : (optional ? ' (optional)' : ''));
        });
        document.querySelectorAll('fieldset > legend').forEach(legend => {
            const group = legend.parentElement;
            qualify(legend, group, group.hasAttribute('data-optional') ? ' (optional)' : '');
        });
        document.querySelectorAll('[data-optional][aria-label]').forEach(field => {
            if (!ariaLabels.has(field)) ariaLabels.set(field, field.getAttribute('aria-label'));
            const label = ariaLabels.get(field) + (!field.required && !field.readOnly ? ' (optional)' : '');
            if (field.getAttribute('aria-label') !== label) field.setAttribute('aria-label', label);
        });
    };
    update();
    new MutationObserver(records => {
        const relevant = records.some(record => record.type === 'attributes'
            || [...record.addedNodes, ...record.removedNodes].some(node => node instanceof Element
                && (node.matches('label, input, textarea, select, fieldset, legend') || node.querySelector('label, input, textarea, select, fieldset, legend'))));
        if (relevant) update();
    }).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['readonly', 'disabled', 'required', 'data-optional', 'for', 'id'] });
})();

// Dismiss informational banners for this page without losing references used by
// live feedback. A later message may reuse and show the same banner again.
(() => {
    const selector = '.notice, .warning, .error, .app-notice-dismissible';
    const update = banner => {
        const existing = banner.querySelector(':scope > button.app-notice-dismiss');
        const eligible = banner.matches('.notice, .warning') && !banner.closest('.error')
            && banner.dataset.noticeDismissible !== 'false';
        const hasContent = [...banner.childNodes].some(node => node !== existing && node.textContent.trim());
        if (!eligible || !hasContent) {
            existing?.remove();
            if (banner.classList.contains('app-notice-dismissible')) banner.classList.remove('app-notice-dismissible');
            return;
        }
        if (!banner.classList.contains('app-notice-dismissible')) banner.classList.add('app-notice-dismissible');
        if (existing) return;
        const dismiss = document.createElement('button');
        dismiss.type = 'button';
        dismiss.className = 'app-notice-dismiss';
        dismiss.textContent = '✕';
        dismiss.setAttribute('aria-label', 'Dismiss notification');
        dismiss.title = 'Dismiss notification';
        dismiss.addEventListener('click', () => {
            const controls = [...document.querySelectorAll('a[href], button:not(:disabled), input:not(:disabled):not([type="hidden"]), select:not(:disabled), textarea:not(:disabled), summary, [tabindex]')]
                .filter(control => control.tabIndex >= 0 && control.getClientRects().length);
            const index = controls.indexOf(dismiss);
            const next = controls.slice(index + 1).find(control => !banner.contains(control))
                || controls.slice(0, index).reverse().find(control => !banner.contains(control))
                || document.getElementById('app-main');
            const restoreFocus = banner.contains(document.activeElement);
            banner.hidden = true;
            if (restoreFocus) next?.focus();
        });
        banner.append(dismiss);
    };
    const inspect = node => {
        if (!(node instanceof Element)) return;
        const banner = node.closest(selector);
        if (banner) update(banner);
        node.querySelectorAll(selector).forEach(update);
    };
    document.querySelectorAll(selector).forEach(update);
    new MutationObserver(records => {
        records.forEach(record => {
            if (record.type === 'attributes') inspect(record.target);
            else {
                const banner = record.target instanceof Element ? record.target.closest(selector) : null;
                if (banner) update(banner);
                record.addedNodes.forEach(inspect);
            }
        });
    }).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'hidden'] });
})();
