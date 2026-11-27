import { createAction } from './actions.js';
import { mountClearableInput } from './clearable-input.js';
import './app.js';
import { loadCatalogSnapshot, CATALOG_MAX_AGE_MS } from './catalog-snapshot.js';

/**
 * Supports item, collection and category selection with a shared read-only snapshot. Disable browser selection persistence for an editor initialized from saved IDs and location. The caller owns saving and should wait for the ready callback.
 *
 * Scope: Domain-specific (Saved Lists and browser catalog)
 * Task: Mount a catalog item selection panel
 * @example resources/js/saved-lists.js
 */
export async function mountItemListPicker(root, {persistSelection = true, initialItemIds = [], initialLocationId, onChange = () => {}, onReady = () => {}} = {}) {
    const container = root.closest('[data-memory-picker]');
    const showInventory = root.dataset.showInventory !== '0';
    const snapshotLabel = container.querySelector('[data-memory-snapshot]');
    const showUnavailable = () => {
        snapshotLabel.textContent = 'Catalog and inventory unavailable.';
        container.querySelector('[data-memory-load-error]').hidden = false;
        container.querySelector('[data-memory-retry]').addEventListener('click', () => window.location.reload());
    };
    let snapshot;
    let missingSavedLocation;
    try {
        const options = {userId: root.dataset.userId, endpoint: root.dataset.catalogUrl};
        snapshot = await loadCatalogSnapshot(options);
        // Saved items or a default location can be newer than another page's cache.
        const requestedLocation = initialLocationId === '' || initialLocationId === null || initialLocationId === undefined ? null : Number(initialLocationId);
        const missingLocation = () => requestedLocation !== null && !snapshot.catalog.locations.some(row => row.id === requestedLocation);
        if (!persistSelection && (missingLocation() || initialItemIds.some(id => !snapshot.catalog.items.some(item => item.id === Number(id))))) {
            snapshot = await loadCatalogSnapshot({...options, refresh: true});
        }
        missingSavedLocation = !persistSelection && missingLocation();
    }
    catch {
        showUnavailable();
        return;
    }
    if (missingSavedLocation) {
        showUnavailable();
        return;
    }
    const catalog = snapshot.catalog;
    const updateSnapshotAge = () => {
        const age = Math.max(0, Date.now() - snapshot.loadedAt);
        const ageText = age < 60000 ? `${Math.floor(age / 1000)} seconds` : `${Math.floor(age / 60000)} minutes`;
        snapshotLabel.textContent = `${snapshot.fromCache ? 'Cached' : 'Fresh'} snapshot loaded ${new Date(snapshot.loadedAt).toLocaleString()} · ${ageText} old.`
            + (age > CATALOG_MAX_AGE_MS ? ' Over 15 minutes old; reopening or reloading will refresh it.' : ' Reused on other pages for up to 15 minutes from this load.')
            + (!snapshot.persisted ? ' Browser storage is unavailable; this snapshot cannot be reused after leaving.' : '');
    };
    updateSnapshotAge();
    window.setInterval(updateSnapshotAge, 30000);
    const categories = new Map(catalog.categories.map(row => [row.id, row]));
    const collections = new Map(catalog.collections.map(row => [row.id, row]));
    const items = new Map(catalog.items.map(row => [row.id, row]));
    const locations = new Map(catalog.locations.map(row => [row.id, row]));
    const inventory = new Map(catalog.inventory.map(row => [`${row.item_id}:${row.storage_location_id}`, row.quantity]));
    const collectionItems = new Map(), categoryItems = new Map();
    catalog.items.forEach(item => {
        const collection = collections.get(item.collection_id);
        if (!collection) return;
        if (!collectionItems.has(collection.id)) collectionItems.set(collection.id, []);
        if (!categoryItems.has(collection.category_id)) categoryItems.set(collection.category_id, []);
        collectionItems.get(collection.id).push(item.id);
        categoryItems.get(collection.category_id).push(item.id);
    });
    const entries = [
        ...catalog.items.map(row => ({kind: 'Item', id: row.id, name: row.name, context: `${categories.get(collections.get(row.collection_id)?.category_id)?.name ?? ''} : ${collections.get(row.collection_id)?.name ?? ''}`})),
        ...catalog.collections.map(row => ({kind: 'Collection', id: row.id, name: row.name, context: categories.get(row.category_id)?.name ?? ''})),
        ...catalog.categories.map(row => ({kind: 'Category', id: row.id, name: row.name, context: ''})),
    ].map(row => ({...row, searchName: row.name.normalize('NFKC').toLocaleLowerCase(),
        searchText: `${row.context} ${row.name}`.normalize('NFKC').toLocaleLowerCase()}));
    const entryByKey = new Map(entries.map(entry => [`${entry.kind}:${entry.id}`, entry]));
    const collator = new Intl.Collator(undefined, {numeric: true, sensitivity: 'base'});
    const selected = new Set();
    const collapsedCollections = new Set();
    const input = root.querySelector('#memory-search'), suggestions = root.querySelector('#memory-suggestions');
    const clearSearch = root.querySelector('.item-picker-clear');
    const browse = root.querySelector('[data-memory-browse]');
    const browseOptions = root.querySelector('[data-memory-browse-options]');
    const locationSelect = root.querySelector('#memory-location');
    locationSelect.replaceChildren();
    if (!persistSelection || !locations.has(catalog.central_id)) locationSelect.add(new Option('No default location', ''));
    [...locations.values()].sort((a, b) => collator.compare(a.name, b.name) || a.id - b.id)
        .forEach(location => locationSelect.add(new Option(location.name, String(location.id))));
    let selectedLocationId = persistSelection ? (locations.has(catalog.central_id) ? catalog.central_id : null) : (locations.has(Number(initialLocationId)) ? Number(initialLocationId) : null);
    initialItemIds.forEach(id => { if (items.has(Number(id))) selected.add(Number(id)); });
    const storageKey = `browser-memory-demo:v1:user:${root.dataset.userId}`;
    const persistenceNotice = root.querySelector('[data-memory-persistence]');
    const storageWarning = () => {
        persistenceNotice.hidden = false;
        persistenceNotice.textContent = 'Browser storage is unavailable. Selections still work, but may not be remembered when you leave this page.';
    };
    if (persistSelection) try {
        const saved = JSON.parse(localStorage.getItem(storageKey));
        if (saved && typeof saved === 'object' && !Array.isArray(saved)) {
            if (Array.isArray(saved.selectedItemIds)) saved.selectedItemIds.forEach(id => {
                if (Number.isSafeInteger(id) && items.has(id) && collections.has(items.get(id).collection_id)) selected.add(id);
            });
            if (Number.isSafeInteger(saved.locationId) && locations.has(saved.locationId)) selectedLocationId = saved.locationId;
        }
    } catch { storageWarning(); }
    locationSelect.value = selectedLocationId === null ? '' : String(selectedLocationId);
    const remember = () => {
        onChange({selectedItemIds: [...selected], locationId: selectedLocationId});
        if (!persistSelection) return;
        try {
            localStorage.setItem(storageKey, JSON.stringify({selectedItemIds: [...selected], locationId: selectedLocationId}));
            persistenceNotice.hidden = true;
        } catch { storageWarning(); }
    };
    const body = root.querySelector('[data-memory-results]'), status = root.querySelector('[data-memory-status]');
    const collapseAll = root.querySelector('[data-memory-collapse-all]'), expandAll = root.querySelector('[data-memory-expand-all]');
    const setCollectionCollapsed = (id, collapsed) => {
        if (collapsed) collapsedCollections.add(id); else collapsedCollections.delete(id);
        body.querySelector(`[data-memory-collection-heading="${id}"] .item-picker-section-toggle`)
            ?.setAttribute('aria-expanded', String(!collapsed));
        body.querySelectorAll(`[data-memory-collection-item="${id}"]`).forEach(row => { row.hidden = collapsed; });
    };
    const setAllCollapsed = collapsed => {
        body.querySelectorAll('[data-memory-collection-heading]').forEach(heading => {
            setCollectionCollapsed(Number(heading.dataset.memoryCollectionHeading), collapsed);
        });
    };
    collapseAll.addEventListener('click', () => setAllCollapsed(true));
    expandAll.addEventListener('click', () => setAllCollapsed(false));
    let matches = [], active = 0;
    const close = () => { suggestions.hidden = true; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); };
    const activate = index => {
        active = index;
        [...suggestions.children].forEach((option, i) => option.setAttribute('aria-selected', String(i === active)));
        input.setAttribute('aria-activedescendant', `memory-option-${active}`);
        suggestions.children[active]?.scrollIntoView({block: 'nearest'});
    };
    const textCell = (tag, text) => { const cell = document.createElement(tag); cell.textContent = text; return cell; };
    const idsFor = entry => entry?.kind === 'Item' ? [entry.id] : (entry?.kind === 'Collection' ? collectionItems.get(entry.id) : categoryItems.get(entry?.id)) ?? [];
    const allSelected = ids => ids.length > 0 && ids.every(id => selected.has(id));
    const markAdded = (option, entry) => {
        const ids = idsFor(entry);
        const added = allSelected(ids);
        option.classList.toggle('item-picker-added', added);
        option.setAttribute('aria-description', added ? 'Already in Results. Activate to remove these items.' : 'Activate to add missing items.');
    };
    const updateAdded = () => {
        [...suggestions.children].forEach((option, index) => {
            markAdded(option, matches[index]);
        });
        browseOptions.querySelectorAll('[data-entry-key]').forEach(option => markAdded(option, entryByKey.get(option.dataset.entryKey)));
    };
    const renderResults = () => {
        const locationHeading = root.querySelector('[data-memory-location-heading]');
        if (locationHeading) locationHeading.textContent = locations.get(selectedLocationId)?.name ?? 'Choose location';
        body.replaceChildren();
        const rows = [...selected].map(id => items.get(id)).sort((a, b) => {
            const ac = collections.get(a.collection_id), bc = collections.get(b.collection_id);
            return collator.compare(categories.get(ac.category_id)?.name ?? '', categories.get(bc.category_id)?.name ?? '')
                || ac.category_id - bc.category_id || collator.compare(ac.name, bc.name) || ac.id - bc.id
                || collator.compare(a.name, b.name) || a.id - b.id;
        });
        let group = null;
        rows.forEach(item => {
            const collection = collections.get(item.collection_id);
            if (group !== collection.id) {
                group = collection.id;
                const row = document.createElement('tr'), heading = document.createElement('th');
                row.className = 'group-row'; row.dataset.memoryCollectionHeading = collection.id;
                heading.className = 'item-picker-section-heading'; heading.colSpan = showInventory ? 3 : 2; heading.scope = 'rowgroup';
                const toggle = document.createElement('button'), marker = textCell('span', '▶');
                toggle.type = 'button'; toggle.className = 'item-picker-section-toggle';
                toggle.setAttribute('aria-expanded', String(!collapsedCollections.has(group)));
                marker.className = 'item-picker-marker'; marker.setAttribute('aria-hidden', 'true');
                const title = textCell('span', `${categories.get(collection.category_id)?.name ?? 'Category'} : ${collection.name}`);
                title.className = 'item-picker-section-title';
                const ids = collectionItems.get(group) ?? [];
                const count = ids.filter(id => selected.has(id)).length;
                const tally = textCell('span', `${count.toLocaleString()}/${ids.length.toLocaleString()} items`);
                tally.className = 'item-picker-section-count';
                toggle.append(marker, title, tally); heading.append(toggle);
                const action = document.createElement('th'), remove = createAction({ control: 'delete' });
                remove.type = 'button'; remove.textContent = '✕';
                remove.dataset.memoryRemoveCollection = collection.id;
                remove.setAttribute('aria-label', `Remove collection ${categories.get(collection.category_id)?.name ?? 'Category'} : ${collection.name}`);
                action.append(remove); row.append(action, heading); body.append(row);
            }
            const row = document.createElement('tr'), name = document.createElement('td');
            name.className = 'app-grouped-item item-picker-item-name';
            const label = document.createElement('div'), spacer = textCell('span', '');
            label.className = 'item-picker-item-label'; spacer.className = 'item-picker-marker'; spacer.setAttribute('aria-hidden', 'true');
            label.append(spacer, textCell('span', item.name)); name.append(label);
            row.dataset.memoryCollectionItem = collection.id;
            row.hidden = collapsedCollections.has(collection.id);
            const quantity = textCell('td', locations.has(selectedLocationId) ? Number(inventory.get(`${item.id}:${selectedLocationId}`) ?? 0).toLocaleString() : '—');
            quantity.className = 'table-numeric'; row.dataset.memoryItem = item.id;
            const action = document.createElement('td'), remove = createAction({ control: 'delete' });
            remove.type = 'button'; remove.textContent = '✕';
            remove.dataset.memoryRemove = item.id; remove.setAttribute('aria-label', `Remove ${item.name}`); action.append(remove);
            row.append(action, name, textCell('td', item.sku));
            if (showInventory) row.append(quantity);
            body.append(row);
        });
        if (!rows.length) {
            const row = document.createElement('tr'), empty = textCell('td', 'Select an item, collection or category to begin.');
            empty.colSpan = showInventory ? 4 : 3; row.append(empty); body.append(row);
        }
        const representedCollections = new Set(rows.map(item => item.collection_id));
        const total = [...representedCollections].reduce((sum, id) => sum + (collectionItems.get(id)?.length ?? 0), 0);
        root.querySelector('[data-memory-count]').textContent = `${selected.size.toLocaleString()}/${total.toLocaleString()} items`;
        collapseAll.disabled = expandAll.disabled = selected.size === 0;
        updateAdded();
        remember();
    };
    renderResults();
    input.disabled = false; locationSelect.disabled = false;
    onReady();
    locationSelect.addEventListener('change', () => {
        selectedLocationId = locationSelect.value ? Number(locationSelect.value) : null;
        renderResults();
        status.textContent = `${locations.get(selectedLocationId)?.name ?? 'No location'} inventory shown. ${selected.size.toLocaleString()} items selected.`;
    });
    body.addEventListener('click', event => {
        const collectionRemove = event.target.closest('[data-memory-remove-collection]');
        if (collectionRemove) {
            const id = Number(collectionRemove.dataset.memoryRemoveCollection);
            (collectionItems.get(id) ?? []).forEach(itemId => selected.delete(itemId));
            collapsedCollections.delete(id);
            renderResults();
            status.textContent = `${collections.get(id).name} removed. ${selected.size.toLocaleString()} total.`;
            input.focus();
            return;
        }
        const heading = event.target.closest('[data-memory-collection-heading]');
        if (heading) {
            const id = Number(heading.dataset.memoryCollectionHeading);
            setCollectionCollapsed(id, !collapsedCollections.has(id));
            return;
        }
        const remove = event.target.closest('[data-memory-remove]');
        if (!remove) return;
        const id = Number(remove.dataset.memoryRemove), buttons = [...body.querySelectorAll('[data-memory-remove]')].filter(button => !button.closest('tr').hidden);
        const index = buttons.indexOf(remove), neighbor = buttons[index + 1] ?? buttons[index - 1];
        selected.delete(id); renderResults();
        status.textContent = `${items.get(id).name} removed. ${selected.size.toLocaleString()} total.`;
        (neighbor ? body.querySelector(`[data-memory-remove="${neighbor.dataset.memoryRemove}"]`) : input)?.focus();
    });
    const toggleEntry = (entry, {focusSearch = true} = {}) => {
        if (!entry) return;
        const ids = idsFor(entry);
        const before = selected.size;
        const representedBefore = new Set([...selected].map(id => items.get(id).collection_id));
        const startCollapsed = representedBefore.size > 0 && [...representedBefore].every(id => collapsedCollections.has(id));
        const removing = allSelected(ids);
        ids.forEach(id => {
            if (removing) selected.delete(id);
            else if (collections.has(items.get(id)?.collection_id)) {
                const collectionId = items.get(id).collection_id;
                if (!representedBefore.has(collectionId)) {
                    if (startCollapsed) collapsedCollections.add(collectionId);
                    else collapsedCollections.delete(collectionId);
                }
                selected.add(id);
            }
        });
        if (removing) {
            collapsedCollections.forEach(id => {
                if (!(collectionItems.get(id) ?? []).some(itemId => selected.has(itemId))) collapsedCollections.delete(id);
            });
        }
        if (selected.size !== before) renderResults();
        const changed = Math.abs(selected.size - before);
        status.textContent = `${entry.name}: ${changed.toLocaleString()} ${changed === 1 ? 'item' : 'items'} ${removing ? 'removed' : 'added'}. ${selected.size.toLocaleString()} total.`;
        if (focusSearch) input.focus();
    };
    browseOptions.replaceChildren();
    const byName = (a, b) => collator.compare(a.name, b.name) || a.id - b.id;
    const browseEntry = (kind, row, depth) => {
        const item = document.createElement('li'), button = document.createElement('button');
        button.type = 'button'; button.className = `item-picker-browse-option item-picker-level-${depth}`;
        button.dataset.entryKey = `${kind}:${row.id}`; button.textContent = row.name;
        button.setAttribute('aria-label', `${kind}: ${kind === 'Collection' ? `${categories.get(row.category_id)?.name ?? ''} : ` : ''}${row.name}`);
        item.append(button); browseOptions.append(item);
    };
    [...catalog.categories].sort(byName).forEach(category => {
        browseEntry('Category', category, 0);
        catalog.collections.filter(collection => collection.category_id === category.id).sort(byName)
            .forEach(collection => browseEntry('Collection', collection, 1));
    });
    updateAdded();
    browseOptions.addEventListener('click', event => {
        const option = event.target.closest('[data-entry-key]');
        if (option) toggleEntry(entryByKey.get(option.dataset.entryKey), {focusSearch: false});
    });
    browse.addEventListener('keydown', event => {
        if (event.key === 'Escape') { event.preventDefault(); browse.open = false; browse.querySelector('summary').focus(); }
    });
    document.addEventListener('click', event => { if (!browse.contains(event.target)) browse.open = false; });
    input.addEventListener('input', () => {
        const query = input.value.normalize('NFKC').trim().toLocaleLowerCase();
        if (!query) { matches = []; close(); return; }
        const categoryIds = new Set(), collectionIds = new Set(), itemIds = new Set();
        entries.filter(entry => entry.searchText.includes(query)).forEach(entry => {
            if (entry.kind === 'Category') { categoryIds.add(entry.id); return; }
            const collection = entry.kind === 'Collection' ? collections.get(entry.id) : collections.get(items.get(entry.id)?.collection_id);
            if (!collection) return;
            collectionIds.add(collection.id); categoryIds.add(collection.category_id);
            if (entry.kind === 'Item') itemIds.add(entry.id);
        });
        const byName = (a, b) => collator.compare(a.name, b.name) || a.id - b.id;
        matches = [];
        catalog.categories.filter(row => categoryIds.has(row.id)).sort(byName).forEach(category => {
            matches.push({...entryByKey.get(`Category:${category.id}`), depth: 0});
            catalog.collections.filter(row => row.category_id === category.id && collectionIds.has(row.id)).sort(byName).forEach(collection => {
                matches.push({...entryByKey.get(`Collection:${collection.id}`), depth: 1});
                (collectionItems.get(collection.id) ?? []).filter(id => itemIds.has(id)).map(id => items.get(id)).sort(byName).forEach(item => {
                    matches.push({...entryByKey.get(`Item:${item.id}`), depth: 2});
                });
            });
        });
        suggestions.replaceChildren();
        matches.forEach((entry, index) => {
            const option = document.createElement('li'); option.id = `memory-option-${index}`; option.role = 'option'; option.dataset.index = index;
            option.className = `item-picker-level-${entry.depth}`;
            option.textContent = entry.name;
            suggestions.append(option);
        });
        suggestions.hidden = !matches.length; input.setAttribute('aria-expanded', String(matches.length > 0));
        updateAdded();
        if (matches.length) activate(0);
        else { input.removeAttribute('aria-activedescendant'); status.textContent = 'No matching items, collections or categories.'; }
    });
    input.addEventListener('keydown', event => {
        if (event.isComposing) return;
        if (event.key === 'Escape') { close(); return; }
        if (!suggestions.hidden && matches.length && ['ArrowDown', 'ArrowUp'].includes(event.key)) {
            event.preventDefault(); activate((active + (event.key === 'ArrowDown' ? 1 : -1) + matches.length) % matches.length);
        }
    });
    input.addEventListener('keydown', event => { if (event.key === 'Enter' && !event.isComposing) { event.preventDefault(); if (!suggestions.hidden) toggleEntry(matches[active]); } });
    suggestions.addEventListener('pointerdown', event => event.preventDefault());
    suggestions.addEventListener('click', event => { const option = event.target.closest('[data-index]'); if (option) toggleEntry(matches[Number(option.dataset.index)]); });
    input.addEventListener('blur', close);
    mountClearableInput(input, clearSearch, {onClear: () => {
        matches = []; suggestions.replaceChildren(); close(); status.textContent = '';
    }});
    input.addEventListener('focus', () => {
        if (input.value.trim() && matches.length) {
            suggestions.hidden = false; input.setAttribute('aria-expanded', 'true'); activate(active);
        }
    });
}
