import './app.js';

document.querySelectorAll('[data-unsaved], [data-catalog-progress]').forEach(form => {
    if (form.hasAttribute('data-unsaved')) {
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
    }
    const collection = form.querySelector('[name="collection_id"]');
    const suffix = form.querySelector('[name="sku_suffix"]');
    const preview = document.getElementById('sku-preview');
    const prefixLabel = form.querySelector('[data-sku-prefix]');
    const categoryLabel = form.querySelector('[data-collection-category]');
    const bundle = form.querySelector('[name="bundle_type"]');
    const quantity = form.querySelector('[name="bundle_quantity"]');
    const updateBundle = () => {
        if (!bundle || !quantity) return;
        bundle.required = quantity.value.trim() !== '';
    };
    form.addEventListener('input', updateBundle);
    updateBundle();
    const update = () => {
        if (collection && suffix) {
            if (categoryLabel) categoryLabel.value = collection.selectedOptions[0]?.dataset.category || '';
            const prefix = (collection.selectedOptions[0]?.dataset.prefix || '').replace(/-+$/, '');
            if (preview) preview.textContent = prefix ? prefix + '-' + suffix.value : suffix.value;
            if (prefixLabel) {
                prefixLabel.textContent = prefix ? prefix + '-' : '';
                prefixLabel.hidden = !prefix;
            }
        }
    };
    form.addEventListener('input', update);
    update();
});

(() => {
    const trigger = document.querySelector('[data-catalog-archive-trigger]');
    const form = document.getElementById('catalog-archive-form');
    if (!trigger || !form) return;
    let approved = false, submitting = false;
    window.addEventListener('click', event => {
        if (!event.target.closest('[data-catalog-archive-trigger]') || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        if (submitting || document.querySelector('[data-catalog-progress][data-save-busy="true"]')) return;
        window.appRequestDecision({title:form.dataset.archiveTitle,
            message:'The record, associated records, inventory and earlier history will be retained. This changes only the selected record. Restoration returns it to Inactive; activation is a separate action. Any unsaved edits will not be saved.',
            origin:trigger, confirmLabel:'Archive', variant:'danger',
            onConfirm:() => { approved = true; form.requestSubmit(); approved = false; }});
    }, true);
    form.addEventListener('submit', event => {
        if (submitting || !approved) { event.preventDefault(); return; }
        submitting = true;
    });
    window.addEventListener('pageshow', () => { submitting = false; });
})();
