import './app.js';
import './inventory-search-controls.js';

// Inventory owns its linked result pages; controls and persistence are shared.
const root = document.getElementById('inventory-main-search');
const search = root && window.appInventorySearch.get(root);
const listForm = document.querySelector('[data-inventory-list-load] form');
if (search) {
    document.querySelectorAll('[data-browse-link]').forEach(link => link.addEventListener('click', event => {
        if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        search.navigateWithPreferences(new URL(link.href).pathname);
    }));
}

if (listForm && search?.persistence) {
    const loadButton = listForm.querySelector('button[type="submit"]');
    listForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (loadButton.disabled || search.getState().busy) return;
        loadButton.disabled = true;
        root.inert = true;
        listForm.inert = true;
        await search.persistence.beforeSubmit({flush: true});
        listForm.submit();
    });
    window.addEventListener('pageshow', () => {
        loadButton.disabled = false;
        root.inert = false;
        listForm.inert = false;
    });
}

if (document.querySelector('[data-inventory-list-results]')) {
    window.addEventListener('pageshow', event => {
        if (event.persisted) window.location.reload();
    });
}
