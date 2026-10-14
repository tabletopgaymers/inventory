@extends('layout')
@section('title', 'Relocation request')
@section('title-status')<span data-relocation-status class="status-badge">{{ $status }}</span>@endsection
@section('page-actions')
<button type="submit" form="relocation-form" name="action" value="save" class="primary app-save-progress" data-relocation-save @disabled(!$editable)><span data-save-label>Save progress</span><span class="app-search-spinner" aria-hidden="true"></span></button>
@endsection
@section('content-sections')
<style>
    .relocation-tabs { display: flex; flex-wrap: wrap; gap: .25rem; }
    .relocation-tab-panel { margin-bottom: .75rem; }
    #relocation-form .relocation-shipping-information { margin-top: .75rem; }
    #relocation-form .relocation-review-heading { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: var(--app-density-field-y); }
    #relocation-form .relocation-review-heading h2 { margin: 0; }
    .relocation-tabs button { border-radius: .5rem .5rem 0 0; }
    #relocation-form .relocation-tabs button[aria-selected="true"] { background: #e5dfef; color: var(--app-text, #231c2f); border: 2px solid var(--app-primary, #663399); }
    #relocation-form [data-material-lines] td { vertical-align: middle; }
    #relocation-form [data-material-lines] label, #relocation-form [data-material-lines] input { margin: 0; }
    #relocation-form .relocation-material-table input, #relocation-form [data-search-quantity] { width: 10ch; max-width: 100%; }
    #relocation-form .relocation-material-table input { text-align: right; }
    #relocation-form .material-add-actions { justify-content: flex-end; }
    #relocation-form .material-remove { color: #b91c1c; background: transparent; border: 0; padding: .125rem .25rem; font-weight: 700; }
    #relocation-form tr.material-quantity-error > td { background: #fff1f2; color: #991b1b; }
    #relocation-form .relocation-negative { color: var(--app-danger, #991b1b); }
    #relocation-form [data-review-material] .projection-value { text-align: right; }
    #relocation-form [data-review-material] .projection-arrow { text-align: center; }
    #relocation-form .relocation-review-table th[data-review-location], #relocation-form .relocation-review-table th.review-requested { text-align: center; }
    #relocation-form .relocation-review-table .projection-value { width: 7%; white-space: nowrap; padding-left: .35rem; padding-right: .35rem; }
    #relocation-form .relocation-review-table .projection-arrow { width: 2%; padding-left: .2rem; padding-right: .2rem; }
    #relocation-form .relocation-review-table td.review-requested { width: 8%; text-align: center; }
    #relocation-form .relocation-review-table th, #relocation-form .relocation-review-table td { white-space: nowrap; overflow-wrap: normal; word-break: normal; }
    #relocation-form .relocation-material-table th, #relocation-form .relocation-material-table td { white-space: nowrap; }
    #relocation-form .relocation-material-table .material-delete-column { width: 1%; }
    #relocation-form .relocation-material-table .material-quantity-column { width: 5%; }
    #relocation-form .relocation-material-table th.material-quantity-heading { text-align: center; }
    #relocation-form .relocation-material-table [data-field-qualifier] { display: none; }
    #relocation-form .tracking-editor-row { display: flex; flex-wrap: wrap; align-items: end; gap: var(--app-density-field-y) 1rem; margin-bottom: var(--app-density-field-y); }
    #relocation-form .tracking-saved-table { width: auto; border: 0; }
    #relocation-form .tracking-saved-scroll { overflow-x: auto; }
    #relocation-form .relocation-ship-table th, #relocation-form .relocation-ship-table td { white-space: nowrap; }
    #relocation-form .relocation-ship-table th.ship-quantity, #relocation-form .relocation-ship-table th[data-ship-location] { text-align: center; }
    #relocation-form .relocation-ship-table .projection-value { width: 7%; text-align: right; padding-left: .35rem; padding-right: .35rem; }
    #relocation-form .relocation-ship-table .projection-arrow { width: 2%; text-align: center; padding-left: .2rem; padding-right: .2rem; }
    #relocation-form .relocation-ship-table td.ship-quantity { width: 5%; text-align: right; }
    #relocation-form .relocation-ship-table td.ship-requested { width: 5%; text-align: right; padding-right: .35rem; }
    #relocation-form .relocation-ship-table td.ship-difference { width: 1%; text-align: right; padding-left: .2rem; padding-right: .35rem; }
    #relocation-form .relocation-ship-table thead th:not(:last-child),
    #relocation-form .relocation-ship-table tbody tr:not(.group-row) td:nth-child(1),
    #relocation-form .relocation-ship-table tbody tr:not(.group-row) td:nth-child(3),
    #relocation-form .relocation-ship-table tbody tr:not(.group-row) td:nth-child(6),
    #relocation-form .relocation-ship-table tbody tr:not(.group-row) td:nth-child(7) { border-right: 1px solid var(--app-border); }
    #relocation-form .relocation-ship-table tbody tr:not(.group-row) td:not(:first-child) { width: 4%; }
    #relocation-form .ship-difference-positive { color: #166534; }
    #relocation-form .ship-difference-negative { color: #991b1b; }
    #relocation-form .tracking-saved-table td { border: 0; background: transparent; padding: var(--app-density-field-y) 1rem var(--app-density-field-y) 0; white-space: nowrap; }
    #relocation-form .tracking-editor-row label { display: flex; flex-direction: column; margin: 0; max-width: 100%; }
    #relocation-form .tracking-editor-row select { width: auto; }
    #relocation-form [data-tracking-name] { width: 18ch; }
    #relocation-form [data-tracking-number] { width: 28ch; }
