@extends('layout')
@section('title', 'Relocation request')
@section('title-status')<span data-relocation-status class="status-badge">{{ $status }}</span>@endsection
@section('page-actions')
<button type="submit" form="relocation-form" name="action" value="save" class="primary app-save-progress" data-relocation-save data-save-shortcut @disabled(!$editable)><span data-save-label>Save progress</span><span class="app-search-spinner" aria-hidden="true"></span></button>
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
    #relocation-form .relocation-material-table td { vertical-align: middle; }
    #relocation-form .relocation-material-table label, #relocation-form .relocation-material-table input { margin: 0; }
    #relocation-form .relocation-material-table .material-stock-value { text-align: right; }
    #relocation-form .relocation-material-table input, #relocation-form [data-search-quantity] { width: 10ch; max-width: 100%; }
    #relocation-form .relocation-material-table input { text-align: right; }
    #relocation-form .relocation-material-table input[type="checkbox"] { width: auto; }
    #relocation-form .material-add-actions { justify-content: flex-end; }
    #relocation-form .material-remove { color: #b91c1c; background: transparent; border: 0; padding: .125rem .25rem; font-weight: 700; }
    #relocation-form tr.material-quantity-error > td { background: #fff1f2; color: #991b1b; }
    #relocation-form .relocation-negative { color: var(--app-danger, #991b1b); }
    #relocation-form [data-review-material] .projection-value { text-align: right; }
    #relocation-form .relocation-review-table th[data-review-location], #relocation-form .relocation-review-table th.review-requested { text-align: center; }
    #relocation-form .relocation-review-table .projection-value { width: 7%; white-space: nowrap; padding-left: .35rem; padding-right: .35rem; }
    #relocation-form .relocation-review-table td.review-requested { width: 8%; text-align: right; }
    #relocation-form .relocation-review-table th, #relocation-form .relocation-review-table td { white-space: nowrap; overflow-wrap: normal; word-break: normal; }
    #relocation-form .relocation-review-table thead th:not(:last-child),
    #relocation-form .relocation-review-table tbody tr:not(.group-row) td:not(:last-child) { border-right: 1px solid var(--app-border); }
    #relocation-panel-material .relocation-material-table [data-material-location="source_location_id"],
    #relocation-panel-material .relocation-material-table [data-material-location="destination_location_id"],
    #relocation-panel-material .relocation-material-table [data-material-projection] { border-left: 1px solid var(--app-border); }
    #relocation-panel-material .relocation-material-table [data-material-location="source_location_id"],
    #relocation-panel-material .relocation-material-table [data-material-projection="source"] { border-right: 1px solid var(--app-border); }
    #relocation-form .relocation-material-table th, #relocation-form .relocation-material-table td { white-space: nowrap; }
    #relocation-form .relocation-material-table .material-delete-column { width: 1%; }
    #relocation-form .relocation-material-table .material-quantity-column { width: 5%; }
    #relocation-form .relocation-material-table th.material-quantity-heading { text-align: center; }
    #relocation-form .relocation-material-table [data-field-qualifier] { display: none; }
    #relocation-form [data-shipping-label] { font-weight: inherit; margin: 0; }
    #relocation-form [data-shipping-label] [data-field-qualifier] { display: none; }
    #relocation-form .tracking-editor-row { display: flex; flex-wrap: wrap; align-items: end; gap: var(--app-density-field-y) 1rem; margin-bottom: var(--app-density-field-y); }
    #relocation-form .tracking-saved-table { width: auto; border: 0; }
    #relocation-form .tracking-saved-scroll { overflow-x: auto; }
    #relocation-form .relocation-ship-table th, #relocation-form .relocation-ship-table td { white-space: nowrap; }
    #relocation-form .relocation-ship-table th.ship-quantity, #relocation-form .relocation-ship-table th[data-ship-location], #relocation-form .relocation-ship-table th[data-confirmation-location] { text-align: center; }
    #relocation-form .relocation-ship-table td:not(:first-child) { text-align: right; }
    #relocation-form .relocation-ship-table thead th:not(:last-child),
    #relocation-form .relocation-ship-table tbody tr:not(.group-row) td:not(:last-child) { border-right: 1px solid var(--app-border); }
    #relocation-form .relocation-ship-table tbody tr:not(.group-row) td:not(:first-child) { width: 4%; }
    #relocation-form .relocation-ship-table tbody tr:not(.group-row) td.ship-difference { border-right: 0; }
    #relocation-form .relocation-ship-table tbody tr:not(.group-row) td.ship-difference { width: 1%; padding-left: .2rem; padding-right: .35rem; }
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
        @foreach(['basic' => 'Basic info', 'material' => 'Material', 'review' => 'Review', 'fulfillment' => 'Fulfillment', 'tracking' => 'Tracking', 'ship' => 'Ship', 'reconcile' => 'Reconcile', 'confirmation' => 'Confirmation'] as $key => $label)
        <button type="button" role="tab" id="relocation-tab-{{ $key }}" aria-controls="relocation-panel-{{ $key }}" aria-selected="{{ $step === $key ? 'true' : 'false' }}" tabindex="{{ $step === $key ? 0 : -1 }}" data-step="{{ $key }}">{{ $label }}</button>
        @endforeach
    </div>
    </div>
    <div data-relocation-feedback role="status" tabindex="-1" hidden></div>
    <div data-review-errors class="error" role="alert" hidden></div>
    <div data-ship-errors class="error" role="alert" hidden></div>
    <div data-reconcile-errors class="error" role="alert" hidden></div>
    <div data-confirmation-errors class="error" role="alert" hidden></div>
    <div data-material-notifications @if($step !== 'material') hidden @endif>
        <p data-material-search-feedback class="warning" role="status" hidden></p>
    </div>
    <section class="app-content" role="tabpanel" id="relocation-panel-basic" aria-labelledby="relocation-tab-basic" @if($step !== 'basic') hidden @endif>
        <div class="relocation-review-heading"><h2>Basic info</h2><button type="submit" name="action" value="next" class="primary" data-basic-next @disabled(!$editable)>Next &gt;</button></div>
        <fieldset class="app-entry-fieldset" data-basic-fields>
        <label>Title<input id="relocation-title" name="title" required maxlength="255" value="{{ old('title', $work['title']) }}" @readonly(!$editable && !($manager && $status === 'Accepted'))></label>
        @foreach(['source_location_id' => 'Source', 'destination_location_id' => 'Destination'] as $field => $label)
        <label>{{ $label }}<select id="relocation-{{ $field }}" name="{{ $field }}" required @disabled(!$editable)><option value="">Choose {{ strtolower($label) }}</option>
            @foreach($locations as $location)<option value="{{ $location->id }}" @selected((string) old($field, $work[$field]) === (string) $location->id)>{{ $location->name }}</option>@endforeach
        </select></label>
        @endforeach
        <label>Notes (optional)<textarea id="relocation-details" name="details" maxlength="5000" @readonly(!$editable && !($manager && $status === 'Accepted'))>{{ old('details', $work['details']) }}</textarea></label>
        </fieldset>
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
            <table class="relocation-review-table"><thead><tr><th>Item</th><th scope="col" data-review-location="source_location_id">Source</th><th class="review-requested">Requested</th><th scope="col" data-review-location="destination_location_id">Destination</th></tr></thead><tbody data-review-material></tbody></table>
        </x-app.table-region>
        <template data-review-row><tr><td class="app-grouped-item"></td><td class="projection-value"></td><td class="review-requested"></td><td class="projection-value"></td></tr></template>
        <template data-review-group><tr class="group-row"><th colspan="4"></th></tr></template>
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
                <dt>Requester</dt><dd data-ship-requester>{{ $requesterName }}</dd>
                <dt>Requested</dt><dd data-ship-requested-date>{{ $requestedDate ?? '—' }}</dd>
                <dt>Fulfiller</dt><dd data-ship-fulfiller>{{ $fulfillerName }}</dd>
                <dt><label id="relocation-shipping-label" for="relocation-shipping-date" data-shipping-label><span data-shipping-label-text>{{ now()->toDateString() > $shippingDate ? 'Shipped' : 'Shipping' }}</span></label></dt>
                <dd>
                    <input type="date" id="relocation-shipping-date" name="shipping_date" value="{{ $shippingDate }}" data-shipping-date required @readonly(!$manager || $status !== 'Accepted') @if(in_array($status, ['Fulfilled', 'Received', 'Completed', 'Cancelled'])) hidden @endif>
                    <span data-shipping-date-display aria-labelledby="relocation-shipping-label" @if(!in_array($status, ['Fulfilled', 'Received', 'Completed', 'Cancelled'])) hidden @endif>{{ $shippingDate ? \Illuminate\Support\Carbon::parse($shippingDate)->format('d M Y') : '—' }}</span>
                </dd>
            </dl>
        </div>
        <x-app.table-region label="Shipment material review" class="app-list-table-panel">
            <x-slot:header><h2>Material</h2></x-slot:header>
            <table class="relocation-ship-table"><thead><tr><th>Item</th><th data-ship-location="source_location_id">Source</th><th class="ship-quantity">Requested</th><th class="ship-quantity" colspan="2" scope="colgroup">Fulfilled</th><th data-ship-location="destination_location_id">Destination</th></tr></thead><tbody data-ship-material></tbody></table>
        </x-app.table-region>
        <template data-ship-row><tr><td class="app-grouped-item"></td><td></td><td></td><td class="ship-difference"></td><td class="ship-fulfilled"></td><td></td></tr></template>
        <template data-ship-group><tr class="group-row"><th colspan="6"></th></tr></template>
        <div class="app-content relocation-shipping-information"><h2>Shipping information</h2><p data-tracking-empty>No tracking saved yet.</p><div class="tracking-saved-scroll"><table class="tracking-saved-table" aria-label="Shipping information" hidden><tbody data-tracking-saved></tbody></table></div></div>
    </section>
    <section role="tabpanel" id="relocation-panel-reconcile" aria-labelledby="relocation-tab-reconcile" @if($step !== 'reconcile') hidden @endif>
        <div class="app-content" data-reconcile-receive-panel @if(in_array($status, ['Received', 'Completed'])) hidden @endif>
            <div class="relocation-review-heading"><h2>Reconcile</h2><button type="submit" name="action" value="receive" class="primary" data-relocation-receive @disabled(!$manager || $status !== 'Fulfilled')>Receive</button></div>
            <p data-reconcile-unavailable>{{ $status === 'Fulfilled' ? 'Acknowledge receipt to enter the quantities actually received.' : 'Not available until shipped.' }}</p>
        </div>
        <div data-reconcile-content @if(!in_array($status, ['Received', 'Completed'])) hidden @endif>
            <div data-reconcile-table-host></div>
            <div data-reconcile-search-host></div>
        </div>
    </section>
    <section role="tabpanel" id="relocation-panel-confirmation" aria-labelledby="relocation-tab-confirmation" @if($step !== 'confirmation') hidden @endif>
        <p class="app-content" data-confirmation-unavailable @if(in_array($status, ['Received', 'Completed'])) hidden @endif>Receive the shipment before confirming its contents.</p>
        <div data-confirmation-content @if(!in_array($status, ['Received', 'Completed'])) hidden @endif>
            <div class="app-content">
                <div class="relocation-review-heading"><h2>Confirmation</h2><button type="submit" name="action" value="complete" class="primary" data-relocation-complete @disabled(!$manager || $status !== 'Received')>Complete</button></div>
                <dl class="app-user-details">
                    @foreach(['title' => 'Title', 'source_location_id' => 'Source', 'destination_location_id' => 'Destination', 'details' => 'Notes'] as $field => $label)
                    <dt>{{ $label }}</dt><dd class="plain-text" data-confirmation-field="{{ $field }}">—</dd>
                    @endforeach
                    <dt>Requester</dt><dd>{{ $requesterName }}</dd>
                    <dt>Requested</dt><dd data-confirmation-requested-date>{{ $requestedDate ?? '—' }}</dd>
                    <dt>Fulfiller</dt><dd data-confirmation-fulfiller>{{ $fulfillerName }}</dd>
                    <dt data-confirmation-shipping-label>Shipping</dt><dd data-confirmation-shipping-date>—</dd>
                </dl>
            </div>
            <x-app.table-region label="Reconciliation confirmation" class="app-list-table-panel">
                <x-slot:header><h2>Material</h2></x-slot:header>
                <table class="relocation-ship-table"><thead><tr><th>Item</th><th data-confirmation-location="source_location_id">Source</th><th class="ship-quantity">Requested</th><th class="ship-quantity">Fulfilled</th><th class="ship-quantity" colspan="2" scope="colgroup">Received</th><th data-confirmation-location="destination_location_id">Destination</th></tr></thead><tbody data-confirmation-material></tbody></table>
            </x-app.table-region>
            <div class="app-content relocation-shipping-information"><h2>Shipping information</h2><p data-tracking-empty>No tracking saved yet.</p><div class="tracking-saved-scroll"><table class="tracking-saved-table" aria-label="Shipping information" hidden><tbody data-tracking-saved></tbody></table></div></div>
        </div>
        <template data-confirmation-row><tr><td class="app-grouped-item"></td><td></td><td></td><td></td><td class="ship-difference"></td><td></td><td></td></tr></template>
        <template data-confirmation-group><tr class="group-row"><th colspan="7"></th></tr></template>
    </section>
</form>
<script src="/relocation.js?v={{ substr(hash_file('sha256', public_path('relocation.js')), 0, 12) }}" defer></script>
@endsection
