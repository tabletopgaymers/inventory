'use strict';
const requestIndex = document.querySelector('[data-request-index]');
if (requestIndex) {
    const key = `request-status:${requestIndex.dataset.requestIndex}`;
    const filters = [...requestIndex.querySelectorAll('[data-status-filter]')];
    try {
        if (performance.getEntriesByType('navigation')[0]?.type === 'reload') sessionStorage.removeItem(key);
        const saved = JSON.parse(sessionStorage.getItem(key));
        if (Array.isArray(saved)) filters.forEach(input => { input.checked = saved.includes(input.value); });
    } catch { /* Default filters remain usable without browser storage. */ }
    const filter = () => {
        const selected = filters.filter(input => input.checked).map(input => input.value);
        const rows = [...requestIndex.querySelectorAll('[data-request-status]')];
        rows.forEach(row => { row.hidden = !selected.includes(row.dataset.requestStatus); });
        requestIndex.querySelector('[data-no-requests]').hidden = rows.some(row => !row.hidden);
        try { sessionStorage.setItem(key, JSON.stringify(selected)); } catch { /* In-page filtering still works. */ }
    };
    filters.forEach(input => input.addEventListener('change', filter));
    filter();
}
const requestForms = [...document.querySelectorAll('[data-request-form]')];
const formValues = form => JSON.stringify([...form.elements]
    .filter(input => input.name && !input.disabled && ['INPUT', 'SELECT', 'TEXTAREA'].includes(input.tagName) && input.type !== 'hidden')
    .map(input => [input.name, input.type === 'checkbox' || input.type === 'radio' ? input.checked : input.value]));
const initialValues = new Map(requestForms.map(form => [form, formValues(form)]));
const submitting = new Set();
requestForms.forEach(form => {
    form.addEventListener('input', () => submitting.delete(form));
    form.addEventListener('change', () => submitting.delete(form));
    form.addEventListener('submit', event => { if (!event.defaultPrevented) submitting.add(form); });
});
window.addEventListener('beforeunload', event => {
    if (requestForms.some(form => !submitting.has(form) && formValues(form) !== initialValues.get(form))) {
        // The user may cancel departure and keep this document's unsaved forms.
        submitting.clear();
        event.preventDefault(); event.returnValue = '';
    }
});
document.querySelectorAll('[data-confirm]').forEach(button => button.addEventListener('click', event => {
    if (!window.confirm(button.dataset.confirm)) event.preventDefault();
}));
const project = input => {
    const row = input.closest('[data-item-id]');
    const value = input.value.replaceAll(',', '');
    const quantity = /^\d+$/.test(value) ? Number(value) : NaN;
    const source = row.querySelector('[data-source]');
    const destination = row.querySelector('[data-destination]');
    const valid = Number.isInteger(quantity) && quantity >= 0 && quantity <= 1000000000;
    row.querySelector('[data-source-after]').textContent = source.dataset.source === '' ? 'Unknown' : valid ? (Number(source.dataset.source) - quantity).toLocaleString('en-US') : '—';
    row.querySelector('[data-destination-after]').textContent = destination.dataset.destination === '' ? 'Unknown' : valid ? (Number(destination.dataset.destination) + quantity).toLocaleString('en-US') : '—';
};
document.querySelectorAll('[data-request-quantity]').forEach(input => input.addEventListener('input', () => project(input)));
const remove = button => button.addEventListener('click', () => { button.closest('[data-item-id]').remove(); });
document.querySelectorAll('[data-remove-item]').forEach(remove);
document.querySelector('[data-add-purchase-item]')?.addEventListener('click', () => {
    const choice = document.querySelector('[data-purchase-item]');
    const table = document.querySelector('[data-purchase-lines]');
    const id = choice.value;
    if (!id || table.querySelector(`[data-item-id="${id}"]`)) return;
    const row = document.createElement('tr');
    row.dataset.itemId = id;
    const heading = document.createElement('th');
    const option = choice.selectedOptions[0];
    const identity = option.textContent;
    heading.textContent = option.dataset.name;
    const sku = document.createElement('small');
    sku.className = 'app-item-sku'; sku.textContent = option.dataset.sku;
    heading.append(sku);
    row.append(heading);
    for (const field of ['quantity', 'estimate', 'note']) {
        const cell = document.createElement('td');
        const input = document.createElement(field === 'note' ? 'textarea' : 'input');
        input.name = `lines[${id}][${field}]`;
        input.setAttribute('aria-label', `${field} for ${identity}${field === 'quantity' ? '' : ' (optional)'}`);
        input.required = field === 'quantity';
        input.value = field === 'quantity' ? '0' : '';
        if (field === 'note') input.maxLength = 5000;
        else input.inputMode = field === 'estimate' ? 'decimal' : 'numeric';
        cell.append(input); row.append(cell);
    }
    const cell = document.createElement('td');
    const button = document.createElement('button');
    button.type = 'button'; button.textContent = 'Remove'; button.setAttribute('aria-label', `Remove ${identity}`); remove(button);
    cell.append(button); row.append(cell); table.append(row);
    choice.value = '';
});
document.querySelector('[data-print]')?.addEventListener('click', () => window.print());
