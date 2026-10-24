import { mountTabs } from './workflow/tabs.js';
import { mountQuantityNavigation } from './workflow/quantity-navigation.js';
import { mountUnsavedChanges } from './workflow/unsaved-changes.js';
import '../../public/inventory-search-controls.js';

(() => {
    'use strict';
    const form = document.querySelector('#purchase-request-form');
    if (!form) return;
    const searchRoot = form.querySelector('[data-inventory-search]');
    const rows = [...form.querySelectorAll('[data-item-row]')];
    const validQuantity = value => /^\d+$/.test(value.trim()) && Number.isSafeInteger(Number(value)) && Number(value) <= 1000000000;
    let dirty = false;
    const navigation = mountUnsavedChanges(form, { isDirty: () => dirty });
    const errors = form.querySelector('[data-purchase-errors]');
    const reviewBody = form.querySelector('[data-review-items]');
    const cell = (tag, value) => { const el = document.createElement(tag); el.textContent = value; return el; };
    const updateReview = () => {
        const messages = [];
        if (!form.elements.title.value.trim()) messages.push({text:'Enter a title in Basic info.',tab:'basic',input:form.elements.title});
        form.querySelectorAll('[data-review]').forEach(el => { el.textContent = form.elements[el.dataset.review].value || '—'; });
        reviewBody.replaceChildren();
        let group = null;
        rows.forEach(row => {
            const input = row.querySelector('[data-quantity]');
            const invalid = !validQuantity(input.value);
            row.classList.toggle('table-row-error', invalid && form.dataset.tab === 'review');
            if (invalid) messages.push({text:`${row.dataset.name}: enter a nonnegative whole requested quantity (zero is allowed).`,tab:'material',input});
            if (group !== row.dataset.group) {
                group = row.dataset.group;
                const heading = document.createElement('tr'); heading.className = 'group-row';
                const th = cell('th', group); th.colSpan = 2; th.scope = 'rowgroup'; heading.append(th); reviewBody.append(heading);
            }
            const tr = document.createElement('tr');
            if (invalid) tr.className = 'table-row-error';
            const amount = cell('td', input.value.trim() === '' ? '—' : input.value); amount.className = 'table-numeric table-quantity-column';
            const name = cell('td', row.dataset.name); name.className = 'app-grouped-item';
            tr.append(name, amount); reviewBody.append(tr);
        });
        if (!rows.length) { const tr = document.createElement('tr'); const td = cell('td','No catalog items requested. See Request details.'); td.colSpan = 2; tr.append(td); reviewBody.append(tr); }
        errors.replaceChildren(...messages.map(message => {
            const paragraph = document.createElement('p');
            const link = cell('a',message.text); link.href = '#'+(message.input.id || 'purchase-panel-'+message.tab);
            link.addEventListener('click',event => { event.preventDefault(); showTab(message.tab); message.input.focus(); message.input.select?.(); });
            paragraph.append(link); return paragraph;
        }));
        errors.hidden = form.dataset.tab !== 'review' || !messages.length;
        form.querySelector('[data-submit-request]').disabled = form.dataset.canSubmit !== 'true' || messages.length > 0;
    };
    const showTab = tab => tabUI.activate(tab);
    const tabUI = mountTabs(form, { initialKey: new URL(location.href).searchParams.get('tab') || form.dataset.tab, onChange: tab => {
        form.dataset.tab = tab; form.querySelector('[data-current-tab]').value = tab;
        updateReview();
    } });
    form.querySelector('[data-material-next]').addEventListener('click',event => { event.preventDefault(); showTab('review'); });
    const changed = event => {
        if (searchRoot?.contains(event.target)) return;
        dirty = true; updateReview();
    };
    form.addEventListener('input',changed);
    form.addEventListener('change',changed);
    mountQuantityNavigation(form, { resolveFields: input => input.matches('[data-quantity]') ? form.querySelectorAll('[data-quantity]') : [] });
    form.addEventListener('submit',event => {
        if (event.submitter?.value === 'submit' && form.querySelector('[data-submit-request]').disabled) { event.preventDefault(); showTab('review'); return; }
        navigation.allowDeparture();
    });
    if (searchRoot) {
        const select = form.querySelector('[data-item-select]');
        const filterOptions = criteria => {
            const term = criteria.search.toLocaleLowerCase(), matches = [];
            select.querySelectorAll('optgroup').forEach(group => {
                let visible = false;
                [...group.children].forEach(option => {
                    option.hidden = !(criteria.collections.length === 0 || criteria.collections.includes(Number(group.dataset.collection)))
                        || !`${group.label} ${option.textContent}`.toLocaleLowerCase().includes(term);
                    visible ||= !option.hidden;
                    if (!option.hidden) matches.push({ id: Number(option.value) });
                });
                group.hidden = !visible;
            });
            if (select.selectedOptions[0]?.hidden) select.value = '';
            return matches;
        };
        window.appInventorySearch.mount(searchRoot, { request: async (_url, criteria) => {
            const matches = filterOptions(criteria);
            return { schema_version: 1, rows: matches, columns: {}, count: matches.length, criteria };
        } });
        // Keep the existing immediate select filtering; the shared actions also work.
        searchRoot.addEventListener('inventory-search:pending', event => filterOptions(event.detail.criteria));
    }
    document.querySelectorAll('[data-confirm-transition]').forEach(other => other.addEventListener('submit',event => {
        if (navigation.isLeaving()) return;
        event.preventDefault();
        const button = event.submitter;
        navigation.confirm(() => other.requestSubmit(button), { origin: button, title: 'Confirm status change',
            text: 'Continue with this status change? Unsaved form changes will not be saved.', actionLabel: 'Continue' });
    }));
    document.querySelectorAll('form:not(#purchase-request-form):not([data-confirm-transition])').forEach(other => other.addEventListener('submit',event => {
        if (navigation.isLeaving()) return;
        if (!dirty) { navigation.allowDeparture(); return; }
        event.preventDefault();
        const button = event.submitter;
        navigation.confirm(() => other.requestSubmit(button), { origin: button,
            text: 'This action does not save your unsaved request changes. Continue?', actionLabel: 'Continue' });
    }));
})();
