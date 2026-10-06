'use strict';
document.querySelector('[data-clear-count-filters]')?.addEventListener('click', () => {
    document.querySelectorAll('input[name="collections[]"], input[name="include_inactive"]').forEach(input => { input.checked = false; });
});
document.querySelector('[data-print]')?.addEventListener('click', () => window.print());
const countInputs = Array.from(document.querySelectorAll('[data-count]'));
countInputs.forEach((input, index) => input.addEventListener('keydown', event => {
    if (event.key === 'Enter' && index + 1 < countInputs.length) {
        event.preventDefault();
        countInputs[index + 1].focus();
    }
}));
