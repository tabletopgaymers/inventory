document.querySelectorAll('[data-unsaved]').forEach(form => {
    let changed = false;
    const notice = document.createElement('p'); notice.setAttribute('role', 'status'); notice.hidden = true;
    notice.textContent = 'Changes not saved. Save before closing or reloading this page; in-app links offer Keep editing or Leave without saving.';
    form.prepend(notice);
    form.addEventListener('input', () => { changed = true; notice.hidden = false; });
    form.addEventListener('submit', () => { changed = false; });
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
    const update = () => { if (preview) preview.textContent = (collection.selectedOptions[0]?.dataset.prefix || '') + suffix.value; };
    form.addEventListener('input', update);
    update();
});
