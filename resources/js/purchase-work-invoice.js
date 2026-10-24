import { mountQuantityNavigation } from './workflow/quantity-navigation.js';
import { mountUnsavedChanges } from './workflow/unsaved-changes.js';

(() => {
    const form = document.querySelector('[data-purchase-invoice]');
    if (!form) return;
    const linkedField = document.getElementById(decodeURIComponent(location.hash.slice(1)));
    if (linkedField && !linkedField.closest('[hidden]')) { linkedField.focus(); linkedField.select?.(); }
    let dirty = false;
    form.addEventListener('input', () => { dirty = true; });
    const navigation = mountUnsavedChanges(form, { isDirty: () => dirty,
        message: 'Unsaved invoice edits will be discarded. Keep editing or leave without saving.' });
    form.addEventListener('submit', event => {
        if (event.submitter?.value === 'cancel_receipt' && !navigation.isLeaving()) {
            event.preventDefault();
            const button = event.submitter;
            navigation.confirm(() => form.requestSubmit(button), { origin: button, title: 'Cancel receiving?',
                text: 'Unsaved invoice edits and the saved receipt draft will be discarded.', actionLabel: 'Cancel receiving' });
            return;
        }
        navigation.allowDeparture();
    });
    document.querySelectorAll('form:not([data-purchase-invoice])').forEach(other => other.addEventListener('submit', event => {
        if (navigation.isLeaving()) return;
        if (!dirty) { navigation.allowDeparture(); return; }
        event.preventDefault();
        const button = event.submitter;
        navigation.confirm(() => other.requestSubmit(button), { origin: button,
            text: 'This action does not save your unsaved invoice changes. Continue?', actionLabel: 'Continue' });
    }));
    const decimal = (value, places) => {
        if (!/^\d+(?:\.\d+)?$/.test(value)) throw Error('decimal');
        const [whole, fraction = ''] = value.split('.');
        if (fraction.length > places) throw Error('precision');
        return BigInt(whole) * 10n ** BigInt(places) + BigInt(fraction.padEnd(places, '0') || '0');
    };
    const round = (number, denominator) => (number + denominator / 2n) / denominator;
    const format = (value, places) => `${value / 10n ** BigInt(places)}.${(value % 10n ** BigInt(places)).toString().padStart(places, '0')}`;
    const calculate = (row, changed) => {
        const fields = Object.fromEntries([...row.querySelectorAll('[data-invoice-field]')].map(input => [input.dataset.invoiceField, input]));
        try {
            const quantity = decimal(fields.quantity.value.replaceAll(',', ''), 0);
            if (quantity <= 0n || quantity > 1000000000n) throw Error('quantity');
            if (changed === 'cost') {
                fields.unit.value = format(round(decimal(fields.cost.value, 2) * 1000000000000n, quantity * 100n), 12);
                row.querySelector('[data-line-basis]').value = 'cost';
            } else if (changed === 'unit' || changed === 'quantity') {
                fields.cost.value = format(round(decimal(fields.unit.value, 12) * quantity, 10000000000n), 2);
                row.querySelector('[data-line-basis]').value = 'unit';
            }
            row.querySelector('[data-line-total]').textContent = format(decimal(fields.cost.value, 2) + decimal(fields.fee.value, 2), 2);
        } catch { row.querySelector('[data-line-total]').textContent = '—'; }
    };
    form.querySelectorAll('[data-invoice-line]').forEach(row => {
        calculate(row, 'initial');
    });
    form.addEventListener('change', event => {
        if (event.target.matches('[data-invoice-field]')) calculate(event.target.closest('[data-invoice-line]'), event.target.dataset.invoiceField);
    });
    form.addEventListener('click', event => {
        const button = event.target.closest('[data-recalculate]');
        if (button) { calculate(button.closest('[data-invoice-line]'), 'unit'); dirty = true; }
    });
    mountQuantityNavigation(form, { resolveFields: input => input.matches('[data-invoice-field="quantity"]') ? form.querySelectorAll('[data-invoice-field="quantity"]') : [] });
})();
