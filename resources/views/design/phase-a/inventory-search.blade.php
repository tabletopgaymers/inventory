@extends('design.phase-a.layout')
@section('title', 'Search inventory')
@section('guide-notice')This page searches current development inventory. Searches are read-only: they do not save records, change stock or overwrite saved search preferences.@endsection
@section('guide-content')
<style>
    .guide-search-results table { width: 100%; }
    .guide-search-results th, .guide-search-results td { white-space: nowrap; }
    .guide-search-cards { display: grid; grid-template-columns: repeat(auto-fit,minmax(min(100%,18rem),1fr)); gap: var(--app-density-field-y,.75rem) 1rem; }
    .guide-search-data { white-space: pre-wrap; overflow-wrap: anywhere; }
</style>
<p>The search controls are independent of panels and results. The same result data can drive a table, cards, list or selection form. Neither example loads results until Search or Show all.</p>
<p>Search matches Item, Collection and Category names, following the existing inventory search behavior.</p>
<h2>All filters · separate controls and results</h2>
<div class="app-content">
<x-app.inventory-search :definition="$fullDefinition" endpoint="/style-guide/inventory-search/results?profile=full" instance="guide-search-full" />
<p class="app-field-help">Location and source choices select balance columns, rather than restricting matching items. Available always totals all storage; events, In Transit and Ordered remain separate.</p>
</div>
<div class="app-actions"><label>Display results<select id="guide-search-format"><option value="table">Table</option><option value="cards">Cards</option><option value="list">List</option></select></label></div>
<div id="guide-search-full-results" class="guide-search-results" aria-live="polite"><p>Search inventory or choose Show all.</p></div>
<details><summary>Returned data</summary><pre id="guide-search-json" class="guide-search-data">No search submitted.</pre></details>
<x-app.guide-divider />
<h2>Configured filters · controls inside a table header</h2>
<p>Only Collections is visible. Locations remains enabled but hidden and fixed to Central by the server. Events, In Transit, Ordered and inactive-item options are removed. This example returns active items only.</p>
<x-app.table-region label="Configured inventory search results" class="app-list-table-panel">
<x-slot:header>
<x-app.inventory-search :definition="$pickerDefinition" endpoint="/style-guide/inventory-search/results?profile=picker" instance="guide-search-picker" />
</x-slot:header>
<div id="guide-search-picker-results" class="guide-search-results" aria-live="polite"><table><thead><tr><th>Item</th><th>SKU</th><th class="number">Central</th></tr></thead><tbody><tr><td colspan="3">Search inventory or choose Show all.</td></tr></tbody></table></div>
</x-app.table-region>
<x-app.guide-divider />
<details class="app-collapsible-panel">
<summary><h2>Using the component</h2></summary>
<div class="app-collapsible-panel-body">
<p>Render <code>x-app.inventory-search</code> with a definition from <code>InventorySearchControls::definition($config)</code> and the caller's read-only endpoint. Place it in any panel, header or ordinary page section. It does not create a form or submit a surrounding form.</p>
<p>Inject <code>App\Support\InventorySearchControls</code> into your controller. Keep the configuration in one method shared by the page action and an authenticated GET JSON action, applying the caller's usual policy checks to both.</p>
<p>Workflow adapters: singular Relocation uses these controls with its existing protected search and server-rendered selection table (JSON schema_version/rows/columns/count plus html). It supplies locations/excluded IDs through prepareUrl and keeps checkboxes, quantities, stage permissions and Add selected outside the component. Purchase uses a local request adapter over its preloaded single-item select, retaining live filtering and the existing add submission.</p>
<p>Client extension options on appInventorySearch.mount: prepareUrl(url, criteria) adds caller parameters; request(url, criteria, action) optionally returns the same result envelope without the default fetch; render(data, root) renders the caller's result. URLs must remain same-origin. before-search and before-reset are cancelable; before-reset.detail.reset distinguishes Reset from Clear filters. busy events report start/end after control locking/restoration. Existing callers can omit all extensions.</p>
<p>For Vite callers importing the shared client directly, pass :script="false" to x-app.inventory-search to avoid loading a second classic script. Shared CSS is still included once. Keep result-selection state and domain save policy in the caller.</p>
<pre class="guide-search-data"><code>$config = ['filters' =&gt; ['collections', 'locations'], 'activeOnly' =&gt; true];
// Page action: pass this definition to the view.
$definition = $search-&gt;definition($config);
// JSON action: validation is handled by the adapter.
return response()-&gt;json($search-&gt;search($request, $config));</code></pre>
@verbatim
<pre class="guide-search-data"><code>&lt;x-app.inventory-search :definition="$definition"
    :endpoint="route('inventory-picker.results')" instance="inventory-picker" /&gt;</code></pre>
