import './app.js';
import './inventory-search-controls.js';
import { mountQuantityNavigation } from './workflow/quantity-navigation.js';

// Reconcile owns only its location-specific zero-stock default.
const searchForm = document.querySelector('[data-count-search]');
if (searchForm) {
    const location = searchForm.querySelector('[name="location_id"]');
    const zero = searchForm.querySelector('[type="checkbox"][name="include_active_zero"]');
    location.addEventListener('change', () => {
        zero.checked = location.selectedOptions[0]?.dataset.central === '1';
        searchForm.querySelector('[name="zero_stock_location"]').value = location.value;
    });
}

document.querySelector('[data-print]')?.addEventListener('click', () => window.print());
const countForm = document.querySelector('[data-count-entry]');
if (countForm) {
    mountQuantityNavigation(countForm, {
        resolveFields: () => countForm.querySelectorAll('[data-count]'),
    });
}
document.querySelectorAll('.error a[href^="#count-"]').forEach(link => link.addEventListener('click', () => {
    document.getElementById(link.hash.slice(1))?.focus();
}));
