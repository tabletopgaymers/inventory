@extends('layout')
@section('title', 'Relocation request')
@section('title-status')<span data-relocation-status class="status-badge">{{ $status }}</span>@endsection
@section('page-actions')
<button type="submit" form="relocation-form" name="action" value="save" class="primary app-save-progress" data-relocation-save data-save-shortcut @disabled(!$editable)><span data-save-label>Save progress</span><span class="app-search-spinner" aria-hidden="true"></span></button>
@endsection
@section('content-sections')
<style>
    #relocation-form .relocation-shipping-information { margin-top: .75rem; }
    #relocation-form .material-add-actions { justify-content: flex-end; }
    #relocation-form .material-remove { color: #b91c1c; background: transparent; border: 0; padding: .125rem .25rem; font-weight: 700; }
    #relocation-form .relocation-review-table .projection-value { width: 7%; padding-left: .35rem; padding-right: .35rem; }
    #relocation-form .relocation-review-table td.review-requested { width: 8%; }
    #relocation-form [data-shipping-label] { font-weight: inherit; margin: 0; }
    #relocation-form [data-shipping-label] [data-field-qualifier] { display: none; }
    #relocation-form .tracking-editor-row { display: flex; flex-wrap: wrap; align-items: end; gap: var(--app-density-field-y) 1rem; margin-bottom: var(--app-density-field-y); }
    #relocation-form .tracking-saved-table { width: auto; border: 0; }
    #relocation-form .tracking-saved-scroll { overflow-x: auto; }
    #relocation-form .relocation-ship-table tbody tr:not(.group-row) td:not(:first-child):not(.table-variance) { width: 4%; }
    #relocation-form .tracking-saved-table td { border: 0; background: transparent; padding: var(--app-density-field-y) 1rem var(--app-density-field-y) 0; white-space: nowrap; }
    #relocation-form .tracking-editor-row label { display: flex; flex-direction: column; margin: 0; max-width: 100%; }
    #relocation-form .tracking-editor-row select { width: auto; }
    #relocation-form [data-tracking-name] { width: 18ch; }
    #relocation-form [data-tracking-number] { width: 28ch; }