@endverbatim
<p>The component loads its own assets once. Give each instance a unique ID and attach your renderer to that root; the result renderer is separate from the controls. The guide's <code>inventory-search-guide.js</code> demonstrates that pattern.</p>
<ul>
<li><code>filters</code> selects enabled groups: collections, locations, events, transit, ordered, inactive. Omitting the setting enables all.</li>
<li><code>hiddenFilters</code> hides enabled groups; the server supplies their choices from <code>defaults</code>. Removing a group excludes its controls and balance columns.</li>
<li><code>defaults</code> supplies initial criteria; hidden choices stay fixed by the server. <code>activeOnly</code> and <code>excludeIds</code> are optional caller-side result restrictions.</li>
<li>The caller must choose the same trusted configuration for both definition and endpoint, enforce its normal authentication/permissions, and call <code>InventorySearchControls::search($request, $config)</code>. Browser options do not grant access or select arbitrary server configuration.</li>
</ul>
<p>Listen for <code>inventory-search:results</code> on the control root. <code>event.detail</code> contains criteria, rows, columns and count. Alternatively pass a <code>render(data, root)</code> callback to <code>appInventorySearch.mount(root, options)</code>; this also configures an already mounted instance. Calling <code>search()</code> or <code>showAll()</code> returns a promise containing the successful result data.</p>
<p>Rows include <code>item_id</code>, <code>category_id</code>, <code>collection_id</code>, names, SKU, state, Available and keyed quantities. Column metadata includes <code>location_id</code> or <code>source_id</code>/<code>event_id</code> when applicable. An event source ID is not an event ID; a missing event mapping is null. The caller constructs links in its own Catalog/Inventory context. No transaction link is invented for aggregated In Transit/Ordered balances.</p>
<p><code>appInventorySearch.get(root)</code> exposes search, showAll, reset, clearFilters and getState. <code>inventory-search:before-search</code> is cancelable so a workflow can protect entered quantities. Reset clears results; Clear filters keeps them. Pending edits never change displayed results until submitted. Search/Show all collapse filters and show the spinner. Separate instances do not share criteria.</p>
<p class="app-field-help">Existing searches are unchanged. This prototype does not implement saved criteria, export, item selection, stock posting or return-scroll restoration; those belong to the caller.</p>
</div>
</details>
<x-app.guide-divider />
<x-app.table-region label="Existing search integration assessment" class="app-list-table-panel">
<x-slot:header>
<h2>Existing searches and potential replacement</h2>
</x-slot:header>
<table><thead><tr><th>Search</th><th>Shared parts</th><th>Caller behavior to preserve</th></tr></thead><tbody>
<tr><td>Inventory browse</td><td>All current filter groups and balance results.</td><td>Saved personal criteria, pending/submitted distinction, exports, return refresh and scroll restoration.</td></tr>
<tr><td>Singular Relocation Material</td><td>Text/Collection choices, active items, identifiers and stock quantities.</td><td>Owner/Draft authorization, Basic info prerequisite, source/destination, excluded requested items, pending-quantity guard and Add selected.</td></tr>
<tr><td>Plural Relocation item search</td><td>Text/Collections and active-item results.</td><td>Existing draft/session, requested-line selection and source/destination quantities.</td></tr>
<tr><td>Purchase catalog search</td><td>Text/Collections, active items and exclusion of selected lines.</td><td>Requester/procurement access, purchase quantities/estimates, line selection and workflow progress.</td></tr>
<tr><td>Location count search</td><td>Text/Collections and item/location identifiers.</td><td>A required single location, Central-specific active-zero default, inactive-zero exclusion, named searches, and count/worksheet posting rules. Its inclusion rules require a dedicated adapter.</td></tr>
</tbody></table>
<x-slot:footer>
<p>No existing search has been replaced. Read-only Item/Location displays also reuse inventory queries, but are not interactive search boxes.</p>
</x-slot:footer>
</x-app.table-region>
<script src="/inventory-search-guide.js?v={{ substr(hash_file('sha256', public_path('inventory-search-guide.js')), 0, 12) }}" defer></script>
@endsection
