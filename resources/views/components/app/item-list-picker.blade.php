@props(['endpoint' => '/style-guide/browser-memory-demo/data', 'showLocationSelector' => true, 'showInventory' => true])
<div data-memory-picker>
<p class="app-field-help" data-memory-snapshot>Loading catalog and inventory…</p>
<div class="error" role="alert" data-memory-load-error hidden>Catalog and inventory could not be loaded. Your selections have been kept. <x-app.action type="button" data-memory-retry>Try again</x-app.action></div>
<div class="item-picker-columns" data-memory-demo data-user-id="{{ auth()->id() }}" data-catalog-url="{{ $endpoint }}" data-show-inventory="{{ $showInventory ? '1' : '0' }}">
    <section class="app-content">
        <h2 class="app-heading-flush">Search</h2>
        <div data-memory-search role="search">
            <x-app.field label="Item, collection or category" for="memory-search">
            <div class="app-clearable-field">
                <input id="memory-search" type="text" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="memory-suggestions" autocomplete="off" spellcheck="false" disabled>
                <x-app.action control="clear" type="button" class="app-clear-input item-picker-clear" aria-label="Clear search" title="Clear search" hidden>✕</x-app.action>
                <ul id="memory-suggestions" class="item-picker-suggestions" role="listbox" aria-label="Matching catalog entries" hidden></ul>
            </div>
            </x-app.field>
        </div>
        <p class="app-field-help">Search names, including an item's category and collection. Choose a match or press Enter to add the first match. A category or collection adds all its items. Already selected items are included only once.</p>
        <p role="status" data-memory-status></p>
        <p class="warning" role="status" data-memory-persistence hidden></p>
    </section>
    <section class="app-panel-stack" aria-labelledby="memory-results-title">
        <x-app.table-region label="Selected inventory" :nowrap="true" :quantity-fields="true">
            <x-slot:header>
                <x-workflow.heading>
                    <h2 id="memory-results-title">Results</h2>
                    <select id="memory-location" aria-label="Inventory location" @if(!$showLocationSelector) hidden @endif disabled><option value="">Loading locations…</option></select>
                </x-workflow.heading>
                <p class="app-field-help" data-memory-count>0 items</p>
            </x-slot:header>
            <table>
                <colgroup><col class="table-action-column"><col><col>@if($showInventory)<col class="table-quantity-column">@endif</colgroup>
                <thead><tr><th><span class="sr-only">Remove</span></th><th>Item</th><th>SKU</th>@if($showInventory)<th class="table-numeric" data-memory-location-heading>Location</th>@endif</tr></thead>
                <tbody data-memory-results><tr><td colspan="{{ $showInventory ? 4 : 3 }}">Loading catalog and inventory…</td></tr></tbody>
            </table>
        </x-app.table-region>
    </section>
</div>
</div>