</style>
<form id="relocation-form" method="post" action="/relocation/work/{{ $token }}" novalidate data-manager="{{ $manager ? 'true' : 'false' }}" data-editable="{{ $editable ? 'true' : 'false' }}" data-status="{{ $status }}" data-can-submit="{{ $submitter ? 'true' : 'false' }}" data-initial-step="{{ $step }}" data-saved="{{ $work['id'] ? 'true' : 'false' }}">
    @csrf
    <input type="hidden" name="revision" value="{{ $work['revision'] }}">
    <x-workflow.tabs prefix="relocation" label="Relocation sections" :active="$step" :tabs="['basic'=>'Basic info','material'=>'Material','review'=>'Review','fulfillment'=>'Fulfillment','tracking'=>'Tracking','ship'=>'Ship','reconcile'=>'Reconcile','confirmation'=>'Confirmation']" />
    <div data-relocation-feedback role="status" tabindex="-1" hidden></div>
    <div data-review-errors class="error" role="alert" hidden></div>
    <div data-ship-errors class="error" role="alert" hidden></div>
    <div data-reconcile-errors class="error" role="alert" hidden></div>
    <div data-confirmation-errors class="error" role="alert" hidden></div>
    <div data-material-notifications @if($step !== 'material') hidden @endif>
        <p data-material-search-feedback class="warning" role="status" hidden></p>
    </div>
    <x-workflow.tab-panel class="app-content" id="relocation-panel-basic" labelledby="relocation-tab-basic" :active="$step === 'basic'">
        <x-workflow.heading><h2>Basic info</h2><button type="submit" name="action" value="next" class="primary" data-basic-next @disabled(!$editable)>Next &gt;</button></x-workflow.heading>
        <fieldset class="app-entry-fieldset" data-basic-fields>
        <label>Title<input id="relocation-title" name="title" required maxlength="255" value="{{ old('title', $work['title']) }}" @readonly(!$editable && !($manager && $status === 'Accepted'))></label>
        @foreach(['source_location_id' => 'Source', 'destination_location_id' => 'Destination'] as $field => $label)
        <label>{{ $label }}<select id="relocation-{{ $field }}" name="{{ $field }}" required @disabled(!$editable)><option value="">Choose {{ strtolower($label) }}</option>
            @foreach($locations as $location)<option value="{{ $location->id }}" @selected((string) old($field, $work[$field]) === (string) $location->id)>{{ $location->name }}</option>@endforeach
        </select></label>
        @endforeach
        <label>Notes (optional)<textarea id="relocation-details" name="details" maxlength="5000" @readonly(!$editable && !($manager && $status === 'Accepted'))>{{ old('details', $work['details']) }}</textarea></label>
        </fieldset>
    </x-workflow.tab-panel>
    <x-workflow.tab-panel id="relocation-panel-material" labelledby="relocation-tab-material" :active="$step === 'material'">
        <div data-material-table-host>
        <div data-material-table @if(!$work['id']) hidden @endif>
            @include('relocation.material-table')
        </div>
        </div>
        <p class="app-content" data-material-unavailable @if($work['id']) hidden @endif>Must complete basic info first</p>
        <div data-material-search-host>
        <div data-material-search-panel data-material-content @if(!$work['id']) hidden @endif>
            <x-app.table-region label="Catalog search results" :nowrap="true" :quantity-fields="true" class="app-list-table-panel">
                <x-slot:header>
                    <h3>Add material</h3>
            <fieldset class="app-entry-fieldset" data-material-search-fields @disabled(!$editable)>
                <x-app.inventory-search :definition="$searchDefinition" :endpoint="'/relocation/work/'.$token.'/search'" instance="relocation-material-search" :script="false" />
            </fieldset>
                </x-slot:header>
                <div data-material-results>@include('relocation.search-results', ['results' => [], 'searched' => false, 'sourceName' => $locations->firstWhere('id', (int) $work['source_location_id'])?->name ?: 'Source', 'destinationName' => $locations->firstWhere('id', (int) $work['destination_location_id'])?->name ?: 'Destination'])</div>
                <x-slot:footer>
                    <div class="app-table-panel-toolbar material-add-actions"><div class="app-actions"><button type="button" class="primary" data-material-add-selected @disabled(!$editable)>Add selected</button></div></div>
                </x-slot:footer>
            </x-app.table-region>
        </div>
        </div>

    </x-workflow.tab-panel>
    <x-workflow.tab-panel id="relocation-panel-review" labelledby="relocation-tab-review" :active="$step === 'review'">
        <div class="app-content">
            <x-workflow.heading>
                <h2>Review relocation request</h2>
                <button type="submit" name="action" value="submit" class="primary" data-relocation-submit disabled>Submit</button>
            </x-workflow.heading>
            <dl class="app-user-details">
                @foreach(['title' => 'Title', 'source_location_id' => 'Source', 'destination_location_id' => 'Destination', 'details' => 'Notes'] as $field => $label)
                <dt>{{ $label }}</dt><dd class="plain-text" data-review-field="{{ $field }}">—</dd>
                @endforeach
            </dl>
        </div>
        <x-app.table-region label="Requested material review" :nowrap="true" :separators="true" numeric="after-first" class="app-list-table-panel">
            <x-slot:header><h2>Requested material</h2></x-slot:header>
            <table class="relocation-review-table"><thead><tr><th>Item</th><th class="table-heading-center" scope="col" data-review-location="source_location_id">Source</th><th class="review-requested table-numeric table-heading-center">Requested</th><th class="table-heading-center" scope="col" data-review-location="destination_location_id">Destination</th></tr></thead><tbody data-review-material></tbody></table>
        </x-app.table-region>
        <template data-review-row><tr><td class="app-grouped-item"></td><td class="projection-value"></td><td class="review-requested table-numeric"></td><td class="projection-value"></td></tr></template>
        <template data-review-group><tr class="group-row"><th colspan="4"></th></tr></template>
    </x-workflow.tab-panel>
    <x-workflow.tab-panel id="relocation-panel-fulfillment" labelledby="relocation-tab-fulfillment" :active="$step === 'fulfillment'">
        <div class="app-content" data-fulfillment-accept-panel @if(!in_array($status, ['New', 'Draft', 'Requested'])) hidden @endif><x-workflow.heading><h2>Fulfillment</h2><button type="submit" name="action" value="accept" class="primary" data-relocation-accept @disabled(!$manager || $status !== 'Requested')>Accept</button></x-workflow.heading>
            <p data-fulfillment-unavailable @if(!in_array($status, ['New', 'Draft', 'Requested'])) hidden @endif>{{ $status === 'Requested' ? 'Must Accept First' : 'Not available' }}</p>
        </div>
        <div data-fulfillment-content @if(in_array($status, ['New', 'Draft', 'Requested'])) hidden @endif>
            <div data-fulfillment-table-host></div>
            <div data-fulfillment-search-host></div>
        </div>

    </x-workflow.tab-panel>
    <x-workflow.tab-panel id="relocation-panel-tracking" labelledby="relocation-tab-tracking" :active="$step === 'tracking'">
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
    </x-workflow.tab-panel>
    <x-workflow.tab-panel id="relocation-panel-ship" labelledby="relocation-tab-ship" :active="$step === 'ship'">
        <div class="app-content">
            <x-workflow.heading><h2>Review shipment</h2><button type="submit" name="action" value="ship" class="primary" data-relocation-ship @disabled(!$manager || $status !== 'Accepted')>Ship</button></x-workflow.heading>
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
        <x-app.table-region label="Shipment material review" :nowrap="true" :separators="true" numeric="after-first" class="app-list-table-panel">
            <x-slot:header><h2>Material</h2></x-slot:header>
            <table class="relocation-ship-table"><thead><tr><th>Item</th><th class="table-heading-center" data-ship-location="source_location_id">Source</th><th class="table-heading-center">Requested</th><th class="table-heading-center" colspan="2" scope="colgroup">Fulfilled</th><th class="table-heading-center" data-ship-location="destination_location_id">Destination</th></tr></thead><tbody data-ship-material></tbody></table>
        </x-app.table-region>
        <template data-ship-row><tr><td class="app-grouped-item"></td><td></td><td></td><td class="table-variance table-joined"></td><td class="ship-fulfilled"></td><td></td></tr></template>
        <template data-ship-group><tr class="group-row"><th colspan="6"></th></tr></template>
        <div class="app-content relocation-shipping-information"><h2>Shipping information</h2><p data-tracking-empty>No tracking saved yet.</p><div class="tracking-saved-scroll"><table class="tracking-saved-table" aria-label="Shipping information" hidden><tbody data-tracking-saved></tbody></table></div></div>
    </x-workflow.tab-panel>
    <x-workflow.tab-panel id="relocation-panel-reconcile" labelledby="relocation-tab-reconcile" :active="$step === 'reconcile'">
        <div class="app-content" data-reconcile-receive-panel @if(in_array($status, ['Received', 'Completed'])) hidden @endif>
            <x-workflow.heading><h2>Reconcile</h2><button type="submit" name="action" value="receive" class="primary" data-relocation-receive @disabled(!$manager || $status !== 'Fulfilled')>Receive</button></x-workflow.heading>
            <p data-reconcile-unavailable>{{ $status === 'Fulfilled' ? 'Acknowledge receipt to enter the quantities actually received.' : 'Not available until shipped.' }}</p>
        </div>
        <div data-reconcile-content @if(!in_array($status, ['Received', 'Completed'])) hidden @endif>
            <div data-reconcile-table-host></div>
            <div data-reconcile-search-host></div>
        </div>
    </x-workflow.tab-panel>
    <x-workflow.tab-panel id="relocation-panel-confirmation" labelledby="relocation-tab-confirmation" :active="$step === 'confirmation'">
        <p class="app-content" data-confirmation-unavailable @if(in_array($status, ['Received', 'Completed'])) hidden @endif>Receive the shipment before confirming its contents.</p>
        <div data-confirmation-content @if(!in_array($status, ['Received', 'Completed'])) hidden @endif>
            <div class="app-content">
                <x-workflow.heading><h2>Confirmation</h2><button type="submit" name="action" value="complete" class="primary" data-relocation-complete @disabled(!$manager || $status !== 'Received')>Complete</button></x-workflow.heading>
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
            <x-app.table-region label="Reconciliation confirmation" :nowrap="true" :separators="true" numeric="after-first" class="app-list-table-panel">
                <x-slot:header><h2>Material</h2></x-slot:header>
                <table class="relocation-ship-table"><thead><tr><th>Item</th><th class="table-heading-center" data-confirmation-location="source_location_id">Source</th><th class="table-heading-center">Requested</th><th class="table-heading-center">Fulfilled</th><th class="table-heading-center" colspan="2" scope="colgroup">Received</th><th class="table-heading-center" data-confirmation-location="destination_location_id">Destination</th></tr></thead><tbody data-confirmation-material></tbody></table>
            </x-app.table-region>
            <div class="app-content relocation-shipping-information"><h2>Shipping information</h2><p data-tracking-empty>No tracking saved yet.</p><div class="tracking-saved-scroll"><table class="tracking-saved-table" aria-label="Shipping information" hidden><tbody data-tracking-saved></tbody></table></div></div>
        </div>
        <template data-confirmation-row><tr><td class="app-grouped-item"></td><td></td><td></td><td></td><td class="table-variance table-joined"></td><td></td><td></td></tr></template>
        <template data-confirmation-group><tr class="group-row"><th colspan="7"></th></tr></template>
    </x-workflow.tab-panel>
</form>
@vite('resources/js/relocation.js')
@endsection
