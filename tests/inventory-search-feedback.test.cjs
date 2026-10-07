const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');

function control(value = '') {
    const handlers = {}, attributes = {};
    return { value, checked: false, textContent: '', selectedOptions: [], handlers, attributes,
        addEventListener: (name, handler) => { handlers[name] = handler; },
        setAttribute: (name, value) => { attributes[name] = value; } };
}

test('count loading suppresses duplicates, retains submitter and resets on pageshow; location changes apply defaults', () => {
    const windowHandlers = {};
    const button = control('search'), status = control(), location = control('12'), zero = control(), marker = control('12');
    const form = control();
    form.querySelector = selector => selector === '[value="search"]' ? button : selector === '[data-search-status]' ? status : selector === '[name="location_id"]' ? location : selector === '[name="zero_stock_location"]' ? marker : zero;
    vm.runInNewContext(readFileSync(require.resolve('../public/location-counts.js'), 'utf8'), {
        document: { querySelector: selector => selector === '[data-count-search]' ? form : null, querySelectorAll: () => [] },
        window: { addEventListener: (name, handler) => { windowHandlers[name] = handler; } },
    });
    assert.equal(button.attributes['aria-busy'], undefined, 'Without a native-valid submit event no pending state is set.');
    const first = { defaultPrevented: false, submitter: button, preventDefault() { this.defaultPrevented = true; } };
    form.handlers.submit(first);
    assert.equal(first.defaultPrevented, false, 'Native submitter action/value is preserved.');
    assert.equal(button.value, 'search');
    assert.equal(button.attributes['aria-busy'], 'true');
    assert.match(status.textContent, /Searching/);
    const duplicate = { ...first, defaultPrevented: false };
    form.handlers.submit(duplicate);
    assert.equal(duplicate.defaultPrevented, true);
    windowHandlers.pageshow({ persisted: true });
    assert.equal(button.attributes['aria-busy'], 'false');
    assert.equal(status.textContent, '');
    location.value = '1'; location.selectedOptions = [{ dataset: { central: '1' } }];
    location.handlers.change();
    assert.equal(zero.checked, true); assert.equal(marker.value, '1');
    zero.checked = false;
    assert.equal(zero.checked, false, 'Subsequent user override is retained until another location change.');
    location.value = '12'; location.selectedOptions = [{ dataset: { central: '0' } }];
    location.handlers.change();
    assert.equal(zero.checked, false); assert.equal(marker.value, '12');
});

test('Inventory keyboard and button submissions submit once with the original action and clear pending on Back', async () => {
    const windowHandlers = {}, elements = {};
    for (const id of ['pending-notice', 'preference-error', 'filter-summary', 'clear-filters']) elements[id] = control();
    elements['inventory-filters'] = { classList: { toggle() {} } };
    elements['browse-state'] = { textContent: '{"submitted":null,"scroll":0}' };
    const button = control('search'), status = control(), actions = [];
    let submits = 0;
    const form = control();
    form.querySelector = selector => selector === '[value="search"]' ? button : status;
    form.querySelectorAll = () => [];
    form.append = input => actions.push(input);
    form.submit = () => { submits++; };
    elements['inventory-search'] = form;
    elements['inventory-results'] = { scrollTop: 0 };
    vm.runInNewContext(readFileSync(require.resolve('../public/inventory-browse.js'), 'utf8'), {
        document: { getElementById: id => elements[id], querySelectorAll: () => [], createElement: () => ({}) },
        window: { addEventListener: (name, handler) => { windowHandlers[name] = handler; } },
        FormData: class { get() { return ''; } getAll() { return []; } },
        clearTimeout() {}, requestAnimationFrame: callback => callback(),
    });
    const event = value => ({ submitter: value ? { value } : undefined, preventDefault() {} });
    const first = form.handlers.submit(event());
    await form.handlers.submit(event('show_all'));
    assert.equal(button.attributes['aria-busy'], 'true');
    await first;
    assert.equal(submits, 1);
    assert.equal(actions.length, 1); assert.equal(actions[0].name, 'action'); assert.equal(actions[0].value, 'search');
    windowHandlers.pageshow({ persisted: true });
    assert.equal(button.attributes['aria-busy'], 'false'); assert.equal(status.textContent, '');
    await form.handlers.submit(event('show_all'));
    assert.equal(submits, 2); assert.equal(actions[1].value, 'show_all');
});
