@extends('layout')
@section('title', 'Prepare purchase')
@section('purchase-progress')
@include('requests.purchase-progress', ['progressRow' => $row, 'progressActive' => 'preparation', 'progressSaveUrl' => $canPrepare ? '/purchases/work/'.$token : null, 'progressKey' => $token ? 'work:'.$token : 'preparation:'.$row->id, 'progressRevision' => $draft['revision'], 'progressVersion' => $draft['version'], 'progressDirty' => $canPrepare && \App\Support\PurchaseProgress::workDirty($draft)])
@endsection
@section('content-sections')
<div class="app-content">
@yield('purchase-progress')
<link rel="stylesheet" href="/request-workflow.css?v={{ substr(hash_file('sha256', public_path('request-workflow.css')), 0, 12) }}">
<p data-purchase-identity>Purchase #{{ $row->id }} · {{ $row->status }} · {{ $row->title ?: 'Untitled request' }}</p>
<p>Requester catalog lines are shown below. Preparation and estimates do not create an order or calculate acquisition costs.</p>
</div><form id="purchase-work" method="post" action="{{ $canPrepare ? '/purchases/work/'.$token : '/purchases/'.$row->id.'/preparation' }}" data-request-form @if($canPrepare) data-purchase-form="work" @endif>
@csrf<input type="hidden" name="_deliberate" value="1"><input type="hidden" name="revision" value="{{ $draft['revision'] }}"><input type="hidden" name="work_version" value="{{ $draft['version'] }}"><input type="hidden" name="progress_step" value="preparation">
<input type="hidden" name="title" value="{{ $draft['values']['title'] ?? '' }}" data-purchase-field><input type="hidden" name="suggested_merchant" value="{{ $draft['values']['suggested_merchant'] ?? '' }}" data-purchase-field>
<fieldset class="app-entry-fieldset" @disabled(!$canPrepare)><legend class="sr-only">Pre-order preparation</legend><div class="app-content"><h2>Pre-order preparation</h2>
<label>Request details (optional)<textarea name="details" maxlength="5000">{{ $draft['values']['details'] ?? '' }}</textarea></label>
<label>Supplier (optional)<select name="supplier_id"><option value="">Not selected</option>@foreach($suppliers as $supplier)@if($supplier->state==='active' || (string)$supplier->id===(string)($draft['values']['supplier_id']??''))<option value="{{ $supplier->id }}" @selected((string)$supplier->id===(string)($draft['values']['supplier_id']??''))>{{ $supplier->name }} ({{ $supplier->state }})</option>@endif @endforeach</select></label>
<label>Receiving storage (optional)<select name="receiving_location_id"><option value="">Not selected</option>@foreach($locations as $location)@if($location->state==='active' || (string)$location->id===(string)($draft['values']['receiving_location_id']??''))<option value="{{ $location->id }}" @selected((string)$location->id===(string)($draft['values']['receiving_location_id']??''))>{{ $location->name }} ({{ $location->state }})</option>@endif @endforeach</select></label>
<label>Planned date (optional)<input type="date" name="planned_date" value="{{ $draft['values']['planned_date'] ?? '' }}"></label><label>Preparation notes (optional)<textarea name="preparation_notes" maxlength="5000">{{ $draft['values']['preparation_notes'] ?? '' }}</textarea></label>
</div>@include('requests.purchase-catalog', ['planning' => true, 'catalogEditable' => $canPrepare, 'tablePanel' => true])
</fieldset><div class="app-content">
<div class="actions"><a href="/purchases/{{ $row->id }}">Back to request</a>@if(\App\Support\RequestValues::manages(auth()->user(), 'procurement') && in_array($row->status, ['Draft','Request'], true) && app(\App\Support\BaselineProbe::class)->schemaState(\Illuminate\Support\Facades\DB::connection())['fulfillmentReady'])<a class="button primary" href="/purchases/{{ $row->id }}/fulfillment/order">Record order</a>@endif</div>
</div></form><script src="/requests.js?v=purchase-wizard-1-sentence-case-1-pending-notice-1" defer></script>
@endsection
