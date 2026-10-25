import './app.js';

import { mountTabs } from './workflow/tabs.js';
import { mountQuantityNavigation } from './workflow/quantity-navigation.js';
import { mountUnsavedChanges } from './workflow/unsaved-changes.js';
import './inventory-search-controls.js';
import { mountProgressSave } from './workflow/progress-save.js';

(() => {
    'use strict';
    const form = document.querySelector('#purchase-request-form');
    if (!form) return;
    const searchRoot = form.querySelector('[data-inventory-search]');
    const rows = () => [...form.querySelectorAll('[data-item-row]')];
    const searchResults = form.querySelector('[data-purchase-search-results]');
    const searchFeedback = form.querySelector('[data-purchase-search-feedback]');
    const addSelected = form.querySelector('[data-add-selected]');
    const pendingSelections = () => searchResults?.querySelector('[data-search-item-check]:checked')
        || [...(searchResults?.querySelectorAll('[data-search-quantity]') || [])].some(input => input.value.trim() !== '');
    const validQuantity = value => /^\d+$/.test(value.trim()) && Number.isSafeInteger(Number(value)) && Number(value) <= 1000000000;
    let dirty = form.dataset.workspaceDirty === 'true';
    let editGeneration = 0;
    const save = document.querySelector('[data-save-shortcut]');
    save.classList.toggle('app-save-dirty', dirty);
    let saveBusy = false;
    const navigation = mountUnsavedChanges(form, { isDirty: () => dirty || Boolean(pendingSelections()) });
    const errors = form.querySelector('[data-purchase-errors]');
    const reviewBody = form.querySelector('[data-review-items]');
    const cell = (tag, value) => { const el = document.createElement(tag); el.textContent = value; return el; };
    const isOtherChoice = select => select.value === 'other' || select.selectedOptions[0]?.dataset.other === 'true';
    const materialRows = () => [...form.querySelectorAll('[data-material-line]')];
    const money = cents => {
        const digits = cents.toString().padStart(3, '0');
        return digits.slice(0,-2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')+'.'+digits.slice(-2);
    };
    const updateMaterialTotals = () => {
        let sum = 0n, known = 0, incomplete = false;
        materialRows().forEach(row => {
            const quantity = row.querySelector('[data-quantity]').value.trim();
            const enteredUnit = row.querySelector('[data-estimated-unit]').value.trim();
            const unit = enteredUnit.startsWith('.') ? '0'+enteredUnit : enteredUnit;
            const target = row.querySelector('[data-material-total]');
            if (!validQuantity(quantity) || !/^\d{1,12}(?:\.\d{1,12})?$/.test(unit)) {
                target.textContent = '—'; incomplete = true; return;
            }
            const [whole, fraction = ''] = unit.split('.');
            const scale = 10n ** BigInt(fraction.length);
            const numerator = BigInt(quantity) * BigInt(whole+fraction) * 100n;
            const cents = (numerator + scale/2n) / scale;
            sum += cents; known++;
            target.textContent = money(cents);
        });
        const total = form.querySelector('[data-material-grand-total]');
        total.textContent = known ? money(sum) : '—';
        form.querySelector('[data-material-grand-total-label]').textContent = (incomplete ? '(INCOMPLETE) ' : '')+'Estimated merchandise total';
    };
    updateMaterialTotals();
    const updateBasicChoices = () => {
        form.querySelectorAll('[data-other-choice]').forEach(select => {
            const panel = form.querySelector(`[data-other-fields="${select.dataset.otherChoice}"]`);
            panel.hidden = !isOtherChoice(select);
            select.setAttribute('aria-expanded', String(!panel.hidden));
        });
    };
    updateBasicChoices();
    mountProgressSave(form, { feedback:form.querySelector('[data-progress-feedback]'), onBusy:value => { saveBusy = value; },
        canSave:() => !searchRoot || !window.appInventorySearch.get(searchRoot)?.getState().busy,
        getGeneration:() => editGeneration, onSaved:(result, {unchanged}) => {
        form.elements.revision.value = result.revision;
        document.querySelectorAll('form input[name="revision"]').forEach(input => { input.value = result.revision; });
        if (!unchanged) return;
        Object.entries(result.values || {}).forEach(([name,value]) => {
            const input = form.elements[name];
            if (input && input.tagName !== 'SELECT') input.value = value ?? '';
        });
        const vendor = form.elements.vendor_choice;
        const choice = result.values?.vendor_choice;
        if (choice && ![...vendor.options].some(option => option.value === choice)) vendor.add(new Option(result.values.vendor_name || 'Vendor',choice));
        if (choice) vendor.value = choice;
        [...vendor.options].forEach(option => {
            option.dataset.other = String(Boolean(result.other_vendor_id) && option.value === `supplier:${result.other_vendor_id}`);
            if (option.dataset.other === 'true') option.textContent = `OTHER: ${result.values.vendor_name}`;
        });
        form.querySelectorAll('input[name="revision"]').forEach(input => { input.value = result.revision; });
        document.querySelectorAll('form input[name="revision"]').forEach(input => { input.value = result.revision; });
        document.querySelector('.status-badge').textContent = result.status;
        form.querySelector('[data-fulfillment-links]').hidden = false;
        dirty = false; save.classList.remove('app-save-dirty');
        updateBasicChoices(); updateReview();
    }});
    const updateReview = () => {
        const messages = [];
        if (!form.elements.title.value.trim()) messages.push({text:'Enter a title in Basic info.',tab:'basic',input:form.elements.title});
        const requireBasic = (name, text) => {
            if (!form.elements[name].value.trim()) messages.push({text,tab:'basic',input:form.elements[name]});
        };
        requireBasic('vendor_choice', 'Choose a vendor or suggested source in Basic info.');
        requireBasic('delivery_choice', 'Choose where to deliver the purchase in Basic info.');
        if (isOtherChoice(form.elements.vendor_choice)) requireBasic('vendor_name', 'Enter the other vendor’s name in Basic info.');
        if (form.elements.delivery_choice.value === 'other') {
            requireBasic('delivery_name', 'Enter the delivery name in Basic info.');
            requireBasic('delivery_address', 'Enter the mailing address in Basic info.');
        }
        ['vendor','delivery'].forEach(kind => {
            const email = form.elements[`${kind}_email`];
            if (isOtherChoice(form.elements[`${kind}_choice`]) && email.validity.typeMismatch) {
                messages.push({text:`Enter a valid ${kind === 'vendor' ? 'vendor' : 'delivery'} email in Basic info.`,tab:'basic',input:email});
            }
        });
        form.querySelectorAll('[data-review]').forEach(el => { el.textContent = form.elements[el.dataset.review].value || '—'; });
        form.querySelectorAll('[data-review-choice]').forEach(el => {
            const kind = el.dataset.reviewChoice;
            const select = form.elements[`${kind}_choice`];
            const fields = kind === 'vendor' ? ['name','website','contact','email','phone'] : ['name','address','email','phone'];
            el.textContent = isOtherChoice(select)
                ? fields.map(key => form.elements[`${kind}_${key}`].value.trim()).filter(Boolean).join('\n') || 'Other'
                : select.value ? select.selectedOptions[0].textContent : '—';
        });
        reviewBody.replaceChildren();
        let group = null;
        rows().forEach(row => {
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
        if (!rows().length) { const tr = document.createElement('tr'); const td = cell('td','No catalog items requested.'); td.colSpan = 2; tr.append(td); reviewBody.append(tr); }
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
    const markDirty = () => { editGeneration++; dirty = true; save.classList.add('app-save-dirty'); updateBasicChoices(); updateReview(); updateMaterialTotals(); };
    const changed = event => {
        if (searchRoot?.contains(event.target) || searchResults?.contains(event.target)) return;
        markDirty();
    };
    form.addEventListener('input',changed);
    form.addEventListener('change',changed);
    mountQuantityNavigation(form, { resolveFields: input => input.matches('[data-quantity]') ? form.querySelectorAll('[data-quantity]') : input.matches('[data-search-quantity]') ? form.querySelectorAll('[data-search-quantity]') : [] });
    form.addEventListener('submit',event => {
        if ((event.submitter?.value || 'save') === 'save') return;
        if (event.submitter?.value === 'submit' && form.querySelector('[data-submit-request]').disabled) { event.preventDefault(); showTab('review'); return; }
        navigation.allowDeparture();
    });
    const showSearchMessage = message => { searchFeedback.hidden = false; searchFeedback.textContent = message; };
    const materialBody = form.querySelector('[data-request-material] tbody');
    const naturalNames = new Intl.Collator('en', {numeric:true, sensitivity:'base'});
    const regroupMaterial = () => {
        const catalog = rows().sort((a,b) => naturalNames.compare(a.dataset.group,b.dataset.group) || naturalNames.compare(a.dataset.name,b.dataset.name));
        const others = materialRows().filter(row => !row.hasAttribute('data-item-row'));
        materialBody.replaceChildren();
        let group = null;
        [...catalog, ...others].forEach(row => {
            const nextGroup = row.dataset.group || 'Other items';
            if (nextGroup !== group) {
                group = nextGroup;
                const heading = document.createElement('tr'); heading.className = 'group-row';
                const label = cell('th',group); label.colSpan = 5; label.scope = 'rowgroup'; heading.append(label); materialBody.append(heading);
            }
            materialBody.append(row);
        });
        if (!materialRows().length) {
            const row = document.createElement('tr'); const message = cell('td','No material selected.'); message.colSpan = 5;
            row.append(message); materialBody.append(row);
        }
    };
    const appendMaterial = ({id, name='', group='', quantity='', unit='', key}) => {
        const row = document.createElement('tr'); row.dataset.materialLine = '';
        if (id) { row.dataset.itemRow = ''; row.dataset.itemId = id; row.dataset.name = name; row.dataset.group = group; }
        const action = document.createElement('td'); const remove = cell('button','✕');
        remove.type = 'button'; remove.className = 'material-remove'; remove.dataset.materialRemove = '';
        remove.setAttribute('aria-label', `Remove ${id ? name : 'Other item'}`); remove.title = `Remove ${id ? name : 'Other item'}`; action.append(remove);
        const item = document.createElement('td'); item.className = 'app-grouped-item';
        if (id) item.textContent = name;
        else {
            const description = document.createElement('input'); description.name = `other_lines[${key}][description]`;
            description.value = name; description.maxLength = 500; description.dataset.otherDescription = '';
            description.setAttribute('aria-label','Other item description'); item.append(description);
        }
        const quantityCell = document.createElement('td'); quantityCell.className = 'table-numeric';
        const quantityInput = document.createElement('input'); quantityInput.name = id ? `lines[${id}]` : `other_lines[${key}][quantity]`;
        quantityInput.id = `purchase-quantity-${id || key}`; quantityInput.value = quantity; quantityInput.inputMode = 'numeric'; quantityInput.dataset.quantity = '';
        quantityInput.setAttribute('aria-label', id ? `Requested ${name}` : 'Other item quantity'); quantityCell.append(quantityInput);
        const unitCell = document.createElement('td'); unitCell.className = 'table-numeric';
        const unitInput = document.createElement('input'); unitInput.name = id ? `estimates[${id}]` : `other_lines[${key}][unit]`;
        unitInput.value = unit; unitInput.inputMode = 'decimal'; unitInput.dataset.estimatedUnit = '';
        unitInput.setAttribute('aria-label', id ? `Estimated unit price ${name}` : 'Other item estimated unit price'); unitCell.append(unitInput);
        const total = cell('td','—'); total.className = 'table-numeric'; total.dataset.materialTotal = '';
        row.append(action,item,quantityCell,unitCell,total); materialBody.append(row);
        return row;
    };
    form.querySelector('[data-add-other]')?.addEventListener('click', () => {
        if (saveBusy || form.dataset.editable !== 'true') return;
        if (materialRows().length >= 1000) { showSearchMessage('Use up to 1,000 material lines.'); return; }
        const row = appendMaterial({key:crypto.randomUUID()}); regroupMaterial(); markDirty(); row.querySelector('[data-other-description]').focus();
    });
    materialBody.addEventListener('click',event => {
        const button = event.target.closest('[data-material-remove]');
        if (!button || saveBusy || form.dataset.editable !== 'true') return;
        const row = button.closest('[data-material-line]'); const index = materialRows().indexOf(row);
        row.remove(); regroupMaterial(); markDirty();
        (materialRows()[Math.min(index,materialRows().length-1)]?.querySelector('input') || form.querySelector('[data-add-other]'))?.focus();
    });
    if (searchRoot) {
        let searchBusy = false;
        const catalogChoices = new Map();
        const updateAddButton = () => { addSelected.disabled = saveBusy || searchBusy || !searchResults.querySelector('[data-search-item]'); };
        const renderResults = data => {
            searchResults.replaceChildren(); searchFeedback.hidden = true;
            catalogChoices.clear();
            let group = null;
            data.rows.forEach(item => {
                catalogChoices.set(String(item.id), item);
                const label = `${item.name} · ${item.sku}`;
                const nextGroup = `${item.category} : ${item.collection}`;
                if (group !== nextGroup) {
                    group = nextGroup;
                    const row = document.createElement('tr'); row.className = 'group-row'; row.dataset.searchGroup = '';
                    const selectCell = document.createElement('th');
                    const check = document.createElement('input'); check.type = 'checkbox'; check.dataset.searchGroupCheck = '';
                    check.setAttribute('aria-label', `Select items in ${group}`); selectCell.append(check);
                    const heading = cell('th', group); heading.colSpan = 3; heading.scope = 'rowgroup';
                    row.append(selectCell, heading); searchResults.append(row);
                }
                const row = document.createElement('tr'); row.dataset.searchItem = item.id;
                const selectCell = document.createElement('td'); selectCell.className = 'app-grouped-item';
                const check = document.createElement('input'); check.type = 'checkbox'; check.name = 'selected[]'; check.value = item.id;
                check.dataset.searchItemCheck = ''; check.setAttribute('aria-label', `Select ${label}`); selectCell.append(check);
                const name = cell('td', item.name); name.className = 'app-grouped-item';
                const unit = cell('td', item.unit_price || '—'); unit.className = 'table-numeric';
                const quantityCell = document.createElement('td'); quantityCell.className = 'table-numeric';
                const input = document.createElement('input'); input.name = `add_quantities[${item.id}]`; input.maxLength = 30;
                input.dataset.searchQuantity = ''; input.setAttribute('aria-label', `Request ${label}`); quantityCell.append(input);
                row.append(selectCell, name, unit, quantityCell); searchResults.append(row);
            });
            if (!data.rows.length) {
                const row = document.createElement('tr'); const message = cell('td', 'No matching active items to add.'); message.colSpan = 4;
                row.append(message); searchResults.append(row);
            }
            updateAddButton();
        };
        searchResults.addEventListener('change', event => {
            if (!event.target.matches('[data-search-group-check]')) return;
            const checks = [];
            let row = event.target.closest('[data-search-group]').nextElementSibling;
            while (row && !row.hasAttribute('data-search-group')) {
                const check = row.querySelector('[data-search-item-check]'); if (check) checks.push(check);
                row = row.nextElementSibling;
            }
            if (event.target.checked || checks.every(check => check.checked)) checks.forEach(check => { check.checked = event.target.checked; });
        });
        searchRoot.addEventListener('inventory-search:before-search', event => {
            if (saveBusy) { event.preventDefault(); return; }
            if (pendingSelections()) { event.preventDefault(); showSearchMessage('Use Add selected or clear checked items and entered quantities before changing search results.'); }
        });
        searchRoot.addEventListener('inventory-search:before-reset', event => {
            if (saveBusy) { event.preventDefault(); return; }
            if (event.detail.reset && pendingSelections()) { event.preventDefault(); showSearchMessage('Use Add selected or clear checked items and entered quantities before changing search results.'); }
        });
        searchRoot.addEventListener('inventory-search:reset', () => { renderResults({rows:[]}); });
        searchRoot.addEventListener('inventory-search:busy', event => { searchBusy = event.detail.busy; updateAddButton(); });
        addSelected.addEventListener('click', () => {
            if (saveBusy || searchBusy || form.dataset.editable !== 'true') return;
            const ids = new Set(rows().map(row => row.dataset.itemId));
            const selected = [...searchResults.querySelectorAll('[data-search-item]')].filter(row => !ids.has(row.dataset.searchItem)
                && (row.querySelector('[data-search-item-check]').checked || row.querySelector('[data-search-quantity]').value.trim() !== ''));
            if (!selected.length) { showSearchMessage('Check an item or enter a quantity to add.'); return; }
            if (materialRows().length + selected.length > 1000) { showSearchMessage('Use up to 1,000 material lines.'); return; }
            let first;
            selected.forEach(result => {
                const item = catalogChoices.get(result.dataset.searchItem);
                const entered = result.querySelector('[data-search-quantity]').value.trim();
                const normalized = entered.replaceAll(',','');
                const quantity = /^(?:\d+|\d{1,3}(?:,\d{3})+)$/.test(entered) && validQuantity(normalized) ? String(Number(normalized)) : '';
                const row = appendMaterial({id:String(item.id),name:item.name,group:`${item.category} : ${item.collection}`,quantity,unit:item.unit_price});
                first ||= row; result.remove();
            });
            searchResults.querySelectorAll('[data-search-group]').forEach(group => {
                if (!group.nextElementSibling || group.nextElementSibling.hasAttribute('data-search-group')) group.remove();
            });
            if (!searchResults.querySelector('[data-search-item]')) renderResults({rows:[]});
            regroupMaterial(); markDirty(); updateAddButton(); searchFeedback.hidden = true;
            first?.querySelector('[data-quantity]').focus();
        });
        window.appInventorySearch.mount(searchRoot, {
            prepareUrl(url) {
                url.searchParams.set('live_items','1');
                rows().forEach(row => url.searchParams.append('excluded[]',row.dataset.itemId));
            },
            render: renderResults,
        });
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
