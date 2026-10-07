document.querySelectorAll('[data-unsaved]').forEach(form => {
    let changed = false;
    form.addEventListener('input', () => { changed = true; });
    form.addEventListener('submit', () => { changed = false; });
    window.addEventListener('beforeunload', event => { if (changed) { event.preventDefault(); event.returnValue = ''; } });
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
