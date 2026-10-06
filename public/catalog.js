document.querySelectorAll('[data-unsaved]').forEach(form => {
    let changed = false;
    form.addEventListener('input', () => { changed = true; });
    form.addEventListener('submit', () => { changed = false; });
    window.addEventListener('beforeunload', event => { if (changed) { event.preventDefault(); event.returnValue = ''; } });
    const collection = form.querySelector('[name="collection_id"]');
    const suffix = form.querySelector('[name="sku_suffix"]');
    const preview = document.getElementById('sku-preview');
    const update = () => { if (preview) preview.textContent = (collection.selectedOptions[0]?.dataset.prefix || '') + suffix.value; };
    form.addEventListener('input', update);
    update();
});
