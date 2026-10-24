'use strict';
const searchForm = document.querySelector('[data-count-search]');
if (searchForm) {
    const location = searchForm.querySelector('[name="location_id"]');
    const zero = searchForm.querySelector('[type="checkbox"][name="include_active_zero"]');
    location.addEventListener('change', () => {
        zero.checked = location.selectedOptions[0]?.dataset.central === '1';
        searchForm.querySelector('[name="zero_stock_location"]').value = location.value;
    });
    let submitting = false;
    const button = searchForm.querySelector('[value="search"]');
    searchForm.addEventListener('submit', event => {
        if (submitting) { event.preventDefault(); return; }
        if (event.defaultPrevented) return;
        submitting = true;
        button.setAttribute('aria-busy', 'true');
        searchForm.querySelector('[data-search-status]').textContent = 'Searching inventory…';
    });
    window.addEventListener('pageshow', () => {
        submitting = false;
        button.setAttribute('aria-busy', 'false');
        searchForm.querySelector('[data-search-status]').textContent = '';
    });
}
document.querySelector('[data-clear-count-filters]')?.addEventListener('click', () => {
    document.querySelectorAll('input[name="collections[]"], input[type="checkbox"][name="include_active_zero"]').forEach(input => { input.checked = false; });
});
document.querySelector('[data-print]')?.addEventListener('click', () => window.print());
const countInputs = Array.from(document.querySelectorAll('[data-count]'));
document.querySelectorAll('.error a[href^="#count-"]').forEach(link => link.addEventListener('click', () => {
    document.getElementById(link.hash.slice(1))?.focus();
}));
countInputs.forEach((input, index) => input.addEventListener('keydown', event => {
    if (event.key === 'Enter' && index + 1 < countInputs.length) {
        event.preventDefault();
        countInputs[index + 1].focus();
    }
}));
