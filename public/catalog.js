document.querySelectorAll('[data-unsaved]').forEach(form => {
    let changed = false;
    const notice = document.createElement('p'); notice.setAttribute('role', 'status'); notice.hidden = true;
    notice.textContent = 'Changes not saved. Save before closing or reloading this page; in-app links offer Keep editing or Leave without saving.';
    form.prepend(notice);
    form.addEventListener('input', () => { changed = true; notice.hidden = false; });
    form.addEventListener('submit', () => { changed = false; });
    form.addEventListener('input', () => {
        const archiveNotice = document.querySelector('[data-catalog-archive-unsaved]');
        if (archiveNotice) archiveNotice.hidden = false;
    });
    window.appNavigationGuards.push({ shouldWarn: () => changed, message: 'Your changes have not been saved. Keep editing or leave without saving.' });
    const collection = form.querySelector('[name="collection_id"]');
    const suffix = form.querySelector('[name="sku_suffix"]');
    const preview = document.getElementById('sku-preview');
    const bundle = form.querySelector('[name="bundle_type"]');
    const quantity = form.querySelector('[name="bundle_quantity"]');
    const optional = form.querySelector('[data-bundle-optional]');
    const updateBundle = () => {
        if (!bundle || !quantity) return;
        bundle.required = quantity.value.trim() !== '';
        if (optional) optional.hidden = bundle.required;
    };
    form.addEventListener('input', updateBundle);
    updateBundle();
    const update = () => {
        if (preview && collection && suffix) {
            const prefix = (collection.selectedOptions[0]?.dataset.prefix || '').replace(/-+$/, '');
            preview.textContent = prefix ? prefix + '-' + suffix.value : suffix.value;
        }
    };
    form.addEventListener('input', update);
    update();
});

(() => {
    const trigger = document.querySelector('[data-catalog-archive-trigger]');
    const dialog = document.getElementById('catalog-archive-dialog');
    const form = document.getElementById('catalog-archive-form');
    const cancel = document.getElementById('catalog-archive-cancel');
    const confirm = document.getElementById('catalog-archive-confirm');
    if (!trigger || !dialog || !form || !cancel || !confirm || typeof dialog.showModal !== 'function') return;

    let submitting = false;
    // Capture before the shell's link-navigation guard; the modal explains unsaved edits.
    window.addEventListener('click', event => {
        if (!event.target.closest('[data-catalog-archive-trigger]') || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        if (!submitting && !dialog.open) dialog.showModal();
    }, true);
    cancel.addEventListener('click', () => dialog.close());
    dialog.addEventListener('cancel', event => { if (submitting) event.preventDefault(); });
    dialog.addEventListener('close', () => trigger.focus());
    form.addEventListener('submit', event => {
        if (submitting || !dialog.open || event.submitter !== confirm) {
            event.preventDefault();
            return;
        }
        submitting = true;
        cancel.disabled = true;
        confirm.disabled = true;
    });
    window.addEventListener('pageshow', () => {
        submitting = false;
        cancel.disabled = false;
        confirm.disabled = false;
        if (dialog.open) dialog.close();
    });
})();
