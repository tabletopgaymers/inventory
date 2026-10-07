'use strict';
// Integer decimal arithmetic: retained 12-place Unit never derives from a rounded display.
const invoiceScale = 1000000000000n;
const invoiceDecimal = (value, places) => {
    if (!/^\d+(?:\.\d+)?$/.test(value)) throw Error('decimal');
    const [whole, fraction = ''] = value.split('.');
    if (fraction.length > places) throw Error('precision');
    return BigInt(whole) * 10n ** BigInt(places) + BigInt(fraction.padEnd(places, '0') || '0');
};
const invoiceRound = (number, denominator) => (number + denominator / 2n) / denominator;
const invoiceFormat = (value, places) => `${value / 10n ** BigInt(places)}.${(value % 10n ** BigInt(places)).toString().padStart(places, '0')}`;
document.querySelectorAll('[data-invoice-line]').forEach(row => {
    const fields = Object.fromEntries([...row.querySelectorAll('[data-invoice-field]')].map(input => [input.dataset.invoiceField, input]));
    const basis = row.querySelector('[data-invoice-basis]');
    const total = row.querySelector('[data-invoice-total]');
    const refresh = changed => {
        try {
            const quantity = invoiceDecimal(fields.quantity.value.replaceAll(',', ''), 0);
            if (quantity <= 0n || quantity > 1000000000n) throw Error('quantity');
            if (changed === 'unit' || changed === 'quantity') {
                fields.cost.value = invoiceFormat(invoiceRound(invoiceDecimal(fields.unit.value, 12) * quantity, invoiceScale / 100n), 2);
                basis.value = 'unit';
            } else if (changed === 'cost') {
                fields.unit.value = invoiceFormat(invoiceRound(invoiceDecimal(fields.cost.value, 2) * invoiceScale, quantity * 100n), 12);
                basis.value = 'cost';
            }
            total.textContent = invoiceFormat(invoiceDecimal(fields.cost.value, 2) + invoiceDecimal(fields.fee.value, 2), 2);
        } catch { total.textContent = '—'; }
    };
    Object.entries(fields).forEach(([name, input]) => input.addEventListener('change', () => refresh(name)));
    refresh('initial');
});
document.querySelector('[data-invoice-form]')?.addEventListener('submit', event => {
    const action = event.submitter?.dataset.action;
    if (action) {
        const input = document.createElement('input'); input.type = 'hidden'; input.name = 'action'; input.value = action;
        event.currentTarget.append(input);
    }
});
