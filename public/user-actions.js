(() => {
    const form = document.getElementById('user-disable-form');
    const trigger = document.getElementById('user-disable-trigger');
    const dialog = document.getElementById('user-disable-dialog');
    const cancel = document.getElementById('user-disable-cancel');
    const confirm = document.getElementById('user-disable-confirm');
    if (!form || !trigger || !dialog || !cancel || !confirm || trigger.disabled) return;

    let submitting = false;
    trigger.addEventListener('click', () => dialog.showModal());
    cancel.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => trigger.focus());
    form.addEventListener('submit', event => {
        if (submitting) {
            event.preventDefault();
            return;
        }
        if (!dialog.open || event.submitter !== confirm) {
            event.preventDefault();
            if (!dialog.open) dialog.showModal();
            return;
        }
        submitting = true;
        confirm.disabled = true;
        cancel.disabled = true;
        trigger.disabled = true;
    });
    window.addEventListener('pageshow', () => {
        submitting = false;
        confirm.disabled = false;
        cancel.disabled = false;
        trigger.disabled = false;
        if (dialog.open) dialog.close();
    });
})();

(() => {
    const dialog = document.getElementById('user-role-dialog');
    const cancel = document.getElementById('user-role-cancel');
    const confirm = document.getElementById('user-role-confirm');
    const description = document.getElementById('user-role-description');
    if (!dialog || !cancel || !confirm || !description) return;
    const entries = [...document.querySelectorAll('[data-user-role-form]')].map(form => ({
        form, trigger: form.querySelector('[data-user-role-trigger]'),
    })).filter(entry => entry.trigger && !entry.trigger.disabled);
    let origin;
    let selected;
    let submitting = false;
    const open = (form, trigger) => {
        if (submitting || trigger.disabled) return;
        origin = trigger;
        selected = form;
        description.textContent = form.dataset.roleConfirmation;
        confirm.textContent = form.dataset.roleLabel;
        confirm.setAttribute('form', form.id);
        confirm.className = form.dataset.roleRemoval === '1' ? 'app-user-disable-confirm' : 'app-user-role-grant-confirm';
        if (!dialog.open) dialog.showModal();
        cancel.focus();
    };
    entries.forEach(({ form, trigger }) => {
        trigger.addEventListener('click', () => open(form, trigger));
        form.addEventListener('submit', event => {
            if (submitting) {
                event.preventDefault();
                return;
            }
            if (!dialog.open || selected !== form || event.submitter !== confirm) {
                event.preventDefault();
                open(form, trigger);
                return;
            }
            submitting = true;
            confirm.disabled = true;
            cancel.disabled = true;
            entries.forEach(entry => { entry.trigger.disabled = true; });
        });
    });
    cancel.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => origin?.focus());
    window.addEventListener('pageshow', () => {
        submitting = false;
        selected = undefined;
        confirm.disabled = false;
        cancel.disabled = false;
        entries.forEach(entry => { entry.trigger.disabled = false; });
        if (dialog.open) dialog.close();
    });
})();
