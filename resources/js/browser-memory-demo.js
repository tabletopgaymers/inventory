import './app.js';
import { loadCatalogSnapshot, CATALOG_MAX_AGE_MS } from './catalog-snapshot.js';

const root = document.querySelector('[data-memory-demo]');
async function mountDemo(root) {
    const snapshotLabel = document.querySelector('[data-memory-snapshot]');
    let snapshot;
    try { snapshot = await loadCatalogSnapshot({userId: root.dataset.userId, endpoint: root.dataset.catalogUrl}); }
    catch {
        snapshotLabel.textContent = 'Catalog and inventory unavailable.';
        document.querySelector('[data-memory-load-error]').hidden = false;
        document.querySelector('[data-memory-retry]').addEventListener('click', () => window.location.reload());
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
    const input = root.querySelector('#memory-search'), suggestions = root.querySelector('#memory-suggestions');
    const clearSearch = root.querySelector('.memory-demo-clear');
    const locationSelect = root.querySelector('#memory-location');
    locationSelect.replaceChildren();
    if (!locations.has(catalog.central_id)) locationSelect.add(new Option('Choose location', ''));
    [...locations.values()].sort((a, b) => collator.compare(a.name, b.name) || a.id - b.id)
        .forEach(location => locationSelect.add(new Option(location.name, String(location.id))));
    let selectedLocationId = locations.has(catalog.central_id) ? catalog.central_id : null;
    const storageKey = `browser-memory-demo:v1:user:${root.dataset.userId}`;
    const persistenceNotice = root.querySelector('[data-memory-persistence]');
    const storageWarning = () => {
        persistenceNotice.hidden = false;
        persistenceNotice.textContent = 'Browser storage is unavailable. Selections still work, but may not be remembered when you leave this page.';
    };
    try {
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
        try {
            localStorage.setItem(storageKey, JSON.stringify({selectedItemIds: [...selected], locationId: selectedLocationId}));
            persistenceNotice.hidden = true;
        } catch { storageWarning(); }
    };
    const body = root.querySelector('[data-memory-results]'), status = root.querySelector('[data-memory-status]');
    let matches = [], active = 0;
    const close = () => { suggestions.hidden = true; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); };
    const activate = index => {
        active = index;
        [...suggestions.children].forEach((option, i) => option.setAttribute('aria-selected', String(i === active)));
        input.setAttribute('aria-activedescendant', `memory-option-${active}`);
        suggestions.children[active]?.scrollIntoView({block: 'nearest'});
    };
    const textCell = (tag, text) => { const cell = document.createElement(tag); cell.textContent = text; return cell; };
    const updateAdded = () => {
        [...suggestions.children].forEach((option, index) => {
            const entry = matches[index];
            const ids = entry?.kind === 'Item' ? [entry.id] : (entry?.kind === 'Collection' ? collectionItems.get(entry.id) : categoryItems.get(entry?.id)) ?? [];
            const added = ids.length > 0 && ids.every(id => selected.has(id));
            option.classList.toggle('memory-demo-added', added);
            if (added) option.setAttribute('aria-description', 'Already in Results');
            else option.removeAttribute('aria-description');
        });
    };
    const renderResults = () => {
        root.querySelector('[data-memory-location-heading]').textContent = locations.get(selectedLocationId)?.name ?? 'Choose location';
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
                const row = document.createElement('tr'), heading = textCell('th', `${categories.get(collection.category_id)?.name ?? 'Category'} : ${collection.name}`);
                row.className = 'group-row'; heading.colSpan = 3; heading.scope = 'rowgroup';
                const action = document.createElement('th'), remove = document.createElement('button');
                remove.type = 'button'; remove.className = 'material-remove'; remove.textContent = '✕';
                remove.dataset.memoryRemoveCollection = collection.id;
                remove.setAttribute('aria-label', `Remove collection ${categories.get(collection.category_id)?.name ?? 'Category'} : ${collection.name}`);
                action.append(remove); row.append(action, heading); body.append(row);
            }
            const row = document.createElement('tr'), name = textCell('td', item.name);
            name.className = 'app-grouped-item';
            const quantity = textCell('td', locations.has(selectedLocationId) ? Number(inventory.get(`${item.id}:${selectedLocationId}`) ?? 0).toLocaleString() : '—');
            quantity.className = 'table-numeric'; row.dataset.memoryItem = item.id;
            const action = document.createElement('td'), remove = document.createElement('button');
            remove.type = 'button'; remove.className = 'material-remove'; remove.textContent = '✕';
            remove.dataset.memoryRemove = item.id; remove.setAttribute('aria-label', `Remove ${item.name}`); action.append(remove);
            row.append(action, name, textCell('td', item.sku), quantity); body.append(row);
        });
        if (!rows.length) {
            const row = document.createElement('tr'), empty = textCell('td', 'Select an item, collection or category to begin.');
            empty.colSpan = 4; row.append(empty); body.append(row);
        }
        root.querySelector('[data-memory-count]').textContent = `${selected.size.toLocaleString()} ${selected.size === 1 ? 'item' : 'items'}`;
        updateAdded();
        remember();
    };
    renderResults();
    input.disabled = false; locationSelect.disabled = false;
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
            renderResults();
            status.textContent = `${collections.get(id).name} removed. ${selected.size.toLocaleString()} total.`;
            input.focus();
            return;
        }
        const remove = event.target.closest('[data-memory-remove]');
        if (!remove) return;
        const id = Number(remove.dataset.memoryRemove), buttons = [...body.querySelectorAll('[data-memory-remove]')];
        const index = buttons.indexOf(remove), neighbor = buttons[index + 1] ?? buttons[index - 1];
        selected.delete(id); renderResults();
        status.textContent = `${items.get(id).name} removed. ${selected.size.toLocaleString()} total.`;
        (neighbor ? body.querySelector(`[data-memory-remove="${neighbor.dataset.memoryRemove}"]`) : input)?.focus();
    });
    const add = entry => {
        if (!entry) return;
        const ids = entry.kind === 'Item' ? [entry.id] : (entry.kind === 'Collection' ? collectionItems.get(entry.id) : categoryItems.get(entry.id)) ?? [];
        const before = selected.size;
        ids.forEach(id => { if (collections.has(items.get(id)?.collection_id)) selected.add(id); });
        if (selected.size) renderResults();
        const added = selected.size - before;
        status.textContent = `${entry.name}: ${added.toLocaleString()} ${added === 1 ? 'item' : 'items'} added. ${selected.size.toLocaleString()} total.`;
        input.focus();
    };
    input.addEventListener('input', () => {
        clearSearch.hidden = input.value.length === 0;
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
            option.className = `memory-demo-level-${entry.depth}`;
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
    root.querySelector('[data-memory-search]').addEventListener('submit', event => { event.preventDefault(); if (!suggestions.hidden) add(matches[active]); });
    suggestions.addEventListener('pointerdown', event => event.preventDefault());
    suggestions.addEventListener('click', event => { const option = event.target.closest('[data-index]'); if (option) add(matches[Number(option.dataset.index)]); });
    input.addEventListener('blur', close);
    clearSearch.addEventListener('click', () => {
        input.value = ''; matches = []; suggestions.replaceChildren(); close();
        clearSearch.hidden = true; status.textContent = ''; input.focus();
    });
    input.addEventListener('focus', () => {
        if (input.value.trim() && matches.length) {
            suggestions.hidden = false; input.setAttribute('aria-expanded', 'true'); activate(active);
        }
    });
}
if (root) {
    mountDemo(root);
    // Recheck the current user and cache age when Back/Forward restores page memory.
    window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
}
