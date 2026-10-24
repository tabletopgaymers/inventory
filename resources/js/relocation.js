import { mountTabs } from './workflow/tabs.js';
import { mountQuantityNavigation } from './workflow/quantity-navigation.js';
import { mountUnsavedChanges } from './workflow/unsaved-changes.js';
import '../../public/inventory-search-controls.js';
(() => {
    const form = document.getElementById('relocation-form');
    if (!form) return;
    const tabs = [...form.querySelectorAll('[role="tab"]')];
    const save = document.querySelector('[data-relocation-save]');
    const feedback = document.querySelector('[data-relocation-feedback]');
    let dirty = false;
    const dirtySections = new Set();
    let busy = false;
    let refreshReview = () => {};
    let refreshShipment = () => {};
    let refreshBalances = () => {};
    let refreshFulfillment = () => {};
    let refreshWorkflow = () => {};
    let refreshReconcile = () => {};
    let refreshConfirmation = () => {};
    const tabUI = mountTabs(form, { initialKey: form.dataset.initialStep, removeQueryParams: ['step'], onChange: (key, tab) => {
        form.querySelector('[data-material-notifications]').hidden = !['material', 'fulfillment', 'reconcile'].includes(tab.dataset.workflowTab);
        refreshReview();
        refreshFulfillment();
        refreshWorkflow();
        refreshShipment();
        refreshReconcile();
        refreshConfirmation();
        if (['material', 'review', 'ship', 'confirmation'].includes(tab.dataset.workflowTab)) refreshBalances();
    } });
    const showTab = tab => tabUI.activate(tab?.dataset.workflowTab, { allowDisabled: true });
    form.querySelector('[data-material-next]').addEventListener('click', () => {
        if (!busy) showTab(tabs.find(tab => tab.dataset.workflowTab === 'review'));
    });
    form.querySelector('[data-reconcile-next]').addEventListener('click', () => {
        if (!busy) showTab(tabs.find(tab => tab.dataset.workflowTab === 'confirmation'));
    });
    const controls = () => [...form.querySelectorAll('input:not([type="hidden"]), select, textarea')];
    const markDirty = section => {
        dirtySections.add(section || (activeTab() === 'reconcile' ? 'reconciliation' : activeTab() === 'tracking' ? 'tracking' : form.dataset.status === 'Accepted' ? (activeTab() === 'basic' ? 'basic' : 'fulfillment') : 'request'));
        dirty = true; save.classList.add('app-save-dirty'); refreshReview(); refreshShipment(); refreshReconcile(); refreshConfirmation();
    };
    const materialRows = form.querySelector('[data-material-lines]');
    const results = form.querySelector('[data-material-results]');
    const searchFeedback = form.querySelector('[data-material-search-feedback]');
    const searchRoot = form.querySelector('[data-inventory-search]');
    const activeTab = () => form.querySelector('[aria-selected="true"]').dataset.workflowTab;
    const canEditTracking = () => form.dataset.manager === 'true' && ['Accepted', 'Fulfilled', 'Received', 'Completed'].includes(form.dataset.status);
    const trackingBody = form.querySelector('[data-tracking-lines]');
    const trackingUrl = (carrier, number) => {
        if (!number) return null;
        const encoded = encodeURIComponent(number);
        if (carrier === 'USPS') return `https://tools.usps.com/go/TrackConfirmAction?tLabels=${encoded}`;
        if (carrier === 'UPS') return `https://www.ups.com/track?loc=en_US&tracknum=${encoded}`;
        if (carrier === 'FedEx') return `https://www.fedex.com/apps/fedextrack/?action=track&tracknumbers=${encoded}&cntry_code=us`;
        return null;
    };
    const showSavedTracking = values => {
        form.querySelectorAll('[data-tracking-saved]').forEach(saved => {
        saved.replaceChildren();
        saved.closest('table').hidden = !values.length;
        saved.closest('.app-content').querySelector('[data-tracking-empty]').hidden = !!values.length;
        values.forEach((line, index) => {
            const row = form.querySelector('[data-tracking-saved-row]').content.firstElementChild.cloneNode(true);
            row.querySelector('[data-saved-package-name]').textContent = line.name || `Package ${index + 1}`;
            row.querySelector('[data-saved-shipper]').textContent = line.carrier === 'USPS' ? 'US Postal' : line.carrier;
            const tracker = row.querySelector('[data-saved-tracker]');
            const url = trackingUrl(line.carrier, line.number);
            if (url) {
                const link = document.createElement('a');
                link.href = url;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.textContent = line.number;
                tracker.append(link);
            } else {
                tracker.textContent = line.number || 'No tracking number';
            }
            saved.append(row);
        });
        });
    };
    let trackingCounter = 0;
    const addTrackingRow = (values = {}) => {
        const row = form.querySelector('[data-tracking-row]').content.firstElementChild.cloneNode(true);
        const id = trackingCounter++;
        row.dataset.trackingItem = id;
        const name = row.querySelector('[data-tracking-name]');
        name.name = `tracking[${id}][name]`; name.id = `relocation-package-${id}`; name.value = values.name || `Package ${id + 1}`;
        const carrier = row.querySelector('[data-tracking-carrier]');
        carrier.name = `tracking[${id}][carrier]`; carrier.id = `relocation-carrier-${id}`; carrier.value = values.carrier || '';
        const number = row.querySelector('[data-tracking-number]');
        number.name = `tracking[${id}][number]`; number.id = `relocation-tracker-${id}`; number.value = values.number || '';
        row.querySelector('[data-tracking-remove]').addEventListener('click', () => {
            if (busy || !canEditTracking()) return;
            row.remove();
            markDirty();
        });
        trackingBody.append(row);
        return row;
    };
    const savedTracking = JSON.parse(form.querySelector('[data-tracking-values]').textContent);
    savedTracking.forEach(addTrackingRow);
    if (!savedTracking.length) addTrackingRow();
    showSavedTracking(savedTracking);
    form.querySelector('[data-tracking-add]').addEventListener('click', () => {
        if (busy || !canEditTracking()) return;
        addTrackingRow().querySelector('input').focus(); markDirty();
    });
    const canAddMaterial = () => (activeTab() === 'material' && form.dataset.editable === 'true')
        || (activeTab() === 'fulfillment' && form.dataset.status === 'Accepted' && form.dataset.manager === 'true')
        || (activeTab() === 'reconcile' && form.dataset.status === 'Received' && form.dataset.manager === 'true');
    const canRemoveMaterial = row => (activeTab() === 'material' && form.dataset.editable === 'true')
        || (activeTab() === 'fulfillment' && form.dataset.status === 'Accepted' && form.dataset.manager === 'true' && row.dataset.addedStage === 'fulfillment')
        || (activeTab() === 'reconcile' && form.dataset.status === 'Received' && form.dataset.manager === 'true' && row.dataset.addedStage === 'reconciliation');
    const requestedRows = () => [...materialRows.querySelectorAll('[data-material-item]')];
    const fieldsInColumn = (input, rows) => {
        const cell = input.closest('td');
        if (!cell) return [];
        const column = cell.cellIndex;
        return rows.map(row => row.cells[column]?.querySelector('input')).filter(Boolean);
    };
    mountQuantityNavigation(materialRows, { isBusy: () => busy, resolveFields: input => fieldsInColumn(input, requestedRows()) });
    mountQuantityNavigation(results, { isBusy: () => busy, resolveFields: input => fieldsInColumn(input, [...results.querySelectorAll('[data-search-item]')]) });
    const regroupMaterial = () => {
        materialRows.querySelectorAll('[data-material-group]').forEach(group => group.remove());
        const rows = requestedRows().sort((a, b) => a.dataset.itemGroup.localeCompare(b.dataset.itemGroup)
            || a.dataset.itemName.localeCompare(b.dataset.itemName, undefined, { numeric: true }) || a.dataset.itemSku.localeCompare(b.dataset.itemSku));
        let group;
        rows.forEach(row => {
            if (row.dataset.itemGroup !== group) {
                group = row.dataset.itemGroup;
                const header = form.querySelector('[data-material-group-template]').content.firstElementChild.cloneNode(true);
                header.querySelector('th[colspan]').textContent = group;
                materialRows.append(header);
            }
            materialRows.append(row);
        });
    };
    const locationControls = ['source_location_id', 'destination_location_id'].map(name => form.elements.namedItem(name));
    const refreshStockContext = () => {
        results.querySelectorAll('[data-stock-heading]').forEach(heading => {
            const field = form.elements.namedItem(heading.dataset.stockHeading);
            heading.textContent = field.value ? field.selectedOptions[0].textContent : heading.dataset.stockHeading === 'source_location_id' ? 'Source' : 'Destination';
        });
        results.querySelectorAll('[data-stock-count]').forEach(cell => { cell.textContent = 'Awaiting refresh'; });
        requestedRows().forEach(row => { row.dataset.sourceBalance = ''; row.dataset.destinationBalance = ''; });
        refreshReview();
        refreshBalances();
    };
    locationControls.forEach(control => control.addEventListener('change', refreshStockContext));
    results.addEventListener('change', event => {
        if (!event.target.matches('[data-search-group-check]') || busy || !canAddMaterial()) return;
        const checks = [];
        let row = event.target.closest('[data-search-group]').nextElementSibling;
        while (row && !row.hasAttribute('data-search-group')) {
            const check = row.querySelector('[data-search-item-check]');
            if (check) checks.push(check);
            row = row.nextElementSibling;
        }
        if (event.target.checked || checks.every(check => check.checked)) {
            checks.forEach(check => { check.checked = event.target.checked; });
        }
    });
    const pendingSelections = () => !!results.querySelector('[data-search-item-check]:checked')
        || [...results.querySelectorAll('[data-search-quantity]')].some(input => input.value.trim() !== '');
    const showSearchMessage = message => {
        searchFeedback.hidden = false; searchFeedback.textContent = message;
    };
    const updateMaterialChoices = () => {
        form.querySelector('[data-material-empty]').hidden = requestedRows().length > 0;
        form.querySelector('[data-material-add-selected]').disabled = busy || !canAddMaterial() || !results.querySelector('[data-search-item]');
    };
    const wireMaterialRow = row => row.querySelector('[data-material-remove]').addEventListener('click', () => {
        if (busy || !canRemoveMaterial(row)) return;
        row.remove(); regroupMaterial(); updateMaterialChoices(); refreshFulfillment(); markDirty();
    });
    requestedRows().forEach(wireMaterialRow);
    searchRoot.addEventListener('inventory-search:before-search', event => {
        if (busy || !canAddMaterial() || form.dataset.saved !== 'true') { event.preventDefault(); return; }
        if (pendingSelections()) { event.preventDefault(); showSearchMessage('Use Add selected or clear checked items and entered quantities before changing search results.'); }
    });
    searchRoot.addEventListener('inventory-search:before-reset', event => {
        if (busy || !canAddMaterial()) { event.preventDefault(); return; }
        if (event.detail.reset && pendingSelections()) { event.preventDefault(); showSearchMessage('Use Add selected or clear checked items and entered quantities before changing search results.'); }
    });
    searchRoot.addEventListener('inventory-search:reset', () => { results.replaceChildren(); searchFeedback.hidden = true; updateMaterialChoices(); });
    searchRoot.addEventListener('inventory-search:busy', event => {
        busy = event.detail.busy;
        locationControls.forEach(control => { control.disabled = busy; });
        [...form.querySelectorAll('button'), save].forEach(button => { button.disabled = busy; });
        if (!busy) { updateMaterialChoices(); lockSubmittedForm(); refreshWorkflow(); refreshReview(); }
    });
    window.appInventorySearch.mount(searchRoot, {
        prepareUrl(url) {
            for (const name of ['source_location_id', 'destination_location_id']) url.searchParams.set(name, form.elements.namedItem(name).value);
            requestedRows().forEach(row => url.searchParams.append('excluded[]', row.dataset.materialItem));
        },
        render(data) {
            results.innerHTML = data.html;
            searchFeedback.hidden = true;
        }
    });
    form.querySelector('[data-material-add-selected]').addEventListener('click', event => {
        if (!event.target.closest('[data-material-add-selected]') || busy || !canAddMaterial()) return;
        const ids = new Set(requestedRows().map(row => row.dataset.materialItem));
        let added = false;
        results.querySelectorAll('[data-search-item]').forEach(result => {
            const value = result.querySelector('[data-search-quantity]').value.trim();
            if ((value === '' && !result.querySelector('[data-search-item-check]').checked) || ids.has(result.dataset.searchItem)) return;
            const row = form.querySelector('[data-material-template]').content.firstElementChild.cloneNode(true);
            const id = result.dataset.searchItem;
            row.dataset.materialItem = id;
            const addingFulfillment = activeTab() === 'fulfillment';
            const addingReconciliation = activeTab() === 'reconcile';
            row.dataset.addedStage = addingReconciliation ? 'reconciliation' : addingFulfillment ? 'fulfillment' : 'requested';
            row.dataset.itemGroup = result.dataset.itemGroup;
            row.dataset.itemName = result.dataset.itemName;
            row.dataset.itemSku = result.dataset.itemSku;
            row.dataset.sourceBalance = result.dataset.sourceBalance;
            row.dataset.destinationBalance = result.dataset.destinationBalance;
            row.querySelector('[data-material-label]').textContent = result.dataset.itemName;
            row.querySelector('[data-material-remove]').setAttribute('aria-label', 'Remove ' + result.dataset.itemLabel);
            row.querySelector('[data-material-remove]').title = 'Remove ' + result.dataset.itemLabel;
            row.querySelector('[data-material-quantity-label]').textContent = 'Requested quantity for ' + result.dataset.itemLabel;
            const input = row.querySelector('input');
            input.name = `lines[${id}]`; input.id = `relocation-quantity-${id}`;
            row.querySelector('[data-material-fulfilled]').setAttribute('aria-label', 'Fulfilled quantity for ' + result.dataset.itemLabel);
            row.querySelector('[data-fulfilled-label]').textContent = 'Fulfilled quantity for ' + result.dataset.itemLabel;
            row.querySelector('[data-material-received]').setAttribute('aria-label', 'Received quantity for ' + result.dataset.itemLabel);
            const quantity = Number.isFinite(Number(value.replaceAll(',', ''))) ? value : '';
            input.value = addingFulfillment || addingReconciliation ? '0' : quantity;
            if (addingFulfillment) row.querySelector('[data-material-fulfilled]').value = quantity;
            if (addingReconciliation) {
                row.querySelector('[data-material-fulfilled]').value = '0';
                row.querySelector('[data-material-received]').value = quantity;
            }
            materialRows.append(row); wireMaterialRow(row); ids.add(id); result.remove(); added = true;
        });
        results.querySelectorAll('[data-search-group]').forEach(group => {
            if (!group.nextElementSibling || group.nextElementSibling.hasAttribute('data-search-group')) group.remove();
        });
        if (added) { regroupMaterial(); updateMaterialChoices(); refreshFulfillment(); markDirty(); searchFeedback.hidden = true; }
    });
    updateMaterialChoices();
    const submit = form.querySelector('[data-relocation-submit]');
    const reviewErrors = form.querySelector('[data-review-errors]');
    const numberText = value => {
        const span = document.createElement('span');
        span.textContent = value.toLocaleString('en-US');
        span.classList.toggle('table-negative', value < 0);
        return span;
    };
    let balanceGeneration = 0;
    refreshBalances = async () => {
        const generation = ++balanceGeneration;
        if (locationControls.some(control => !control.value) || !requestedRows().length) return;
        const url = new URL(form.getAttribute('action') + '/balances', location.origin);
        locationControls.forEach(control => url.searchParams.set(control.name, control.value));
        requestedRows().forEach(row => url.searchParams.append('items[]', row.dataset.materialItem));
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Balance refresh failed');
            const data = await response.json();
            if (generation !== balanceGeneration) return;
            requestedRows().forEach(row => {
                const values = data.balances[row.dataset.materialItem];
                row.dataset.sourceBalance = values?.source ?? '';
                row.dataset.destinationBalance = values?.destination ?? '';
            });
        } catch {
            if (generation !== balanceGeneration) return;
            requestedRows().forEach(row => { row.dataset.sourceBalance = ''; row.dataset.destinationBalance = ''; });
        }
        refreshReview();
        refreshShipment();
        refreshConfirmation();
    };
    refreshReview = () => {
        const errors = [];
        form.querySelectorAll('[data-review-field]').forEach(output => {
            const field = form.elements.namedItem(output.dataset.reviewField);
            output.textContent = field.tagName === 'SELECT' ? (field.value ? field.selectedOptions[0].textContent : 'Not selected') : field.value || '—';
        });
        form.querySelectorAll('[data-review-location], [data-material-location]').forEach(heading => {
            const name = heading.dataset.reviewLocation || heading.dataset.materialLocation;
            const field = form.elements.namedItem(name);
            heading.textContent = field.value ? field.selectedOptions[0].textContent : name === 'source_location_id' ? 'Source' : 'Destination';
        });
        if (!form.elements.namedItem('title').value.trim()) errors.push('Enter a title in Basic info.');
        if (locationControls.some(control => !control.value)) errors.push('Choose Source and Destination in Basic info.');
        else if (locationControls[0].value === locationControls[1].value) errors.push('Choose a Destination different from Source.');
        const rows = requestedRows();
        if (!rows.length) errors.push('Add at least one material item before submitting.');
        const body = form.querySelector('[data-review-material]');
        body.replaceChildren();
        let group;
        rows.forEach(source => {
            if (source.dataset.itemGroup !== group) {
                group = source.dataset.itemGroup;
                const header = form.querySelector('[data-review-group]').content.firstElementChild.cloneNode(true);
                header.querySelector('th').textContent = group;
                body.append(header);
            }
            const value = source.querySelector('input[name^="lines["]').value.trim();
            const valid = /^-?(?:\d{1,10}|\d{1,3}(?:,\d{3}){1,3})$/.test(value) && Math.abs(Number(value.replaceAll(',', ''))) <= 1000000000;
            const quantityError = !valid && ['New', 'Draft'].includes(form.dataset.status);
            source.classList.toggle('table-row-error', quantityError);
            if (!valid) errors.push(`${source.dataset.itemName}: enter a nonblank whole-number requested quantity.`);
            const row = form.querySelector('[data-review-row]').content.firstElementChild.cloneNode(true);
            row.classList.toggle('table-row-error', quantityError);
            row.children[0].textContent = source.dataset.itemName;
            const quantity = valid ? Number(value.replaceAll(',', '')) : null;
            [[1, source.dataset.sourceBalance, -1], [3, source.dataset.destinationBalance, 1]].forEach(([index, balance, direction]) => {
                const materialCell = source.querySelector(`[data-material-projection="${direction === -1 ? 'source' : 'destination'}"]`);
                materialCell.replaceChildren();
                if (balance === '' || balance === undefined || quantity === null) row.children[index].textContent = '—';
                else row.children[index].append(numberText(Number(balance) + direction * quantity));
                if (value === '' && balance !== '' && balance !== undefined) materialCell.append(numberText(Number(balance)));
                else materialCell.append(row.children[index].cloneNode(true).firstChild);
            });
            if (valid) row.children[2].append(numberText(quantity));
            else row.children[2].textContent = value || 'Blank';
            body.append(row);
        });
        if (pendingSelections()) errors.push('Use Add selected or clear checked items and pending search quantities before submitting.');
        if (form.dataset.saved !== 'true') errors.push('Save Basic info to create a Draft before submitting.');
        reviewErrors.replaceChildren();
        if (form.dataset.status === 'New' || form.dataset.status === 'Draft') {
            errors.forEach(message => { const line = document.createElement('p'); line.textContent = message; reviewErrors.append(line); });
        }
        reviewErrors.hidden = !reviewErrors.childElementCount || form.querySelector('[aria-selected="true"]').dataset.workflowTab !== 'review';
        submit.disabled = busy || form.dataset.status !== 'Draft' || form.dataset.editable !== 'true' || form.dataset.canSubmit !== 'true' || errors.length > 0;
    };
    refreshShipment = () => {
        const previewShipment = form.dataset.status === 'Accepted';
        form.querySelectorAll('[data-ship-field]').forEach(output => {
            const field = form.elements.namedItem(output.dataset.shipField);
            output.textContent = field.tagName === 'SELECT' ? (field.value ? field.selectedOptions[0].textContent : 'Not selected') : field.value || '—';
        });
        form.querySelectorAll('[data-ship-location]').forEach(heading => {
            const field = form.elements.namedItem(heading.dataset.shipLocation);
            heading.textContent = field.value ? field.selectedOptions[0].textContent : heading.dataset.shipLocation === 'source_location_id' ? 'Source' : 'Destination';
        });
        const errors = [];
        const shippingDate = form.querySelector('[data-shipping-date]');
        const dateDisplay = form.querySelector('[data-shipping-date-display]');
        const showDateText = ['Fulfilled', 'Received', 'Completed', 'Cancelled'].includes(form.dataset.status);
        shippingDate.hidden = showDateText;
        dateDisplay.hidden = !showDateText;
        const now = new Date();
        const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
        form.querySelector('[data-shipping-label-text]').textContent = shippingDate.value && today > shippingDate.value ? 'Shipped' : 'Shipping';
        dateDisplay.textContent = shippingDate.value ? new Date(shippingDate.value + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
        if (form.dataset.status === 'Accepted' && (!shippingDate.value || !shippingDate.validity.valid)) errors.push('Choose a valid shipping date.');
        const rows = requestedRows();
        if (!rows.length) errors.push('Add at least one material item before shipping.');
        const body = form.querySelector('[data-ship-material]');
        body.replaceChildren();
        let group;
        rows.forEach(source => {
            if (source.dataset.itemGroup !== group) {
                group = source.dataset.itemGroup;
                const header = form.querySelector('[data-ship-group]').content.firstElementChild.cloneNode(true);
                header.querySelector('th').textContent = group;
                body.append(header);
            }
            const requested = source.querySelector('input[name^="lines["]').value.trim();
            const fulfilled = source.querySelector('[data-material-fulfilled]').value.trim();
            const valid = /^(?:\d{1,10}|\d{1,3}(?:,\d{3}){1,3})$/.test(fulfilled) && Number(fulfilled.replaceAll(',', '')) <= 1000000000;
            if (!valid) errors.push(`${source.dataset.itemName}: enter a nonblank whole-number fulfilled quantity.`);
            const row = form.querySelector('[data-ship-row]').content.firstElementChild.cloneNode(true);
            row.classList.toggle('table-row-error', !valid && form.dataset.status === 'Accepted');
            row.children[0].textContent = source.dataset.itemName;
            const requestedValid = /^-?(?:\d{1,10}|\d{1,3}(?:,\d{3}){1,3})$/.test(requested);
            if (requestedValid) row.children[2].append(numberText(Number(requested.replaceAll(',', ''))));
            else row.children[2].textContent = requested || 'Blank';
            const quantity = valid ? Number(fulfilled.replaceAll(',', '')) : null;
            if (valid) row.children[4].append(numberText(quantity));
            else row.children[4].textContent = fulfilled || 'Blank';
            if (valid && requestedValid) {
                const difference = quantity - Number(requested.replaceAll(',', ''));
                if (difference !== 0) {
                    const value = document.createElement('span');
                    value.className = difference > 0 ? 'table-variance-positive' : 'table-variance-negative';
                    value.textContent = `[${difference > 0 ? '+' : '-'}${Math.abs(difference).toLocaleString('en-US')}]`;
                    row.children[3].append(value);
                }
            }
            const sourceAdjustment = previewShipment ? (quantity === null ? null : -quantity) : 0;
            const destinationAdjustment = previewShipment || ['Fulfilled', 'Received'].includes(form.dataset.status) ? quantity : 0;
            [[1, source.dataset.sourceBalance, sourceAdjustment], [5, source.dataset.destinationBalance, destinationAdjustment]].forEach(([index, balance, adjustment]) => {
                if (balance === '' || balance === undefined || adjustment === null) row.children[index].textContent = '—';
                else row.children[index].append(numberText(Number(balance) + adjustment));
            });
            body.append(row);
        });
        if (dirtySections.has('fulfillment')) errors.push('Save Fulfillment progress before shipping.');
        const errorBox = form.querySelector('[data-ship-errors]');
        errorBox.replaceChildren();
        if (form.dataset.status === 'Accepted') errors.forEach(message => { const line = document.createElement('p'); line.textContent = message; errorBox.append(line); });
        errorBox.hidden = !errorBox.childElementCount || activeTab() !== 'ship';
        form.querySelector('[data-relocation-ship]').disabled = busy || form.dataset.manager !== 'true' || form.dataset.status !== 'Accepted' || errors.length > 0;
    };
    refreshReconcile = () => {
        const errors = [];
        let blankCount = 0;
        const rows = requestedRows();
        if (!rows.length) errors.push('Add at least one material item before completing.');
        rows.forEach(row => {
            const value = row.querySelector('[data-material-received]').value.trim();
            const valid = /^(?:\d{1,10}|\d{1,3}(?:,\d{3}){1,3})$/.test(value) && Number(value.replaceAll(',', '')) <= 1000000000;
            if (value === '') blankCount++;
            else if (!valid) errors.push(`${row.dataset.itemName}: enter a whole-number received quantity from 0 to 1,000,000,000.`);
            if (activeTab() === 'reconcile') row.classList.toggle('table-row-error', value !== '' && !valid && form.dataset.status === 'Received');
        });
        const progress = form.querySelector('[data-reconcile-progress]');
        progress.hidden = activeTab() !== 'reconcile' || form.dataset.status !== 'Received' || blankCount === 0;
        progress.textContent = `${blankCount} ${blankCount === 1 ? 'item still needs' : 'items still need'} a received quantity. Enter 0 for items not received. You can save progress while you work.`;
        if (pendingSelections()) errors.push('Use Add selected or clear checked items and pending search quantities before completing.');
        const box = form.querySelector('[data-reconcile-errors]');
        box.replaceChildren();
        if (form.dataset.status === 'Received') errors.forEach(message => { const line = document.createElement('p'); line.textContent = message; box.append(line); });
        box.hidden = activeTab() !== 'reconcile' || !box.childElementCount;
        form.querySelector('[data-relocation-complete]').disabled = busy || form.dataset.manager !== 'true' || form.dataset.status !== 'Received' || blankCount > 0 || errors.length > 0;
    };
    refreshConfirmation = () => {
        form.querySelectorAll('[data-confirmation-field]').forEach(output => {
            const field = form.elements.namedItem(output.dataset.confirmationField);
            output.textContent = field.tagName === 'SELECT' ? (field.value ? field.selectedOptions[0].textContent : 'Not selected') : field.value || '—';
        });
        form.querySelectorAll('[data-confirmation-location]').forEach(heading => {
            const field = form.elements.namedItem(heading.dataset.confirmationLocation);
            heading.textContent = field.value ? field.selectedOptions[0].textContent : heading.dataset.confirmationLocation === 'source_location_id' ? 'Source' : 'Destination';
        });
        form.querySelector('[data-confirmation-requested-date]').textContent = form.querySelector('[data-ship-requested-date]').textContent;
        form.querySelector('[data-confirmation-fulfiller]').textContent = form.querySelector('[data-ship-fulfiller]').textContent;
        form.querySelector('[data-confirmation-shipping-label]').textContent = form.querySelector('[data-shipping-label-text]').textContent;
        form.querySelector('[data-confirmation-shipping-date]').textContent = form.querySelector('[data-shipping-date-display]').textContent;
        const errors = [];
        const rows = requestedRows();
        if (!rows.length) errors.push('Add at least one material item before completing.');
        const body = form.querySelector('[data-confirmation-material]');
        body.replaceChildren();
        let group;
        const wholeQuantity = value => /^(?:\d{1,10}|\d{1,3}(?:,\d{3}){1,3})$/.test(value) && Number(value.replaceAll(',', '')) <= 1000000000;
        rows.forEach(source => {
            if (source.dataset.itemGroup !== group) {
                group = source.dataset.itemGroup;
                const header = form.querySelector('[data-confirmation-group]').content.firstElementChild.cloneNode(true);
                header.querySelector('th').textContent = group;
                body.append(header);
            }
            const requested = source.querySelector('input[name^="lines["]').value.trim();
            const fulfilled = source.querySelector('[data-material-fulfilled]').value.trim();
            const received = source.querySelector('[data-material-received]').value.trim();
            const valid = wholeQuantity(received);
            const row = form.querySelector('[data-confirmation-row]').content.firstElementChild.cloneNode(true);
            row.children[0].textContent = source.dataset.itemName;
            row.classList.toggle('table-row-error', !valid && form.dataset.status === 'Received');
            if (!valid) errors.push(`${source.dataset.itemName}: enter a nonblank whole-number received quantity.`);
            if (/^-?(?:\d{1,10}|\d{1,3}(?:,\d{3}){1,3})$/.test(requested)) row.children[2].append(numberText(Number(requested.replaceAll(',', ''))));
            else row.children[2].textContent = requested || 'Blank';
            if (wholeQuantity(fulfilled)) row.children[3].append(numberText(Number(fulfilled.replaceAll(',', ''))));
            else row.children[3].textContent = fulfilled || 'Blank';
            const quantity = valid ? Number(received.replaceAll(',', '')) : null;
            if (valid) row.children[5].append(numberText(quantity));
            else row.children[5].textContent = received || 'Blank';
            if (valid && wholeQuantity(fulfilled)) {
                const difference = quantity - Number(fulfilled.replaceAll(',', ''));
                if (difference !== 0) {
                    const variance = document.createElement('span');
                    variance.className = difference > 0 ? 'table-variance-positive' : 'table-variance-negative';
                    variance.textContent = `[${difference > 0 ? '+' : '-'}${Math.abs(difference).toLocaleString('en-US')}]`;
                    row.children[4].append(variance);
                }
            }
            const destinationAdjustment = form.dataset.status === 'Completed' ? 0 : quantity;
            [[1, source.dataset.sourceBalance, 0], [6, source.dataset.destinationBalance, destinationAdjustment]].forEach(([index, balance, adjustment]) => {
                if (balance === '' || balance === undefined || adjustment === null) row.children[index].textContent = '—';
                else row.children[index].append(numberText(Number(balance) + adjustment));
            });
            body.append(row);
        });
        if (pendingSelections()) errors.push('Use Add selected or clear checked items and pending search quantities before completing.');
        const box = form.querySelector('[data-confirmation-errors]');
        box.replaceChildren();
        if (form.dataset.status === 'Received') errors.forEach(message => { const line = document.createElement('p'); line.textContent = message; box.append(line); });
        box.hidden = activeTab() !== 'confirmation' || !box.childElementCount;
        const available = ['Received', 'Completed'].includes(form.dataset.status);
        form.querySelector('[data-confirmation-content]').hidden = !available;
        form.querySelector('[data-confirmation-unavailable]').hidden = available;
        form.querySelector('[data-relocation-complete]').disabled = busy || form.dataset.manager !== 'true' || form.dataset.status !== 'Received' || errors.length > 0;
    };
    const lockSubmittedForm = () => {
        if (form.dataset.editable === 'true') return;
        form.querySelectorAll('fieldset').forEach(group => { group.disabled = true; });
        requestedRows().forEach(row => {
            row.querySelector('input[name^="lines["]').readOnly = true;
            row.querySelector('[data-material-remove]').disabled = true;
        });
        form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = true; });
        save.disabled = true;
        updateMaterialChoices();
    };
    refreshReview();
    if (['material', 'review', 'ship', 'confirmation'].includes(activeTab())) refreshBalances();
    const sharedTable = form.querySelector('[data-material-table]');
    const fulfillmentBody = materialRows;
    let fulfilledValues = Object.fromEntries(requestedRows().map(row => [row.dataset.materialItem, row.querySelector('[data-material-fulfilled]').value]));
    const materialTablePolicy = () => {
        const tab = ['fulfillment', 'reconcile'].includes(activeTab()) ? activeTab() : 'material';
        return {
            tab,
            requested: tab === 'material' && form.dataset.editable === 'true',
            fulfilled: tab === 'fulfillment' && form.dataset.status === 'Accepted' && form.dataset.manager === 'true',
            received: tab === 'reconcile' && form.dataset.status === 'Received' && form.dataset.manager === 'true',
            remove: (tab === 'material' && form.dataset.editable === 'true') || (tab === 'fulfillment' && form.dataset.status === 'Accepted' && form.dataset.manager === 'true') || (tab === 'reconcile' && form.dataset.status === 'Received' && form.dataset.manager === 'true'),
        };
    };
    refreshFulfillment = () => {
        const policy = materialTablePolicy();
        const host = form.querySelector(`[data-${policy.tab}-table-host]`);
        if (sharedTable.parentElement !== host) host.append(sharedTable);
        const searchPanel = form.querySelector('[data-material-search-panel]');
        const searchHost = form.querySelector(`[data-${policy.tab}-search-host]`);
        if (searchPanel.parentElement !== searchHost) searchHost.append(searchPanel);
        form.querySelector('[data-material-search-fields]').disabled = busy || !canAddMaterial();
        const title = policy.tab === 'reconcile' ? 'Material reconciliation' : policy.tab === 'fulfillment' ? 'Material fulfillment' : 'Requested material';
        results.querySelectorAll('[data-search-quantity-heading]').forEach(heading => { heading.textContent = policy.tab === 'reconcile' ? 'Received' : policy.tab === 'fulfillment' ? 'Fulfilled' : 'Request'; });
        results.querySelectorAll('[data-search-item]').forEach(row => { row.querySelector('[data-search-quantity]').setAttribute('aria-label', `${policy.tab === 'reconcile' ? 'Received' : policy.tab === 'fulfillment' ? 'Fulfilled' : 'Request'} ${row.dataset.itemLabel}`); });
        sharedTable.querySelector('[data-material-table-title]').textContent = title;
        const printButton = sharedTable.querySelector('[data-fulfillment-print]');
        printButton.hidden = policy.tab !== 'fulfillment';
        printButton.disabled = busy;
        const reconcileNext = sharedTable.querySelector('[data-reconcile-next]');
        reconcileNext.hidden = policy.tab !== 'reconcile';
        reconcileNext.disabled = busy;
        const next = sharedTable.querySelector('[data-material-next]');
        next.hidden = policy.tab !== 'material';
        next.disabled = busy;
        sharedTable.querySelector('[role="region"]').setAttribute('aria-label', title);
        sharedTable.querySelectorAll('[data-material-only]').forEach(cell => { cell.hidden = policy.tab !== 'material'; });
        sharedTable.querySelectorAll('[data-fulfillment-only]').forEach(cell => { cell.hidden = policy.tab === 'material'; });
        requestedRows().forEach(row => {
            const id = row.dataset.materialItem;
            row.querySelector('input[name^="lines["]').readOnly = !policy.requested;
            const input = row.querySelector('[data-material-fulfilled]');
            input.dataset.fulfillmentQuantity = '';
            input.dataset.itemId = id;
            input.name = `fulfillment[${id}]`;
            input.id = `relocation-fulfilled-${id}`;
            input.readOnly = !policy.fulfilled;
            input.disabled = policy.tab === 'material';
            const received = row.querySelector('[data-material-received]');
            received.name = `reconciliation[${id}]`;
            received.id = `relocation-received-${id}`;
            received.readOnly = !policy.received;
            received.disabled = policy.tab !== 'reconcile';
            const remove = row.querySelector('[data-material-remove]');
            const stageRemovable = policy.tab === 'material' || row.dataset.addedStage === (policy.tab === 'reconcile' ? 'reconciliation' : 'fulfillment');
            remove.hidden = !stageRemovable;
            remove.disabled = busy || !policy.remove || !stageRemovable;
        });
        refreshReconcile();
    };
    refreshWorkflow = () => {
        const manager = form.dataset.manager === 'true';
        const accepted = form.dataset.status === 'Accepted';
        const basicEditable = form.dataset.editable === 'true';
        form.querySelector('[data-basic-fields]').disabled = busy;
        ['title', 'details'].forEach(name => { form.elements.namedItem(name).readOnly = !(basicEditable || (manager && accepted)); });
        locationControls.forEach(control => { control.disabled = busy || !basicEditable; });
        form.querySelector('[data-basic-next]').disabled = busy || !basicEditable;
        const shippingDate = form.querySelector('[data-shipping-date]');
        shippingDate.disabled = busy;
        shippingDate.readOnly = !manager || !accepted;
        const available = !['New', 'Draft', 'Requested'].includes(form.dataset.status);
        form.querySelector('[data-fulfillment-accept-panel]').hidden = available;
        form.querySelector('[data-fulfillment-content]').hidden = !available;
        const unavailable = form.querySelector('[data-fulfillment-unavailable]');
        unavailable.hidden = available;
        unavailable.textContent = form.dataset.status === 'Requested' ? 'Must Accept First' : 'Not available';
        form.querySelector('[data-relocation-accept]').disabled = busy || !manager || form.dataset.status !== 'Requested';
        const reconcileAvailable = ['Received', 'Completed'].includes(form.dataset.status);
        form.querySelector('[data-reconcile-receive-panel]').hidden = reconcileAvailable;
        form.querySelector('[data-reconcile-content]').hidden = !reconcileAvailable;
        form.querySelector('[data-relocation-receive]').disabled = busy || !manager || form.dataset.status !== 'Fulfilled';
        form.querySelector('[data-reconcile-unavailable]').textContent = form.dataset.status === 'Fulfilled' ? 'Acknowledge receipt to enter the quantities actually received.' : 'Not available until shipped.';
        refreshFulfillment();
        sharedTable.hidden = form.dataset.saved !== 'true' || (materialTablePolicy().tab === 'fulfillment' && !available) || (materialTablePolicy().tab === 'reconcile' && !reconcileAvailable);
        form.querySelector('[data-material-search-panel]').hidden = sharedTable.hidden;
        updateMaterialChoices();
        const trackingAvailable = ['Accepted', 'Fulfilled', 'Received', 'Completed', 'Cancelled'].includes(form.dataset.status);
        form.querySelector('[data-tracking-content]').hidden = !trackingAvailable;
        form.querySelector('[data-tracking-unavailable]').hidden = trackingAvailable;
        form.querySelector('[data-tracking-fields]').disabled = busy || !canEditTracking();
        refreshShipment();
        refreshConfirmation();
        save.disabled = busy || !(form.dataset.editable === 'true' || (manager && accepted && ['basic', 'fulfillment'].includes(activeTab())) || (manager && form.dataset.status === 'Received' && activeTab() === 'reconcile') || (canEditTracking() && activeTab() === 'tracking'));
    };
    refreshFulfillment();
    refreshWorkflow();
    const errorAttributes = new Map();
    const clearErrors = () => {
        form.querySelectorAll('[data-relocation-field-error]').forEach(error => error.remove());
        errorAttributes.forEach((previous, control) => {
            Object.entries(previous).forEach(([name, value]) => {
                if (value === null) control.removeAttribute(name);
                else control.setAttribute(name, value);
            });
        });
        errorAttributes.clear();
        feedback.replaceChildren();
        feedback.hidden = true;
        feedback.removeAttribute('aria-label');
    };
    const showErrors = (result, action = 'save') => {
        const materialError = Object.keys(result.errors || {}).some(name => name === 'lines' || name.startsWith('lines.'));
        const fulfillmentError = action === 'accept' || form.dataset.status === 'Accepted';
        showTab(tabs[action === 'complete' ? 7 : ['receive', 'reconciliation'].includes(action) ? 6 : action === 'basic' ? 0 : action === 'ship' ? 5 : action === 'tracking' ? 4 : fulfillmentError ? 3 : action === 'submit' ? 2 : materialError ? 1 : 0]);
        feedback.hidden = false;
        feedback.className = 'error';
        feedback.setAttribute('role', 'alert');
        feedback.setAttribute('aria-label', 'Validation errors');
        const heading = document.createElement('p');
        heading.textContent = 'Progress could not be saved. Correct the fields below and try again.';
        feedback.replaceChildren(heading);
        const labels = { title: 'Title', source_location_id: 'Source', destination_location_id: 'Destination', details: 'Notes', shipping_date: 'Shipping date' };
        Object.entries(result.errors || {}).forEach(([name, messages]) => {
            const control = controls().find(field => field.name.replace(/\[([^\]]+)\]/g, '.$1') === name);
            const message = (Array.isArray(messages) ? messages : [messages]).join(' ');
            const summary = document.createElement('p');
            if (control) {
                const link = document.createElement('a');
                link.href = '#' + control.id;
                const itemLabel = control.closest('[data-material-item]')?.querySelector('[data-material-label]').textContent;
                const quantityLabel = control.name.startsWith('reconciliation[') ? 'Received quantity for ' : control.name.startsWith('fulfillment[') ? 'Fulfilled quantity for ' : 'Requested quantity for ';
                const trackingLabel = control.name.startsWith('tracking[') ? (control.name.endsWith('[carrier]') ? 'Shipper' : control.name.endsWith('[name]') ? 'Name' : 'Tracking number') : null;
                link.textContent = `${itemLabel ? quantityLabel + itemLabel : trackingLabel || labels[name] || name}: ${message}`;
                link.addEventListener('click', () => { showTab(tabs[control.name.startsWith('reconciliation[') ? 6 : control.name === 'shipping_date' ? 5 : control.name.startsWith('tracking[') ? 4 : control.name.startsWith('fulfillment[') ? 3 : control.name.startsWith('lines[') ? 1 : 0]); form.elements.namedItem(control.name)?.focus(); });
                summary.append(link);
                const inline = document.createElement('p');
                inline.id = control.id + '-error';
                inline.className = 'app-field-error';
                inline.dataset.relocationFieldError = '';
                inline.textContent = message;
                (control.closest('label') || control).insertAdjacentElement('afterend', inline);
                errorAttributes.set(control, { 'aria-invalid': control.getAttribute('aria-invalid'), 'aria-describedby': control.getAttribute('aria-describedby') });
                control.setAttribute('aria-invalid', 'true');
                control.setAttribute('aria-describedby', [control.getAttribute('aria-describedby'), inline.id].filter(Boolean).join(' '));
            } else summary.textContent = message;
            feedback.append(summary);
        });
        if (!Object.keys(result.errors || {}).length) {
            heading.textContent = result.message || 'Progress could not be saved. Your entered values are retained.';
        }
        feedback.focus();
    };
    const edited = event => {
        if (searchRoot.contains(event.target)) return;
        markDirty(event.target.matches('[data-shipping-date]') ? 'shipping' : undefined);
    };
    form.addEventListener('input', edited);
    form.addEventListener('change', edited);
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy) return;
        const action = event.submitter?.value || 'save';
        const printing = action === 'print';
        const printUrl = form.querySelector('[data-fulfillment-print]').dataset.printUrl;
        if (printing && !(activeTab() === 'fulfillment' && form.dataset.status === 'Accepted' && form.dataset.manager === 'true')) {
            window.open(printUrl, '_blank', 'noopener');
            return;
        }
        const accepting = action === 'accept';
        const shipping = action === 'ship';
        const receiving = action === 'receive';
        const completing = action === 'complete';
        const reconciliationSave = action === 'save' && activeTab() === 'reconcile' && form.dataset.status === 'Received';
        const fulfillmentSave = (action === 'save' || printing) && form.dataset.status === 'Accepted' && form.querySelector('[aria-selected="true"]').dataset.workflowTab === 'fulfillment';
        const basicSave = action === 'save' && form.dataset.status === 'Accepted' && activeTab() === 'basic';
        const trackingSave = action === 'save' && activeTab() === 'tracking' && canEditTracking();
        if (receiving ? form.dataset.manager !== 'true' || form.dataset.status !== 'Fulfilled' : completing ? form.dataset.manager !== 'true' || form.dataset.status !== 'Received' : shipping ? form.dataset.manager !== 'true' || form.dataset.status !== 'Accepted' : accepting ? form.dataset.manager !== 'true' || form.dataset.status !== 'Requested' : fulfillmentSave || basicSave || reconciliationSave ? form.dataset.manager !== 'true' : trackingSave ? !canEditTracking() : form.dataset.editable !== 'true') return;
        if (shipping && dirtySections.has('fulfillment')) {
            showTab(tabs[3]);
            feedback.hidden = false; feedback.className = 'error'; feedback.setAttribute('role', 'alert');
            feedback.textContent = 'Save Fulfillment progress before shipping so the saved quantities match your edits.';
            return;
        }
        if (pendingSelections()) {
            showTab(tabs[['reconcile', 'confirmation'].includes(activeTab()) ? 6 : activeTab() === 'fulfillment' ? 3 : 1]);
            showSearchMessage('Use Add selected or clear checked items and entered quantities before saving.');
            return;
        }
        if (action === 'submit') {
            refreshReview();
            if (submit.disabled) return;
        }
        if (completing) {
            refreshConfirmation();
            if (form.querySelector('[data-relocation-complete]').disabled) return;
        }
        const data = new FormData(form);
        if (completing) requestedRows().forEach(row => {
            data.set(`reconciliation[${row.dataset.materialItem}]`, row.querySelector('[data-material-received]').value);
        });
        const disabledBeforeSave = new Map(controls().map(control => [control, control.disabled]));
        data.set('action', printing ? 'save' : action);
        // Reserve the new tab during the click so saving can finish before navigation.
        const printWindow = printing ? window.open('', '_blank') : null;
        let printReady = false;
        if (printWindow) {
            printWindow.opener = null;
            printWindow.document.title = 'Preparing packing sheet';
            printWindow.document.body.textContent = 'Saving progress before opening the packing sheet…';
        }
        clearErrors();
        busy = true;
        controls().forEach(control => { control.disabled = true; });
        [...form.querySelectorAll('button'), save].forEach(button => { button.disabled = true; });
        save.setAttribute('aria-busy', 'true');
        save.querySelector('[data-save-label]').textContent = 'Saving…';
        try {
            const endpoint = form.getAttribute('action') + (receiving ? '/receive' : completing ? '/complete' : reconciliationSave ? '/reconciliation' : shipping ? '/ship' : accepting ? '/accept' : basicSave ? '/basic' : fulfillmentSave ? '/fulfillment' : trackingSave ? '/tracking' : '');
            const response = await fetch(endpoint, { method: 'POST', body: data, headers: { Accept: 'application/json' } });
            const result = (response.headers.get('content-type') || '').includes('application/json') ? await response.json() : {};
            feedback.hidden = false;
            if (!response.ok || result.saved !== true) {
                showErrors(result, reconciliationSave ? 'reconciliation' : basicSave ? 'basic' : trackingSave || (shipping && Object.keys(result.errors || {}).some(name => name.startsWith('tracking.'))) ? 'tracking' : action);
                return;
            }
            Object.entries(result.values || {}).forEach(([name, value]) => {
                if (!basicSave && dirtySections.has('basic') && ['title', 'details'].includes(name)) return;
                form.elements.namedItem(name).value = value ?? '';
            });
            Object.entries(result.lines || {}).forEach(([item, value]) => { form.elements.namedItem(`lines[${item}]`).value = value ?? ''; });
            if (result.addedStages) requestedRows().forEach(row => { row.dataset.addedStage = result.addedStages[row.dataset.materialItem]; });
            if ('requestedDate' in result) form.querySelector('[data-ship-requested-date]').textContent = result.requestedDate || '—';
            if (result.shippingDate) form.querySelector('[data-shipping-date]').value = result.shippingDate;
            if (result.fulfillerName) form.querySelector('[data-ship-fulfiller]').textContent = result.fulfillerName;
            if (result.fulfilled) {
                fulfilledValues = result.fulfilled;
                fulfillmentBody.querySelectorAll('[data-fulfillment-quantity]').forEach(input => { input.value = fulfilledValues[input.dataset.itemId] ?? ''; });
                requestedRows().forEach(row => { row.querySelector('[data-material-fulfilled]').value = fulfilledValues[row.dataset.materialItem] ?? ''; });
            }
            if (result.received && (receiving || reconciliationSave || completing || !dirtySections.has('reconciliation'))) {
                requestedRows().forEach(row => { row.querySelector('[data-material-received]').value = result.received[row.dataset.materialItem] ?? ''; });
            }
            if (result.tracking) {
                trackingBody.replaceChildren(); trackingCounter = 0;
                result.tracking.forEach(addTrackingRow);
                if (!result.tracking.length) addTrackingRow();
                showSavedTracking(result.tracking);
            }
            form.elements.namedItem('revision').value = result.revision;
            form.dataset.saved = 'true';
            form.querySelector('[data-material-content]').hidden = false;
            form.querySelector('[data-material-search-panel]').hidden = false;
            form.querySelector('[data-material-table]').hidden = false;
            form.querySelector('[data-material-unavailable]').hidden = true;
            form.dataset.status = result.status;
            if (shipping || completing) {
                requestedRows().forEach(row => { row.dataset.sourceBalance = ''; row.dataset.destinationBalance = ''; });
                await refreshBalances();
            }
            document.querySelector('[data-relocation-status]').textContent = result.status;
            if (result.status !== 'Draft') form.dataset.editable = 'false';
            form.querySelectorAll('[data-stage-placeholder]').forEach(message => { message.textContent = 'This section will be added in a later step.'; });
            feedback.className = 'notice'; feedback.setAttribute('role', 'status'); feedback.textContent = receiving ? 'Receipt acknowledged. Enter the quantities actually received.' : completing ? 'Relocation completed.' : shipping ? 'Request shipped.' : action === 'submit' ? 'Request submitted.' : accepting ? 'Request accepted.' : trackingSave ? 'Tracking saved.' : 'Progress saved.';
            if (!receiving) dirtySections.delete(reconciliationSave || completing ? 'reconciliation' : trackingSave || shipping ? 'tracking' : basicSave ? 'basic' : fulfillmentSave ? 'fulfillment' : 'request');
            if (shipping) dirtySections.delete('shipping');
            dirty = dirtySections.size > 0; save.classList.toggle('app-save-dirty', dirty);
            if (dirty) feedback.textContent += ' Changes on another tab are still unsaved.';
            if (action === 'next') showTab(tabs[1]);
            if (action === 'submit') showTab(tabs[2]);
            if (accepting) showTab(tabs[3]);
            if (receiving) showTab(tabs[6]);
            if (completing) showTab(tabs[7]);
            if (printing) {
                if (printWindow && !printWindow.closed) printWindow.location.replace(printUrl);
                else {
                    const link = document.createElement('a');
                    link.href = printUrl; link.target = '_blank'; link.rel = 'noopener';
                    link.textContent = 'Open packing sheet';
                    feedback.append(' ', link);
                }
                printReady = true;
            }
        } catch {
            feedback.hidden = false; feedback.className = 'error';
            feedback.textContent = 'Progress could not be saved. Your entered values are retained; try again.';
        } finally {
            if (printWindow && !printReady && !printWindow.closed) printWindow.close();
            busy = false;
            controls().forEach(control => { control.disabled = disabledBeforeSave.get(control) ?? false; });
            [...form.querySelectorAll('button'), save].forEach(button => { button.disabled = false; });
            updateMaterialChoices();
            lockSubmittedForm();
            refreshReview();
            refreshWorkflow();
            save.setAttribute('aria-busy', 'false'); save.querySelector('[data-save-label]').textContent = 'Save progress';
        }
    });
    mountUnsavedChanges(form, { isDirty: () => dirty, isBusy: () => busy });
})();
