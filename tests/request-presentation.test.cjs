const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');

function form(name, value) {
    const handlers = {};
    return { elements: [{ name, value, tagName: 'INPUT', type: 'text' }],
        addEventListener: (name, handler) => { handlers[name] = handler; }, handlers };
}

function load(forms, quantities = []) {
    const handlers = {};
    const context = {
        document: {
            querySelector: () => null,
            querySelectorAll: selector => selector === '[data-request-form]' ? forms : selector === '[data-request-quantity]' ? quantities : [],
        },
        window: { addEventListener: (name, handler) => { handlers[name] = handler; } },
    };
    vm.runInNewContext(readFileSync(require.resolve('../public/requests.js'), 'utf8'), context);
    return () => {
        let warned = false;
        handlers.beforeunload({ preventDefault: () => { warned = true; } });
        return warned;
    };
}

test('dirty guard restores values, isolates submissions and detects line additions/removals', () => {
    const note = form('note', ''); const title = form('title', 'Original');
    const warn = load([note, title]);
    assert.equal(warn(), false);
    note.elements[0].value = 'Unsent permanent note';
    assert.equal(warn(), true);
    note.elements[0].value = '';
    assert.equal(warn(), false);
    note.elements[0].value = 'Unsent permanent note';
    title.elements[0].value = 'Changed';
    title.handlers.submit({ defaultPrevented: false });
    assert.equal(warn(), true, 'Submitting title must preserve the unsent note guard');
    note.handlers.submit({ defaultPrevented: true });
    assert.equal(warn(), true, 'Prevented submission cannot clear protection');
    note.elements[0].value = '';
    assert.equal(warn(), true, 'Canceled departure must re-arm the still-changed title');
    title.elements[0].value = 'Original';
    assert.equal(warn(), false, 'Restoring both forms removes the warning');
    note.elements.push({ name: 'lines[42][quantity]', value: '0', tagName: 'INPUT', type: 'text' });
    assert.equal(warn(), true);
    note.elements.pop();
    assert.equal(warn(), false);
    title.elements[0].value = 'Changed again';
    title.handlers.input();
    assert.equal(warn(), true, 'Further edits remain protected');
});

test('canceled departure re-arms submitted title after fulfillment is restored', () => {
    const title = form('title', 'Original'); const fulfillment = form('fulfillment[42]', '0');
    const warn = load([title, fulfillment]);
    title.elements[0].value = 'Unsaved title';
    fulfillment.elements[0].value = '5';
    title.handlers.submit({ defaultPrevented: false });
    assert.equal(warn(), true, 'Other dirty form blocks departure');
    // Cancel leaves the same document alive; no further title input occurs.
    fulfillment.elements[0].value = '0';
    assert.equal(warn(), true, 'Later navigation must still protect the title');
    title.handlers.submit({ defaultPrevented: false });
    assert.equal(warn(), false, 'A fresh authorized submission may depart');
});

test('successful submission remains exempt until further input', () => {
    const title = form('title', 'Original');
    const warn = load([title]);
    title.elements[0].value = 'Saved title';
    title.handlers.submit({ defaultPrevented: false });
    assert.equal(warn(), false);
    title.handlers.input();
    assert.equal(warn(), true);
    title.elements[0].value = 'Original';
    assert.equal(warn(), false);
});

test('client projections independently preserve unknown, zero and invalid quantities', () => {
    for (const [source, destination, quantity, afterSource, afterDestination] of [
        ['', '', '2', 'Unknown', 'Unknown'], ['0', '', '2', '-2', 'Unknown'],
        ['', '0', '2', 'Unknown', '2'], ['17', '-5', '2', '15', '-3'],
        ['0', '0', 'bad', '—', '—'],
    ]) {
        const cells = {
            '[data-source]': { dataset: { source } }, '[data-destination]': { dataset: { destination } },
            '[data-source-after]': {}, '[data-destination-after]': {},
        };
        let project;
        load([], [{ value: quantity, closest: () => ({ querySelector: selector => cells[selector] }), addEventListener: (_, handler) => { project = handler; } }]);
        project();
        assert.equal(cells['[data-source-after]'].textContent, afterSource);
        assert.equal(cells['[data-destination-after]'].textContent, afterDestination);
    }
});

test('ordinary keyed errors retain values and connect unique controls to their summaries', () => {
    const makeInput = (name, label) => ({ name, value: 'invalid retained value', id: '', labels: [{ textContent: label }],
        attributes: {}, closest: () => null, getAttribute(key) { return this.attributes[key] || null; },
        setAttribute(key, value) { this.attributes[key] = value; },
        insertAdjacentElement(_, element) { this.inline = element; }, focus() { this.focused = true; } });
    const quantity = makeInput('lines[42][quantity]', 'Quantity for item · SKU-A');
    const title = makeInput('title', 'Title');
    const summary = { dataset: { errorKey: 'lines.42.quantity' }, textContent: 'Enter whole units.',
        replaceChildren(link) { this.link = link; } };
    const elements = [quantity, title];
    const context = { document: {
        querySelectorAll: selector => selector.startsWith('form input') ? elements : selector === '[data-error-key]' ? [summary] : [],
        getElementById: () => null,
        createElement: () => ({ addEventListener(name, handler) { this[name] = handler; } }),
    }, window: { matchMedia: () => ({}) } };
    vm.runInNewContext(readFileSync(require.resolve('../public/app-shell.js'), 'utf8'), context);
    assert.notEqual(quantity.id, title.id);
    assert.equal(quantity.value, 'invalid retained value');
    assert.equal(summary.link.href, `#${quantity.id}`);
    assert.match(summary.link.textContent, /SKU-A: Enter whole units/);
    assert.equal(quantity.attributes['aria-invalid'], 'true');
    assert.equal(quantity.attributes['aria-describedby'], quantity.inline.id);
    summary.link.click();
    assert.equal(quantity.focused, true);
    assert.equal(title.inline, undefined);
});
