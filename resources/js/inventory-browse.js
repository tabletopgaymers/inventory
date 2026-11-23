import './app.js';
import './inventory-search-controls.js';

// Inventory owns its linked result pages; controls and persistence are shared.
const root = document.getElementById('inventory-main-search');
const search = root && window.appInventorySearch.get(root);
if (search) {
    document.querySelectorAll('[data-browse-link]').forEach(link => link.addEventListener('click', event => {
        if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        search.navigateWithPreferences(new URL(link.href).pathname);
    }));
}
