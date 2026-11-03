@extends('design.phase-a.layout')
@section('title', 'Browser Memory Demo')
@section('guide-notice')Catalog and inventory snapshots are reused for up to 15 minutes across pages using this cache. Selected item IDs and location are remembered separately for each signed-in user. No catalog records or inventory are changed.@endsection
@section('guide-content')
@vite(['resources/css/browser-memory-demo.css', 'resources/js/browser-memory-demo.js'])
<p class="app-field-help" data-memory-snapshot>Loading catalog and inventory…</p>
<div class="error" role="alert" data-memory-load-error hidden>Catalog and inventory could not be loaded. Your saved selections have been kept. <button type="button" data-memory-retry>Try again</button></div>
<x-app.panel-stack>
<div class="memory-demo-columns" data-memory-demo data-user-id="{{ auth()->id() }}" data-catalog-url="/style-guide/browser-memory-demo/data">
    <section class="app-content">
        <h2 class="app-heading-flush">Search</h2>
        <form data-memory-search role="search">
            <label for="memory-search">Item, collection or category</label>
            <div class="memory-demo-combobox">
                <input id="memory-search" type="text" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="memory-suggestions" autocomplete="off" spellcheck="false" disabled>
                <button type="button" class="memory-demo-clear" aria-label="Clear search" title="Clear search" hidden>✕</button>
                <ul id="memory-suggestions" class="memory-demo-suggestions" role="listbox" aria-label="Matching catalog entries" hidden></ul>
            </div>
        </form>
        <p class="app-field-help">Search names, including an item's category and collection. Choose a match or press Enter to add the first match. A category or collection adds all its items. Already selected items are included only once.</p>
        <p role="status" data-memory-status></p>
        <p class="warning" role="status" data-memory-persistence hidden></p>
    </section>
    <section class="app-panel-stack" aria-labelledby="memory-results-title">
        <x-app.table-region label="Selected inventory" :nowrap="true" :quantity-fields="true">
            <x-slot:header>
                <x-workflow.heading>
                    <h2 id="memory-results-title">Results</h2>
                    <select id="memory-location" aria-label="Inventory location" disabled><option value="">Loading locations…</option></select>
                </x-workflow.heading>
                <p class="app-field-help" data-memory-count>0 items</p>
            </x-slot:header>
            <table>
                <colgroup><col class="table-action-column"><col><col><col class="table-quantity-column"></colgroup>
                <thead><tr><th><span class="sr-only">Remove</span></th><th>Item</th><th>SKU</th><th class="table-numeric" data-memory-location-heading>Location</th></tr></thead>
                <tbody data-memory-results><tr><td colspan="4">Loading catalog and inventory…</td></tr></tbody>
            </table>
        </x-app.table-region>
    </section>
