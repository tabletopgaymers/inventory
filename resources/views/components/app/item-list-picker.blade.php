@props(['endpoint' => '/style-guide/browser-memory-demo/data', 'showLocationSelector' => true, 'showInventory' => true])
<div data-memory-picker>
<p class="app-field-help" data-memory-snapshot>Loading catalog and inventory…</p>
<div class="error" role="alert" data-memory-load-error hidden>Catalog and inventory could not be loaded. Your selections have been kept. <x-app.action type="button" data-memory-retry>Try again</x-app.action></div>
<div class="item-picker-columns" data-memory-demo data-user-id="{{ auth()->id() }}" data-catalog-url="{{ $endpoint }}" data-show-inventory="{{ $showInventory ? '1' : '0' }}">
    <section class="app-content">
        <h2 class="app-heading-flush">Search</h2>
        <div data-memory-search role="search">
            <div class="app-field">
            <div class="app-clearable-field">
                <input id="memory-search" type="text" role="combobox" aria-label="Item, collection or category" placeholder="enter item, collection, or category" aria-autocomplete="list" aria-expanded="false" aria-controls="memory-suggestions" autocomplete="off" spellcheck="false" disabled>
                <x-app.action control="clear" type="button" class="app-clear-input item-picker-clear" aria-label="Clear search" title="Clear search" hidden>✕</x-app.action>
                <ul id="memory-suggestions" class="item-picker-suggestions" role="listbox" aria-label="Matching catalog entries" hidden></ul>
            </div>
            </div>
        </div>
        <p class="app-field-help">Choose an entry or press Enter on a search match to add missing items. Bold green entries are already fully added. Choose them again to remove their items.</p>
        <details class="item-picker-browse" data-memory-browse>
            <summary>Categories and Collections</summary>
            <ul class="item-picker-browse-options" aria-label="Categories and Collections" data-memory-browse-options><li>Loading categories and collections…</li></ul>
        </details>
        <p role="status" data-memory-status></p>
        <p class="warning" role="status" data-memory-persistence hidden></p>
    </section>
    <section class="app-panel-stack" aria-labelledby="memory-results-title">
        <x-app.table-region label="Selected inventory" :nowrap="true" :quantity-fields="true">
            <x-slot:header>
                <x-workflow.heading>
                    <h2 id="memory-results-title">Results</h2>
                    <div class="app-inline-group">
                        <select id="memory-location" aria-label="Inventory location" @if(!$showLocationSelector) hidden @endif disabled><option value="">Loading locations…</option></select>
                        <x-app.action type="button" size="small" data-memory-collapse-all disabled>Collapse</x-app.action>
                        <x-app.action type="button" size="small" data-memory-expand-all disabled>Expand</x-app.action>
                    </div>
                </x-workflow.heading>
                <p class="app-field-help item-picker-total-count" data-memory-count>Loading item count…</p>
            </x-slot:header>
            <table class="item-picker-results">
                <colgroup><col class="table-action-column"><col><col>@if($showInventory)<col class="table-quantity-column">@endif</colgroup>
                <thead><tr><th><span class="sr-only">Remove</span></th><th>Item</th><th>SKU</th>@if($showInventory)<th class="table-numeric" data-memory-location-heading>Location</th>@endif</tr></thead>
                <tbody data-memory-results><tr><td colspan="{{ $showInventory ? 4 : 3 }}">Loading catalog and inventory…</td></tr></tbody>
            </table>
        </x-app.table-region>
    </section>
</div>
</div>