</style>
<form id="relocation-form" method="post" action="/relocation/work/{{ $token }}" novalidate data-manager="{{ $manager ? 'true' : 'false' }}" data-editable="{{ $editable ? 'true' : 'false' }}" data-status="{{ $status }}" data-can-submit="{{ $submitter ? 'true' : 'false' }}" data-initial-step="{{ $step }}" data-saved="{{ $work['id'] ? 'true' : 'false' }}">
    @csrf
    <input type="hidden" name="revision" value="{{ $work['revision'] }}">
    <div class="app-content relocation-tab-panel">
    <div class="relocation-tabs" role="tablist" aria-label="Relocation sections">
        @foreach(['basic' => 'Basic info', 'material' => 'Material', 'review' => 'Review', 'fulfillment' => 'Fulfillment', 'tracking' => 'Tracking', 'ship' => 'Ship', 'reconcile' => 'Reconcile'] as $key => $label)
        <button type="button" role="tab" id="relocation-tab-{{ $key }}" aria-controls="relocation-panel-{{ $key }}" aria-selected="{{ $step === $key ? 'true' : 'false' }}" tabindex="{{ $step === $key ? 0 : -1 }}" data-step="{{ $key }}">{{ $label }}</button>
        @endforeach
    </div>
    </div>
    <div data-relocation-feedback role="status" tabindex="-1" hidden></div>
    <div data-review-errors class="error" role="alert" hidden></div>
    <div data-ship-errors class="error" role="alert" hidden></div>
    <div data-material-notifications @if($step !== 'material') hidden @endif>
        <p data-material-search-feedback class="warning" role="status" hidden></p>
    </div>
    <section class="app-content" role="tabpanel" id="relocation-panel-basic" aria-labelledby="relocation-tab-basic" @if($step !== 'basic') hidden @endif>
        <h2>Basic info</h2>
        <fieldset class="app-entry-fieldset" @disabled(!$editable)>
        <label>Title<input id="relocation-title" name="title" required maxlength="255" value="{{ old('title', $work['title']) }}"></label>
        @foreach(['source_location_id' => 'Source', 'destination_location_id' => 'Destination'] as $field => $label)
        <label>{{ $label }}<select id="relocation-{{ $field }}" name="{{ $field }}" required><option value="">Choose {{ strtolower($label) }}</option>
            @foreach($locations as $location)<option value="{{ $location->id }}" @selected((string) old($field, $work[$field]) === (string) $location->id)>{{ $location->name }}</option>@endforeach
        </select></label>
        @endforeach
        <label>Notes (optional)<textarea id="relocation-details" name="details" maxlength="5000">{{ old('details', $work['details']) }}</textarea></label>
        </fieldset>
        <div class="actions"><a href="/relocation">Back to requests</a><button type="submit" name="action" value="next" class="primary" @disabled(!$editable)>Next &gt;</button></div>
    </section>
    <section role="tabpanel" id="relocation-panel-material" aria-labelledby="relocation-tab-material" @if($step !== 'material') hidden @endif>
        <div data-material-table-host>
        <div data-material-table @if(!$work['id']) hidden @endif>
            @include('relocation.material-table')
        </div>
        </div>
        <p class="app-content" data-material-unavailable @if($work['id']) hidden @endif>Must complete basic info first</p>
        <div data-material-search-host>
        <div data-material-search-panel data-material-content @if(!$work['id']) hidden @endif>
            <x-app.table-region label="Catalog search results" class="app-list-table-panel">
                <x-slot:header>
                    <h3>Add material</h3>
            <fieldset class="app-entry-fieldset" data-material-search-fields @disabled(!$editable)>
                <div class="search-actions"><label>Search inventory<input data-material-search maxlength="255"></label>
                    <button type="button" class="primary" aria-busy="false" data-material-search-action="search">Search<span class="app-search-spinner" aria-hidden="true"></span></button>
                    <span class="sr-only" role="status" data-material-search-loading></span>
                    <button type="button" data-material-search-action="all">Show all</button>
                    <button type="button" data-material-search-action="reset">Reset</button>
                </div>
                <details data-material-filters><summary>Filters <span data-material-filter-summary>— 0 selected</span></summary><div class="collection-checklist">
                    @foreach($collections as $collection)<label class="app-choice"><input type="checkbox" data-material-collection value="{{ $collection->id }}">{{ $collection->category_name }} : {{ $collection->name }}</label>@endforeach
                </div><button type="button" data-material-clear-filters>Clear filters</button></details>
            </fieldset>
                </x-slot:header>
                <div data-material-results>@include('relocation.search-results', ['results' => [], 'searched' => false, 'sourceName' => $locations->firstWhere('id', (int) $work['source_location_id'])?->name ?: 'Source', 'destinationName' => $locations->firstWhere('id', (int) $work['destination_location_id'])?->name ?: 'Destination'])</div>
                <x-slot:footer>
                    <div class="app-table-panel-toolbar material-add-actions"><div class="app-actions"><button type="button" class="primary" data-material-add-selected @disabled(!$editable)>Add selected</button></div></div>
                </x-slot:footer>
            </x-app.table-region>
        </div>
        </div>

    </section>
    <section role="tabpanel" id="relocation-panel-review" aria-labelledby="relocation-tab-review" @if($step !== 'review') hidden @endif>
        <div class="app-content">
            <div class="relocation-review-heading">
                <h2>Review relocation request</h2>
                <button type="submit" name="action" value="submit" class="primary" data-relocation-submit disabled>Submit</button>
            </div>
            <dl class="app-user-details">
                @foreach(['title' => 'Title', 'source_location_id' => 'Source', 'destination_location_id' => 'Destination', 'details' => 'Notes'] as $field => $label)
                <dt>{{ $label }}</dt><dd class="plain-text" data-review-field="{{ $field }}">—</dd>
                @endforeach
            </dl>
        </div>
        <x-app.table-region label="Requested material review" class="app-list-table-panel">
            <x-slot:header><h2>Requested material</h2></x-slot:header>
            <table class="relocation-review-table"><thead><tr><th>Item</th><th colspan="3" scope="colgroup" data-review-location="source_location_id">Source</th><th class="review-requested">Requested</th><th colspan="3" scope="colgroup" data-review-location="destination_location_id">Destination</th></tr></thead><tbody data-review-material></tbody></table>
        </x-app.table-region>
        <template data-review-row><tr><td class="app-grouped-item"></td><td class="projection-value"></td><td class="projection-arrow">→</td><td class="projection-value"></td><td class="review-requested"></td><td class="projection-value"></td><td class="projection-arrow">→</td><td class="projection-value"></td></tr></template>
        <template data-review-group><tr class="group-row"><th colspan="8"></th></tr></template>
    </section>
    <section role="tabpanel" id="relocation-panel-fulfillment" aria-labelledby="relocation-tab-fulfillment" @if($step !== 'fulfillment') hidden @endif>
        <div class="app-content" data-fulfillment-accept-panel @if(!in_array($status, ['New', 'Draft', 'Requested'])) hidden @endif><div class="relocation-review-heading"><h2>Fulfillment</h2><button type="submit" name="action" value="accept" class="primary" data-relocation-accept @disabled(!$manager || $status !== 'Requested')>Accept</button></div>
            <p data-fulfillment-unavailable @if(!in_array($status, ['New', 'Draft', 'Requested'])) hidden @endif>{{ $status === 'Requested' ? 'Must Accept First' : 'Not available' }}</p>
        </div>
        <div data-fulfillment-content @if(in_array($status, ['New', 'Draft', 'Requested'])) hidden @endif>
            <div data-fulfillment-table-host></div>
            <div data-fulfillment-search-host></div>
        </div>

    </section>
    <section role="tabpanel" id="relocation-panel-tracking" aria-labelledby="relocation-tab-tracking" @if($step !== 'tracking') hidden @endif>
        <p class="app-content" data-tracking-unavailable @if(in_array($status, ['Accepted', 'Fulfilled', 'Received', 'Completed', 'Cancelled'])) hidden @endif>Not available</p>
        <div data-tracking-content @if(!in_array($status, ['Accepted', 'Fulfilled', 'Received', 'Completed', 'Cancelled'])) hidden @endif>
            <div class="app-content"><h2>Saved tracking</h2><p data-tracking-empty>No tracking saved yet.</p><div class="tracking-saved-scroll"><table class="tracking-saved-table" aria-label="Saved tracking" hidden><tbody data-tracking-saved></tbody></table></div></div>
            <div class="app-content">
            <fieldset class="app-entry-fieldset" data-tracking-fields @disabled(!$manager || $status === 'Cancelled')>
                <div data-tracking-lines></div>
                <button type="button" data-tracking-add>Add package</button>
            </fieldset>
            </div>
        </div>
        <template data-tracking-row>
            <div class="tracking-editor-row">
                <label>Name<input data-tracking-name maxlength="255"></label>
                <label>Shipper<select data-tracking-carrier><option value="">Choose a shipper</option><option value="USPS">US Postal</option><option value="UPS">UPS</option><option value="FedEx">FedEx</option><option value="Other">Other</option></select></label>
                <label>Tracking (optional)<input data-tracking-number maxlength="255"></label>
                <button type="button" class="material-remove" data-tracking-remove aria-label="Delete package">✕</button>
            </div>
        </template>
        <template data-tracking-saved-row><tr><td><strong data-saved-package-name></strong></td><td data-saved-shipper></td><td data-saved-tracker></td></tr></template>
        <script type="application/json" data-tracking-values>@json($tracking)</script>
    </section>
    <section role="tabpanel" id="relocation-panel-ship" aria-labelledby="relocation-tab-ship" @if($step !== 'ship') hidden @endif>
        <div class="app-content">
            <div class="relocation-review-heading"><h2>Review shipment</h2><button type="submit" name="action" value="ship" class="primary" data-relocation-ship @disabled(!$manager || $status !== 'Accepted')>Ship</button></div>
            <dl class="app-user-details">
                @foreach(['title' => 'Title', 'source_location_id' => 'Source', 'destination_location_id' => 'Destination', 'details' => 'Notes'] as $field => $label)
                <dt>{{ $label }}</dt><dd class="plain-text" data-ship-field="{{ $field }}">—</dd>
                @endforeach
            </dl>
        </div>
        <x-app.table-region label="Shipment material review" class="app-list-table-panel">
            <x-slot:header><h2>Material</h2></x-slot:header>
            <table class="relocation-ship-table"><thead><tr><th>Item</th><th class="ship-quantity" colspan="2" scope="colgroup">Requested</th><th colspan="3" scope="colgroup" data-ship-location="source_location_id">Source</th><th class="ship-quantity">Fulfilled</th><th colspan="3" scope="colgroup" data-ship-location="destination_location_id">Destination</th></tr></thead><tbody data-ship-material></tbody></table>
        </x-app.table-region>
        <template data-ship-row><tr><td class="app-grouped-item"></td><td class="ship-requested"></td><td class="ship-difference"></td><td class="projection-value"></td><td class="projection-arrow">→</td><td class="projection-value"></td><td class="ship-quantity"></td><td class="projection-value"></td><td class="projection-arrow">→</td><td class="projection-value"></td></tr></template>
        <template data-ship-group><tr class="group-row"><th colspan="10"></th></tr></template>
        <div class="app-content relocation-shipping-information"><h2>Shipping information</h2><p data-tracking-empty>No tracking saved yet.</p><div class="tracking-saved-scroll"><table class="tracking-saved-table" aria-label="Shipping information" hidden><tbody data-tracking-saved></tbody></table></div></div>
    </section>
    @foreach(['reconcile' => 'Reconcile'] as $key => $label)
    <section class="app-content" role="tabpanel" id="relocation-panel-{{ $key }}" aria-labelledby="relocation-tab-{{ $key }}" @if($step !== $key) hidden @endif>
        <h2>{{ $label }}</h2><p data-stage-placeholder>{{ !$work['id'] && in_array($key, ['material', 'review']) ? 'Must complete basic info first' : 'This section will be added in a later step.' }}</p>
    </section>
    @endforeach
</form>
<script src="/relocation.js?v={{ substr(hash_file('sha256', public_path('relocation.js')), 0, 12) }}" defer></script>
@endsection
