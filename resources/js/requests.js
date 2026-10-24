'use strict';
const requestIndex = document.querySelector('[data-request-index]');
if (requestIndex) {
    const key = `request-status:${requestIndex.dataset.requestIndex}`;
    const filters = [...requestIndex.querySelectorAll('[data-status-filter]')];
    try {
        if (performance.getEntriesByType('navigation')[0]?.type === 'reload') { sessionStorage.removeItem(key); sessionStorage.removeItem(key + ':mine'); }
        const saved = JSON.parse(sessionStorage.getItem(key));
        if (Array.isArray(saved)) filters.forEach(input => { input.checked = saved.includes(input.value); });
    } catch { /* Default filters remain usable without browser storage. */ }
    let mineOnly = false;
    try { mineOnly = sessionStorage.getItem(key + ':mine') === '1'; } catch {}
    const filter = () => {
        const selected = filters.filter(input => input.checked).map(input => input.value);
        const rows = [...requestIndex.querySelectorAll('[data-request-status]')];
        rows.forEach(row => { row.hidden = !selected.includes(row.dataset.requestStatus) || (mineOnly && row.dataset.requestOwner !== requestIndex.dataset.requestIndex.split(':').at(-1)); });
        requestIndex.querySelector('[data-my-drafts]')?.setAttribute('aria-pressed', mineOnly ? 'true' : 'false');
        requestIndex.querySelector('[data-no-requests]').hidden = rows.some(row => !row.hidden);
        try { sessionStorage.setItem(key, JSON.stringify(selected)); sessionStorage.setItem(key + ':mine', mineOnly ? '1' : '0'); } catch { /* In-page filtering still works. */ }
    };
        filters.forEach(input => input.addEventListener('change', () => { mineOnly = false; filter(); }));
    requestIndex.querySelector('[data-my-drafts]')?.addEventListener('click', () => { mineOnly = true; filters.forEach(input => { input.checked = input.value === 'Draft'; }); filter(); });
    requestIndex.querySelector('[data-show-all-requests]')?.addEventListener('click', () => { mineOnly = false; filters.forEach(input => { input.checked = true; }); filter(); });
    filter();
}
const requestForms = [...document.querySelectorAll('[data-request-form]')].filter(form => !form.hasAttribute('data-relocation-form') && !form.hasAttribute('data-purchase-form'));
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
window.appNavigationGuards?.push({
    shouldWarn: () => requestForms.some(form => !submitting.has(form) && formValues(form) !== initialValues.get(form)),
    message: 'Your changes have not been saved. Keep editing to preserve them, or leave without saving.'
});
const project = input => {
    const row = input.closest('[data-item-id]');
    if (row.dataset.projectionStale === 'true' || !row.querySelector('[data-source-after]')) return;
    const value = input.value.replaceAll(',', '');
    const quantity = /^\d+$/.test(value) ? Number(value) : NaN;
    const source = row.querySelector('[data-source]');
    const destination = row.querySelector('[data-destination]');
    const valid = Number.isInteger(quantity) && quantity >= 0 && quantity <= 1000000000;
    row.querySelector('[data-source-after]').textContent = source.dataset.source === '' ? 'Unknown' : valid ? (Number(source.dataset.source) - quantity).toLocaleString('en-US') : '—';
    row.querySelector('[data-destination-after]').textContent = destination.dataset.destination === '' ? 'Unknown' : valid ? (Number(destination.dataset.destination) + quantity).toLocaleString('en-US') : '—';
};
document.querySelectorAll('[data-request-quantity]').forEach(input => input.addEventListener('input', () => project(input)));
const remove = button => button.addEventListener('click', () => { const form = button.closest('form'); button.closest('[data-item-id]').remove(); form?.dispatchEvent(new Event('input', { bubbles: true })); });
document.querySelectorAll('[data-remove-item]').forEach(remove);
const addPurchaseRow = (choice, id) => {
    const table = document.querySelector('[data-purchase-lines]');
    if (!id || table.querySelector(`[data-item-id="${id}"]`)) return;
    const row = document.createElement('tr');
    row.dataset.itemId = id;
    const heading = document.createElement('th');
    const option = [...choice.options].find(option => option.value === id);
    if (!option) return;
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
};
document.querySelector('[data-add-purchase-item]')?.addEventListener('click', () => {
    const choice = document.querySelector('[data-purchase-item]');
    addPurchaseRow(choice, choice.value);
    choice.closest('form').dispatchEvent(new Event('input', { bubbles: true }));
});
window.appRestorePurchaseLines = (form, saved) => {
    const choice = form.querySelector('[data-purchase-item]');
    const body = form.querySelector('[data-purchase-lines]');
    if (!choice || !body) return true;
    const ids = new Set(saved.map(field => field.name.match(/^lines\[(\d+)\]/)?.[1]).filter(Boolean));
    body.querySelectorAll('[data-item-id]').forEach(row => { if (!ids.has(row.dataset.itemId)) row.remove(); });
    let restored = true;
    ids.forEach(id => {
        if (body.querySelector(`[data-item-id="${id}"]`)) return;
        addPurchaseRow(choice, id);
        if (!body.querySelector(`[data-item-id="${id}"]`)) restored = false;
    });
    return restored;
};
document.querySelector('[data-print]')?.addEventListener('click', () => window.print());
// Working navigation retains session data; it never saves a request implicitly.
[...document.querySelectorAll('[data-request-form]')].forEach(form => {
    let state = document.querySelector('[data-working-state]');
    if (!state && !form.hasAttribute('data-relocation-form') && !form.hasAttribute('data-purchase-form')) {
        state = document.createElement('p'); state.setAttribute('role', 'status'); state.hidden = true; form.prepend(state);
    }
    const markDirty = () => {
        if (form.hasAttribute('data-relocation-form') || form.hasAttribute('data-purchase-form')) return;
        state.hidden = false;
        if (form.hasAttribute('data-purchase-retain-form')) {
            state.textContent = 'Changes not saved. Entered values are retained in this browser; use this form’s separate action to save them. Save progress does not add notes or change shipment status.';
            return;
        }
        state.textContent = (form.getAttribute('action') || '').includes('/relocations/work/')
            ? 'Changes not saved as a request. Back, Next and Search retain working values only in this session. Save before closing or reloading this page; in-app links offer Keep editing or Leave without saving.'
            : 'Changes not saved. Closing or reloading may discard unsaved input; in-app links offer Keep editing or Leave without saving.';
    };
    form.addEventListener('input', markDirty);
    form.addEventListener('change', markDirty);
    const selections = [...form.querySelectorAll('[name^="selection["]')];
    const count = () => {
        const total = selections.filter(input => input.value.trim() !== '').length;
        const label = form.querySelector('[data-selection-count]');
        if (label) label.textContent = total ? `(${total})` : '';
    };
    selections.forEach(input => input.addEventListener('input', count)); count();
    form.addEventListener('submit', event => {
        if (!form.hasAttribute('data-purchase-form') && ['review', 'save', 'search', 'show_all', 'reset'].includes(event.submitter?.value) && selections.some(input => input.value !== '')) {
            event.preventDefault(); submitting.delete(form);
            let notice = form.querySelector('[data-pending-selection-notice]');
            if (!notice) {
                notice = document.createElement('p'); notice.dataset.pendingSelectionNotice = ''; notice.className = 'warning'; notice.setAttribute('role', 'alert'); notice.tabIndex = -1;
                form.append(notice);
            }
            notice.textContent = 'There are item quantities waiting in search results. Use Add selected or clear those entries before changing search results, saving or reviewing.';
            notice.hidden = false;
            form.querySelector('[data-selection-count]').closest('section[hidden]')?.removeAttribute('hidden');
            notice.focus();
        }
    });
    form.querySelectorAll('[name="source_location_id"], [name="destination_location_id"]').forEach(input => input.addEventListener('change', () => {
        if (state) state.textContent = 'Locations changed · Balances await refresh. Next: Items refreshes them without saving a request. Save before closing or reloading this page; in-app links offer Keep editing or Leave without saving.';
        form.querySelectorAll('[data-source], [data-destination], [data-route-balance]').forEach(cell => {
            cell.dataset.source = ''; cell.dataset.destination = '';
            const after = cell.querySelector('[data-source-after], [data-destination-after]');
            if (after) { after.textContent = 'Awaiting refresh'; cell.replaceChildren(document.createTextNode('Awaiting refresh → '), after); }
            else cell.textContent = 'Awaiting refresh';
            const row = cell.closest('[data-item-id]'); if (row) row.dataset.projectionStale = 'true';
        });
    }));
});
const validationSummary = document.querySelector('[aria-label="Validation errors"]');
if (validationSummary && document.querySelector('[data-working-state], [data-relocation-progress], [data-purchase-progress]')) {
    validationSummary.tabIndex = -1; validationSummary.focus();
    validationSummary.querySelectorAll('[data-error-key]').forEach(message => {
        const key = message.dataset.errorKey;
        const control = [...document.querySelectorAll('[name]')].find(input => input.name.replace(/\[([^\]]+)\]/g, '.$1') === key);
        const section = control?.closest('section[hidden]');
        if (section) section.hidden = false;
        if (key === 'lines' || key === 'selection' || key.startsWith('selection.')) {
            const items = document.getElementById('requested-items');
            items?.closest('section[hidden]')?.removeAttribute('hidden');
            if (!items) return;
            const link = document.createElement('a'); link.href = '#requested-items'; link.textContent = message.textContent;
            link.addEventListener('click', () => items.focus()); message.replaceChildren(link);
        }
    });
}
