// The shell renders one Blade dialog. Callers supply wording and business actions.
export function mountConfirmationDialog() {
    const dialog = document.querySelector('[data-confirmation-dialog]');
    if (!dialog) throw new Error('The application confirmation dialog is missing.');
    const title = dialog.querySelector('[data-confirmation-title]');
    const message = dialog.querySelector('[data-confirmation-message]');
    const cancelButton = dialog.querySelector('[data-confirmation-cancel]');
    const continueButton = dialog.querySelector('[data-confirmation-continue]');
    let active = null;
    const finish = confirmed => {
        if (!active) return;
        const options = active;
        active = null; // Clear before callbacks to prevent duplicate execution/re-entry.
        if (dialog.open) dialog.close();
        if (options.origin?.isConnected) options.origin.focus();
        if (confirmed) options.onConfirm?.();
        else options.onCancel?.();
    };
    cancelButton.addEventListener('click', () => finish(false));
    continueButton.addEventListener('click', () => finish(true));
    dialog.addEventListener('cancel', event => { event.preventDefault(); finish(false); });
    dialog.addEventListener('close', () => { if (!dialog.open) finish(false); });
    window.addEventListener('pageshow', () => { if (active) finish(false); });
    return (options = {}) => {
        if (active) { cancelButton.focus(); return false; }
        const drawer = document.getElementById('app-nav-dialog');
        if (drawer?.open) {
            drawer.addEventListener('close', () => request({...options, origin:document.getElementById('app-nav-open')}), {once:true});
            drawer.close();
            return false;
        }
        return request(options);
    };
    function request(options) {
        if (active) { cancelButton.focus(); return false; }
        active = {...options, origin:options.origin || document.activeElement};
        title.textContent = options.title || 'Confirm action';
        message.textContent = options.message || '';
        cancelButton.textContent = options.cancelLabel || 'Cancel';
        continueButton.textContent = options.confirmLabel || 'Continue';
        continueButton.classList.toggle('app-action-destructive', options.variant === 'danger');
        continueButton.classList.toggle('app-action-primary', options.variant !== 'danger');
        dialog.showModal();
        cancelButton.focus();
        return true;
    }
}
