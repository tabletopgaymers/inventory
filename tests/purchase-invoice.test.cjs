const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');

function invoice(values) {
    const fields = Object.fromEntries(Object.entries(values).map(([name, value]) => [name, { value, dataset: { invoiceField: name }, handlers: {}, addEventListener(event, callback) { this.handlers[event] = callback; } }]));
    const basis = { value: 'unit' };
    const total = { textContent: '' };
    const row = { querySelectorAll: () => Object.values(fields), querySelector: selector => selector === '[data-invoice-basis]' ? basis : total };
    vm.runInNewContext(readFileSync(require.resolve('../public/purchase-invoice.js'), 'utf8'), { document: { querySelectorAll: () => [row], querySelector: () => null } });
    return { fields, basis, total, change(name, value) { fields[name].value = value; fields[name].handlers.change(); } };
}

test('Cost edit retains exact derived Unit through quantity and fee edits', () => {
    const row = invoice({ quantity: '5200', unit: '0.173076923077', cost: '900.00', fee: '15.00' });
    assert.equal(row.total.textContent, '915.00');
    row.change('cost', '1040.00');
    assert.equal(row.fields.unit.value, '0.200000000000');
    row.change('quantity', '6000');
    assert.equal(row.fields.cost.value, '1200.00');
    row.change('fee', '30.00');
    assert.equal(row.fields.unit.value, '0.200000000000');
    assert.equal(row.fields.cost.value, '1200.00');
    assert.equal(row.total.textContent, '1230.00');
});

test('Unit edits commit on change, preserve typing, and round large exact products in cents', () => {
    const row = invoice({ quantity: '1000000000', unit: '0.000000000005', cost: '0.01', fee: '0' });
    assert.deepEqual(Object.keys(row.fields.unit.handlers), ['change']);
    row.change('unit', '0.000000000005');
    assert.equal(row.fields.cost.value, '0.01');
    row.change('unit', '0.000000000004');
    assert.equal(row.fields.cost.value, '0.00');
    row.change('quantity', 'x');
    assert.equal(row.total.textContent, '—');
});

test('refresh/recalculation does not feed shortened Unit text into a correct invoice', () => {
    const row = invoice({ quantity: '5200', unit: '0.173076923077', cost: '900.00', fee: '15.00' });
    assert.equal(row.fields.unit.value, '0.173076923077');
    row.change('quantity', '5,200');
    assert.equal(row.fields.unit.value, '0.173076923077');
    assert.equal(row.fields.cost.value, '900.00');
    assert.equal(row.basis.value, 'unit');
});
