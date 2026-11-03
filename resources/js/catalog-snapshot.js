// Shared, per-user snapshot cache. Selections belong to each page, never this cache.
export const CATALOG_MAX_AGE_MS = 15 * 60 * 1000;
const version = 1;
const id = value => Number.isSafeInteger(value) && value > 0;
const rows = (value, valid) => Array.isArray(value) && value.every(valid);
const validCatalog = data => data && typeof data === 'object'
    && rows(data.categories, row => row && id(row.id) && typeof row.name === 'string')
    && rows(data.collections, row => row && id(row.id) && id(row.category_id) && typeof row.name === 'string' && typeof row.sku_prefix === 'string')
    && rows(data.items, row => row && id(row.id) && id(row.collection_id) && typeof row.name === 'string' && typeof row.sku === 'string')
    && rows(data.locations, row => row && id(row.id) && typeof row.name === 'string')
    && rows(data.inventory, row => row && id(row.item_id) && id(row.storage_location_id) && Number.isSafeInteger(Number(row.quantity)))
    && (data.central_id === null || id(data.central_id));

export async function loadCatalogSnapshot({userId, endpoint}) {
    if (!/^[1-9]\d*$/.test(String(userId))) throw new Error('A signed-in user is required.');
    const key = `inventory-catalog-snapshot:v${version}:user:${userId}`;
    const now = Date.now();
    try {
        const saved = JSON.parse(localStorage.getItem(key));
        if (saved?.version === version && Number.isSafeInteger(saved.loadedAt)
            && saved.loadedAt > 0 && saved.loadedAt <= now && now - saved.loadedAt <= CATALOG_MAX_AGE_MS && validCatalog(saved.catalog)) {
            return {...saved, fromCache: true, persisted: true};
        }
    } catch { /* Missing, corrupt or blocked storage falls through to a fresh read. */ }

    const url = new URL(endpoint, location.href);
    if (url.origin !== location.origin) throw new Error('Catalog endpoint must be same-origin.');
    const response = await fetch(url, {headers: {Accept: 'application/json'}, cache: 'no-store'});
    if (!response.ok) throw new Error('Catalog could not be loaded.');
    const data = await response.json();
    if (!validCatalog(data.catalog)) throw new Error('Catalog response is invalid.');
    const snapshot = {version, loadedAt: Date.now(), catalog: data.catalog};
    let persisted = true;
    try { localStorage.setItem(key, JSON.stringify(snapshot)); }
    catch { persisted = false; }
    return {...snapshot, fromCache: false, persisted};
}
