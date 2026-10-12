(() => {
    const form = document.getElementById('user-disable-form');
    const trigger = document.getElementById('user-disable-trigger');
    const dialog = document.getElementById('user-disable-dialog');
    const cancel = document.getElementById('user-disable-cancel');
    const confirm = document.getElementById('user-disable-confirm');
    if (!form || !trigger || !dialog || !cancel || !confirm) return;

    let submitting = false;
    trigger.addEventListener('click', () => dialog.showModal());
    cancel.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => trigger.focus());
    form.addEventListener('submit', event => {
        if (submitting) {
            event.preventDefault();
            return;
        }
        if (!dialog.open) {
            event.preventDefault();
            dialog.showModal();
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
