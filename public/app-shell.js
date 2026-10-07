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
        const label = target.getAttribute('aria-label') || target.labels?.[0]?.textContent.trim() || key;
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
    const narrow = window.matchMedia('(max-width: 63.999rem)');
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
