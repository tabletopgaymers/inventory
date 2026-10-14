'use strict';
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
    const showTab = tab => {
        tabs.forEach(candidate => {
            const active = candidate === tab;
            candidate.setAttribute('aria-selected', String(active));
            candidate.tabIndex = active ? 0 : -1;
            document.getElementById(candidate.getAttribute('aria-controls')).hidden = !active;
        });
        form.querySelector('[data-material-notifications]').hidden = !['material', 'fulfillment'].includes(tab.dataset.step);
        refreshReview();
        refreshFulfillment();
        refreshWorkflow();
        refreshShipment();
        if (['review', 'ship'].includes(tab.dataset.step)) refreshBalances();
        const url = new URL(location.href);
        url.searchParams.set('tab', tab.dataset.step);
        url.searchParams.delete('step');
        history.replaceState(null, '', url);
    };
    showTab(tabs.find(tab => tab.dataset.step === form.dataset.initialStep) || tabs[0]);
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => showTab(tab));
        tab.addEventListener('keydown', event => {
            const next = event.key === 'ArrowRight' ? (index + 1) % tabs.length : event.key === 'ArrowLeft' ? (index + tabs.length - 1) % tabs.length : event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : null;
            if (next === null) return;
            event.preventDefault(); showTab(tabs[next]); tabs[next].focus();
        });
    });
    const controls = () => [...form.querySelectorAll('input:not([type="hidden"]), select, textarea')];
    const markDirty = () => {
        dirtySections.add(activeTab() === 'tracking' ? 'tracking' : form.dataset.status === 'Accepted' ? 'fulfillment' : 'request');
        dirty = true; save.classList.add('app-save-dirty'); refreshReview(); refreshShipment();
    };
    const materialRows = form.querySelector('[data-material-lines]');
    const results = form.querySelector('[data-material-results]');
    const searchFeedback = form.querySelector('[data-material-search-feedback]');
    const searchText = form.querySelector('[data-material-search]');
    const searchLoading = form.querySelector('[data-material-search-loading]');
    const searchButton = form.querySelector('[data-material-search-action="search"]');
    const activeTab = () => form.querySelector('[aria-selected="true"]').dataset.step;
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
        || (activeTab() === 'fulfillment' && form.dataset.status === 'Accepted' && form.dataset.manager === 'true');
    const canRemoveMaterial = row => (activeTab() === 'material' && form.dataset.editable === 'true')
        || (activeTab() === 'fulfillment' && form.dataset.status === 'Accepted' && form.dataset.manager === 'true' && row.dataset.addedStage === 'fulfillment');
    const filters = form.querySelector('[data-material-filters]');
    const updateFilters = () => {
        const count = form.querySelectorAll('[data-material-collection]:checked').length;
        form.querySelector('[data-material-filter-summary]').textContent = `— ${count} selected`;
        filters.classList.toggle('active-filter', count > 0);
    };
    form.querySelectorAll('[data-material-collection]').forEach(input => input.addEventListener('change', updateFilters));
    form.querySelector('[data-material-clear-filters]').addEventListener('click', () => {
        if (busy || !canAddMaterial()) return;
        form.querySelectorAll('[data-material-collection]').forEach(input => { input.checked = false; });
        filters.open = false;
        updateFilters();
    });
    updateFilters();
    const requestedRows = () => [...materialRows.querySelectorAll('[data-material-item]')];
    materialRows.addEventListener('keydown', event => {
        if (!['ArrowUp', 'ArrowDown', 'Enter'].includes(event.key) || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey || event.isComposing) return;
        const input = event.target;
        if (!(input instanceof HTMLInputElement) || input.readOnly || input.matches(':disabled') || busy) return;
        const cell = input.closest('td');
        if (!cell) return;
        const column = cell.cellIndex;
        const fields = requestedRows().map(row => row.cells[column]?.querySelector('input'))
            .filter(field => field && !field.readOnly && !field.matches(':disabled'));
        const index = fields.indexOf(input);
        if (index === -1) return;
        event.preventDefault();
        const next = fields[index + (event.key === 'ArrowUp' ? -1 : 1)];
        if (next) { next.focus(); next.select(); }
    });
    const regroupMaterial = () => {
        materialRows.querySelectorAll('[data-material-group]').forEach(group => group.remove());
        const rows = requestedRows().sort((a, b) => a.dataset.itemGroup.localeCompare(b.dataset.itemGroup)
            || a.dataset.itemName.localeCompare(b.dataset.itemName) || a.dataset.itemSku.localeCompare(b.dataset.itemSku));
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
            heading.textContent = `${field.value ? field.selectedOptions[0].textContent : heading.dataset.stockHeading === 'source_location_id' ? 'Source' : 'Destination'} stock`;
        });
        results.querySelectorAll('[data-stock-count]').forEach(cell => { cell.textContent = 'Awaiting refresh'; });
        requestedRows().forEach(row => { row.dataset.sourceBalance = ''; row.dataset.destinationBalance = ''; });
        refreshReview();
        refreshBalances();
    };
    locationControls.forEach(control => control.addEventListener('change', refreshStockContext));
    const pendingSelections = () => [...results.querySelectorAll('[data-search-quantity]')].some(input => input.value.trim() !== '');
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
    form.querySelectorAll('[data-material-search-action]').forEach(button => button.addEventListener('click', async () => {
        if (busy || !canAddMaterial() || form.dataset.saved !== 'true') return;
        if (pendingSelections()) {
            showSearchMessage('Use Add selected or clear entered result quantities before changing search results.');
            return;
        }
        if (button.dataset.materialSearchAction === 'reset') {
            searchText.value = '';
            form.querySelectorAll('[data-material-collection]').forEach(input => { input.checked = false; });
            updateFilters();
            results.replaceChildren(); searchFeedback.hidden = true; updateMaterialChoices(); return;
        }
        if (button.dataset.materialSearchAction === 'all') searchText.value = '';
        const url = new URL(form.getAttribute('action') + '/search', location.origin);
        url.searchParams.set('search', searchText.value);
        for (const name of ['source_location_id', 'destination_location_id']) url.searchParams.set(name, form.elements.namedItem(name).value);
        form.querySelectorAll('[data-material-collection]:checked').forEach(input => url.searchParams.append('collections[]', input.value));
        requestedRows().forEach(row => url.searchParams.append('excluded[]', row.dataset.materialItem));
        busy = true;
        form.querySelector('[data-material-filters]').open = false;
        searchButton.setAttribute('aria-busy', 'true');
        searchLoading.textContent = 'Searching inventory…';
        locationControls.forEach(control => { control.disabled = true; });
        const buttons = [...form.querySelectorAll('button'), save];
        buttons.forEach(control => { control.disabled = true; });
        searchFeedback.hidden = true;
        try {
            const response = await fetch(url, { headers: { Accept: 'text/html, application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) {
                const data = (response.headers.get('content-type') || '').includes('application/json') ? await response.json() : {};
                showSearchMessage(Object.values(data.errors || {}).flat().join(' ') || data.message || 'Search could not be completed.');
                return;
            }
            results.innerHTML = await response.text();
            searchFeedback.hidden = true;
        } catch { showSearchMessage('Search could not be completed. Try again.'); }
        finally {
            busy = false;
            searchButton.setAttribute('aria-busy', 'false');
            searchLoading.textContent = '';
            locationControls.forEach(control => { control.disabled = false; });
            buttons.forEach(control => { control.disabled = false; });
            updateMaterialChoices();
            lockSubmittedForm();
            refreshWorkflow();
            refreshReview();
        }
    }));
    form.querySelector('[data-material-add-selected]').addEventListener('click', event => {
        if (!event.target.closest('[data-material-add-selected]') || busy || !canAddMaterial()) return;
        const ids = new Set(requestedRows().map(row => row.dataset.materialItem));
        let added = false;
        results.querySelectorAll('[data-search-item]').forEach(result => {
            const value = result.querySelector('[data-search-quantity]').value.trim();
            if (value === '' || ids.has(result.dataset.searchItem)) return;
            const row = form.querySelector('[data-material-template]').content.firstElementChild.cloneNode(true);
            const id = result.dataset.searchItem;
            row.dataset.materialItem = id;
            const addingFulfillment = activeTab() === 'fulfillment';
            row.dataset.addedStage = addingFulfillment ? 'fulfillment' : 'requested';
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
            input.value = addingFulfillment ? '0' : quantity;
            if (addingFulfillment) row.querySelector('[data-material-fulfilled]').value = quantity;
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
        span.classList.toggle('relocation-negative', value < 0);
        return span;
    };
    const projection = (currentCell, projectedCell, balance, quantity, direction) => {
        if (balance === '' || balance === undefined) { currentCell.textContent = '—'; projectedCell.textContent = '—'; return; }
        const current = Number(balance);
        currentCell.append(numberText(current));
        if (quantity === null) projectedCell.textContent = '—';
        else projectedCell.append(numberText(current + direction * quantity));
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
    };
    refreshReview = () => {
        const errors = [];
        form.querySelectorAll('[data-review-field]').forEach(output => {
            const field = form.elements.namedItem(output.dataset.reviewField);
            output.textContent = field.tagName === 'SELECT' ? (field.value ? field.selectedOptions[0].textContent : 'Not selected') : field.value || '—';
        });
        form.querySelectorAll('[data-review-location]').forEach(heading => {
            const field = form.elements.namedItem(heading.dataset.reviewLocation);
            heading.textContent = field.value ? field.selectedOptions[0].textContent : heading.dataset.reviewLocation === 'source_location_id' ? 'Source' : 'Destination';
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
            source.classList.toggle('material-quantity-error', quantityError);
            if (!valid) errors.push(`${source.dataset.itemName}: enter a nonblank whole-number requested quantity.`);
            const row = form.querySelector('[data-review-row]').content.firstElementChild.cloneNode(true);
            row.classList.toggle('material-quantity-error', quantityError);
            row.children[0].textContent = source.dataset.itemName;
            projection(row.children[1], row.children[3], source.dataset.sourceBalance, valid ? Number(value.replaceAll(',', '')) : null, -1);
            if (valid) row.children[4].append(numberText(Number(value.replaceAll(',', ''))));
            else row.children[4].textContent = value || 'Blank';
            projection(row.children[5], row.children[7], source.dataset.destinationBalance, valid ? Number(value.replaceAll(',', '')) : null, 1);
            body.append(row);
        });
        if (pendingSelections()) errors.push('Use Add selected or clear pending search quantities before submitting.');
        if (form.dataset.saved !== 'true') errors.push('Save Basic info to create a Draft before submitting.');
        reviewErrors.replaceChildren();
        if (form.dataset.status === 'New' || form.dataset.status === 'Draft') {
            errors.forEach(message => { const line = document.createElement('p'); line.textContent = message; reviewErrors.append(line); });
        }
        reviewErrors.hidden = !reviewErrors.childElementCount || form.querySelector('[aria-selected="true"]').dataset.step !== 'review';
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
            row.classList.toggle('material-quantity-error', !valid && form.dataset.status === 'Accepted');
            row.children[0].textContent = source.dataset.itemName;
            const requestedValid = /^-?(?:\d{1,10}|\d{1,3}(?:,\d{3}){1,3})$/.test(requested);
            if (requestedValid) row.children[1].append(numberText(Number(requested.replaceAll(',', ''))));
            else row.children[1].textContent = requested || 'Blank';
            const quantity = valid ? Number(fulfilled.replaceAll(',', '')) : null;
            if (valid && requestedValid) {
                const difference = quantity - Number(requested.replaceAll(',', ''));
                if (difference !== 0) {
                    const value = document.createElement('span');
                    value.className = difference > 0 ? 'ship-difference-positive' : 'ship-difference-negative';
                    value.textContent = `${difference > 0 ? '+' : '-'} ${Math.abs(difference).toLocaleString('en-US')}`;
                    row.children[2].append('[ ', value, ' ]');
                }
            }
            projection(row.children[3], row.children[5], source.dataset.sourceBalance, previewShipment ? quantity : null, -1);
            if (valid) row.children[6].append(numberText(quantity));
            else row.children[6].textContent = fulfilled || 'Blank';
            projection(row.children[7], row.children[9], source.dataset.destinationBalance, previewShipment ? quantity : null, 1);
            if (!previewShipment) [4, 5, 8, 9].forEach(index => { row.children[index].textContent = ''; });
            body.append(row);
        });
        if (dirtySections.has('fulfillment')) errors.push('Save Fulfillment progress before shipping.');
        const errorBox = form.querySelector('[data-ship-errors]');
        errorBox.replaceChildren();
        if (form.dataset.status === 'Accepted') errors.forEach(message => { const line = document.createElement('p'); line.textContent = message; errorBox.append(line); });
        errorBox.hidden = !errorBox.childElementCount || activeTab() !== 'ship';
        form.querySelector('[data-relocation-ship]').disabled = busy || form.dataset.manager !== 'true' || form.dataset.status !== 'Accepted' || errors.length > 0;
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
    if (['review', 'ship'].includes(activeTab())) refreshBalances();
    const sharedTable = form.querySelector('[data-material-table]');
    const fulfillmentBody = materialRows;
    let fulfilledValues = Object.fromEntries(requestedRows().map(row => [row.dataset.materialItem, row.querySelector('[data-material-fulfilled]').value]));
    const materialTablePolicy = () => {
        const tab = form.querySelector('[aria-selected="true"]').dataset.step === 'fulfillment' ? 'fulfillment' : 'material';
        return {
            tab,
            requested: tab === 'material' && form.dataset.editable === 'true',
            fulfilled: tab === 'fulfillment' && form.dataset.status === 'Accepted' && form.dataset.manager === 'true',
            remove: (tab === 'material' && form.dataset.editable === 'true') || (tab === 'fulfillment' && form.dataset.status === 'Accepted' && form.dataset.manager === 'true'),
        };
    };
    refreshFulfillment = () => {
        const policy = materialTablePolicy();
        const host = form.querySelector(policy.tab === 'fulfillment' ? '[data-fulfillment-table-host]' : '[data-material-table-host]');
        if (sharedTable.parentElement !== host) host.append(sharedTable);
        const searchPanel = form.querySelector('[data-material-search-panel]');
        const searchHost = form.querySelector(policy.tab === 'fulfillment' ? '[data-fulfillment-search-host]' : '[data-material-search-host]');
        if (searchPanel.parentElement !== searchHost) searchHost.append(searchPanel);
        form.querySelector('[data-material-search-fields]').disabled = busy || !canAddMaterial();
        const title = policy.tab === 'fulfillment' ? 'Material fulfillment' : 'Requested material';
        sharedTable.querySelector('[data-material-table-title]').textContent = title;
        sharedTable.querySelector('[data-fulfillment-print]').hidden = policy.tab !== 'fulfillment';
        sharedTable.querySelector('[role="region"]').setAttribute('aria-label', title);
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
            const remove = row.querySelector('[data-material-remove]');
            const stageRemovable = policy.tab === 'material' || row.dataset.addedStage === 'fulfillment';
            remove.hidden = !stageRemovable;
            remove.disabled = busy || !policy.remove || !stageRemovable;
        });
    };
    refreshWorkflow = () => {
        const manager = form.dataset.manager === 'true';
        const accepted = form.dataset.status === 'Accepted';
        const available = !['New', 'Draft', 'Requested'].includes(form.dataset.status);
        form.querySelector('[data-fulfillment-accept-panel]').hidden = available;
        form.querySelector('[data-fulfillment-content]').hidden = !available;
        const unavailable = form.querySelector('[data-fulfillment-unavailable]');
        unavailable.hidden = available;
        unavailable.textContent = form.dataset.status === 'Requested' ? 'Must Accept First' : 'Not available';
        form.querySelector('[data-relocation-accept]').disabled = busy || !manager || form.dataset.status !== 'Requested';
        refreshFulfillment();
        sharedTable.hidden = form.dataset.saved !== 'true' || (materialTablePolicy().tab === 'fulfillment' && !available);
        form.querySelector('[data-material-search-panel]').hidden = form.dataset.saved !== 'true' || (materialTablePolicy().tab === 'fulfillment' && !available);
        updateMaterialChoices();
        const trackingAvailable = ['Accepted', 'Fulfilled', 'Received', 'Completed', 'Cancelled'].includes(form.dataset.status);
        form.querySelector('[data-tracking-content]').hidden = !trackingAvailable;
        form.querySelector('[data-tracking-unavailable]').hidden = trackingAvailable;
        form.querySelector('[data-tracking-fields]').disabled = busy || !canEditTracking();
        refreshShipment();
        save.disabled = busy || !(form.dataset.editable === 'true' || (manager && accepted && activeTab() === 'fulfillment') || (canEditTracking() && activeTab() === 'tracking'));
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
        showTab(tabs[action === 'tracking' ? 4 : fulfillmentError ? 3 : action === 'submit' ? 2 : materialError ? 1 : 0]);
        feedback.hidden = false;
        feedback.className = 'error';
        feedback.setAttribute('role', 'alert');
        feedback.setAttribute('aria-label', 'Validation errors');
        const heading = document.createElement('p');
        heading.textContent = 'Progress could not be saved. Correct the fields below and try again.';
        feedback.replaceChildren(heading);
        const labels = { title: 'Title', source_location_id: 'Source', destination_location_id: 'Destination', details: 'Notes' };
        Object.entries(result.errors || {}).forEach(([name, messages]) => {
            const control = controls().find(field => field.name.replace(/\[([^\]]+)\]/g, '.$1') === name);
            const message = (Array.isArray(messages) ? messages : [messages]).join(' ');
            const summary = document.createElement('p');
            if (control) {
                const link = document.createElement('a');
                link.href = '#' + control.id;
                const itemLabel = control.closest('[data-material-item]')?.querySelector('[data-material-label]').textContent;
                const quantityLabel = control.name.startsWith('fulfillment[') ? 'Fulfilled quantity for ' : 'Requested quantity for ';
                const trackingLabel = control.name.startsWith('tracking[') ? (control.name.endsWith('[carrier]') ? 'Shipper' : control.name.endsWith('[name]') ? 'Name' : 'Tracking number') : null;
                link.textContent = `${itemLabel ? quantityLabel + itemLabel : trackingLabel || labels[name] || name}: ${message}`;
                link.addEventListener('click', () => { showTab(tabs[control.name.startsWith('tracking[') ? 4 : control.name.startsWith('fulfillment[') ? 3 : control.name.startsWith('lines[') ? 1 : 0]); form.elements.namedItem(control.name)?.focus(); });
                summary.append(link);
                const inline = document.createElement('p');
                inline.id = control.id + '-error';
                inline.className = 'app-field-error';
                inline.dataset.relocationFieldError = '';
                inline.textContent = message;
                control.closest('label').insertAdjacentElement('afterend', inline);
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
        if (event.target.matches('[data-material-search], [data-material-collection]')) return;
        markDirty();
    };
    form.addEventListener('input', edited);
    form.addEventListener('change', edited);
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy) return;
        const action = event.submitter?.value || 'save';
        const accepting = action === 'accept';
        const shipping = action === 'ship';
        const fulfillmentSave = action === 'save' && form.dataset.status === 'Accepted' && form.querySelector('[aria-selected="true"]').dataset.step === 'fulfillment';
        const trackingSave = action === 'save' && activeTab() === 'tracking' && canEditTracking();
        if (shipping ? form.dataset.manager !== 'true' || form.dataset.status !== 'Accepted' : accepting ? form.dataset.manager !== 'true' || form.dataset.status !== 'Requested' : fulfillmentSave ? form.dataset.manager !== 'true' : trackingSave ? !canEditTracking() : form.dataset.editable !== 'true') return;
        if (shipping && dirtySections.has('fulfillment')) {
            showTab(tabs[3]);
            feedback.hidden = false; feedback.className = 'error'; feedback.setAttribute('role', 'alert');
            feedback.textContent = 'Save Fulfillment progress before shipping so the saved quantities match your edits.';
            return;
        }
        if (pendingSelections()) {
            showTab(tabs[activeTab() === 'fulfillment' ? 3 : 1]);
            showSearchMessage('Use Add selected or clear entered result quantities before saving.');
            return;
        }
        if (action === 'submit') {
            refreshReview();
            if (submit.disabled) return;
        }
        const data = new FormData(form);
        const disabledBeforeSave = new Map(controls().map(control => [control, control.disabled]));
        data.set('action', action);
        clearErrors();
        busy = true;
        controls().forEach(control => { control.disabled = true; });
        [...form.querySelectorAll('button'), save].forEach(button => { button.disabled = true; });
        save.setAttribute('aria-busy', 'true');
        save.querySelector('[data-save-label]').textContent = 'Saving…';
        try {
            const endpoint = form.getAttribute('action') + (shipping ? '/ship' : accepting ? '/accept' : fulfillmentSave ? '/fulfillment' : trackingSave ? '/tracking' : '');
            const response = await fetch(endpoint, { method: 'POST', body: data, headers: { Accept: 'application/json' } });
            const result = (response.headers.get('content-type') || '').includes('application/json') ? await response.json() : {};
            feedback.hidden = false;
            if (!response.ok || result.saved !== true) {
                showErrors(result, trackingSave || (shipping && Object.keys(result.errors || {}).some(name => name.startsWith('tracking.'))) ? 'tracking' : action);
                return;
            }
            Object.entries(result.values || {}).forEach(([name, value]) => { form.elements.namedItem(name).value = value ?? ''; });
            Object.entries(result.lines || {}).forEach(([item, value]) => { form.elements.namedItem(`lines[${item}]`).value = value ?? ''; });
            if (result.addedStages) requestedRows().forEach(row => { row.dataset.addedStage = result.addedStages[row.dataset.materialItem]; });
            if (result.fulfilled) {
                fulfilledValues = result.fulfilled;
                fulfillmentBody.querySelectorAll('[data-fulfillment-quantity]').forEach(input => { input.value = fulfilledValues[input.dataset.itemId] ?? ''; });
                requestedRows().forEach(row => { row.querySelector('[data-material-fulfilled]').value = fulfilledValues[row.dataset.materialItem] ?? ''; });
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
            if (shipping) {
                requestedRows().forEach(row => { row.dataset.sourceBalance = ''; row.dataset.destinationBalance = ''; });
                await refreshBalances();
            }
            document.querySelector('[data-relocation-status]').textContent = result.status;
            if (result.status !== 'Draft') form.dataset.editable = 'false';
            form.querySelectorAll('[data-stage-placeholder]').forEach(message => { message.textContent = 'This section will be added in a later step.'; });
            feedback.className = 'notice'; feedback.setAttribute('role', 'status'); feedback.textContent = shipping ? 'Request shipped. Stock moved from Source to In Transit.' : action === 'submit' ? 'Request submitted.' : accepting ? 'Request accepted.' : trackingSave ? 'Tracking saved.' : 'Progress saved.';
            dirtySections.delete(trackingSave || shipping ? 'tracking' : fulfillmentSave ? 'fulfillment' : 'request');
            dirty = dirtySections.size > 0; save.classList.toggle('app-save-dirty', dirty);
            if (dirty) feedback.textContent += ' Changes on another tab are still unsaved.';
            if (action === 'next') showTab(tabs[1]);
            if (action === 'submit') showTab(tabs[2]);
            if (accepting) showTab(tabs[3]);
        } catch {
            feedback.hidden = false; feedback.className = 'error';
            feedback.textContent = 'Progress could not be saved. Your entered values are retained; try again.';
        } finally {
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
    window.appNavigationGuards?.push({ modal: true, shouldWarn: () => dirty && !busy, message: 'Your changes have not been saved. Keep editing or leave without saving.' });
})();