</div>
<x-app.collapsible-panel title="About this demo and implementation notes">
    <p>This is a read-only browser-selection demonstration. It does not create a purchase, relocation, reservation or stock adjustment. Only this demo uses the snapshot loader; existing application workflows have not been converted.</p>

    <h3>What happens in the browser</h3>
    <ul>
        <li>Search matches item names and their category/collection context. Suggestions show naturally sorted Category → Collection → Item hierarchies, including parents of matching descendants.</li>
        <li>Click or Enter adds an item, every item in a collection, or every item in a category. A Set of item IDs prevents duplicates. Individual and collection ✕ controls remove selections.</li>
        <li>Grey suggestions indicate an item is selected, or every item in a nonempty collection/category is selected. The query stays open for repeated selections; arrows navigate, Escape dismisses and ✕ clears the search.</li>
        <li>The location selector changes the displayed quantity using the loaded inventory map. Central is the initial default. A missing item/location balance displays zero; an unavailable location displays no quantity.</li>
        <li>Searching, adding, removing and switching locations make no server requests. Data loading is separate from selection state.</li>
    </ul>

    <h3>Minimal snapshot and relationships</h3>
    <ul>
        <li><code>categories</code>: <code>id, name</code>.</li>
        <li><code>collections</code>: <code>id, name, sku_prefix, category_id</code>.</li>
        <li><code>items</code>: <code>id, name, sku, collection_id</code>; <code>sku</code> is the full SKU.</li>
        <li><code>locations</code>: <code>id, name</code>; <code>central_id</code> identifies Central.</li>
        <li><code>inventory</code>: <code>item_id, storage_location_id, quantity</code>, from current storage-location balances. Ordered, In-Transit and event quantities are not included.</li>
    </ul>
    <p>Items connect to collections, which connect to categories. Inventory connects an item and a storage location. JavaScript Maps index these relationships. All catalog records are included; this demo does not impose an active-only filter. Descriptions, prices, addresses and other catalog details are not loaded.</p>

    <h3>Persistence and freshness contract</h3>
    <ul>
        <li><code>browser-memory-demo:v1:user:[ID]</code> stores <code>{selectedItemIds, locationId}</code> in localStorage. It does not store the query or copies of catalog/quantity data.</li>
        <li><code>inventory-catalog-snapshot:v1:user:[ID]</code> separately stores <code>{version, loadedAt, catalog}</code>. <code>loadedAt</code> is the successful fetch time in milliseconds since the Unix epoch.</li>
        <li>Opening the page reuses a valid snapshot no more than 15 minutes old. A missing, expired, malformed or future-dated cache causes a new read-only request. Reuse never renews the timestamp.</li>
        <li>The displayed timestamp/age updates every 30 seconds. An already-open page does not silently refresh its data; reopen or reload after expiry. Back/Forward restoration reloads the shell to recheck the user and cache age.</li>
        <li>Restored selections are checked against the loaded snapshot. Missing items are dropped, duplicates collapse, and an invalid saved location falls back to Central. Selected IDs have no 15-minute expiry.</li>
        <li>Storage is scoped to this website origin, browser profile and user ID. It survives normal tab/browser closure until cleared; signing out does not delete it. Namespacing separates accounts but is not encryption or server authorization. Multiple tabs currently use last-write-wins storage, without live selection synchronization.</li>
        <li>If snapshot loading fails, the controls stay disabled and Retry is offered; saved selections are kept. If localStorage cannot be written, the loaded demo still works in memory with a warning that it may not be remembered.</li>
    </ul>
    <p>For the current development dataset, one observed snapshot used approximately 76.34 KiB and 51 selected IDs/location used 0.41 KiB, using a UTF-16 key/value estimate. This is an example, not a quota guarantee; other site storage shares the allowance.</p>

    <h3>Code entry points and reuse</h3>
    <ul>
        <li><code>app/Http/Controllers/BrowserMemoryDemoController.php</code>: lightweight page shell and consistent read-only database snapshot.</li>
        <li><code>routes/design-phase-a.php</code>: authenticated, local/development-only <code>GET /style-guide/browser-memory-demo</code> and <code>GET /style-guide/browser-memory-demo/data</code>. The latter returns <code>{catalog}</code> with private/no-store HTTP caching.</li>
        <li><code>resources/js/catalog-snapshot.js</code>: reusable <code>loadCatalogSnapshot({userId, endpoint})</code> and <code>CATALOG_MAX_AGE_MS</code>. It returns the catalog, original loadedAt, cache source and persistence status. A cache hit avoids requesting the JSON endpoint.</li>
        <li><code>resources/js/browser-memory-demo.js</code>: search, hierarchy, selection, location display and demo-specific selection persistence.</li>
        <li><code>resources/views/design/phase-a/browser-memory-demo.blade.php</code> and <code>resources/css/browser-memory-demo.css</code>: shared panels/table components and demo presentation. Assets are registered in <code>vite.config.js</code>.</li>
    </ul>
    <p>Another page can import the same loader, supply its authenticated user ID and the same snapshot contract, and reuse this cache without sharing its selected-item list. Keep page/workflow selection keys separate. Version the cache key/validation when the payload contract changes. Do not copy the loader or embed another full catalog into every page.</p>

    <h3>Next steps for a real workflow</h3>
    <ol>
        <li>Choose a single consuming page and its required catalog visibility, fields and quantity rules. Reuse the existing search/table components and this loader where they fit; avoid a site-wide replacement in one step.</li>
        <li>Keep cached availability informational. On Save/Submit, send selected IDs and workflow inputs to the server, which must recheck current authentication, permissions, item/location eligibility, quantities and request revision. Never trust localStorage or cached stock as authoritative.</li>
        <li>Apply existing transaction, locking, duplicate-operation and inventory/cost posting rules on the server. Selecting an item must not reserve or move stock. Preserve entered work when validation fails.</li>
        <li>Decide when successful catalog/inventory writes should invalidate the cache, whether an active page needs refresh controls, and whether multiple-tab synchronization is useful. These behaviors are not implemented here.</li>
        <li>Before adopting it outside the demo, perform focused checks for account switching, stale/removed records, denied or full browser storage, expired-cache request failure, keyboard/mobile use and the actual Save/Submit integration.</li>
    </ol>
    <p>Current evidence: focused author browser/build checks and a quick independent source review of an earlier demo checkpoint are recorded in <code>TG Inventory Dev/work/tasks/browser-memory-demo.md</code>. This is not a production-readiness assessment. These notes describe behavior and possible integration steps; they do not authorize a migration of existing workflows.</p>
</x-app.collapsible-panel>
</x-app.panel-stack>
<noscript><p class="error">JavaScript is required for this browser-memory demo.</p></noscript>
@endsection
