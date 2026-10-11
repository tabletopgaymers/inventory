@extends('layout')

@section('title', $row->title ?: 'Untitled request')

@section('title-status')<span class="status-badge request-status {{ strtolower($row->status) }}">{{ $row->status }}</span>@endsection

@section('purchase-progress')
@include('requests.purchase-progress', ['progressRow' => $row, 'progressActive' => match ($row->status) { 'Draft' => 'review', 'Request' => 'preparation', 'Ordered', 'Shipped' => 'receive', 'Received' => 'complete', default => '' }, 'progressKey' => 'show:'.$row->id])
@endsection
@section('content')

<p>Purchase request #{{ $row->id }} · Owner: {{ trim($owner->first_name.' '.$owner->last_name) ?: 'Account '.$owner->id }}</p>

<a href="/purchases">All purchase requests</a>
<section id="purchase-review"><h2>{{ $row->status === 'Draft' ? 'Review and submit' : 'Next action' }}</h2>
<p>Saved {{ \App\Support\DisplayDates::timestamp($row->updated_at) }} · {{ match($row->status) { 'Draft' => 'Review your saved request below, then submit when ready. Saving alone does not submit.', 'Request' => 'Submitted. Procurement can prepare and record an order.', 'Ordered', 'Shipped' => 'An order is recorded. Procurement can record the final actual receipt.', 'Received' => 'Final receipt recorded. Request details are read-only; permanent notes remain available.', default => 'This request is cancelled. Saved details and history are retained.' } }}</p>
@if(!$editable && !$manager)<p>You can view the saved request; editing and fulfillment follow the existing owner and management permissions.</p>@endif
@if($editable)<a class="button" href="/purchases/{{ $row->id }}/edit">Edit details</a>@endif
</section>

<dl class="item-metadata" id="purchase-request"><dt>Suggested merchant</dt><dd>{{ $row->suggested_merchant ?: 'Not provided' }}</dd><dt>Request details</dt><dd>{{ $row->details ?: 'Not provided' }}</dd></dl>
<div id="purchase-items">@include('requests.purchase-catalog', ['rows' => $requestRows, 'draft' => ['values' => ['lines' => array_map(fn ($quantity) => ['quantity' => $quantity], $requestLines)]], 'planning' => false, 'catalogEditable' => false])</div>

<form method="post" action="/purchases/{{ $row->id }}/transition" class="actions">@csrf<input type="hidden" name="_deliberate" value="1"><input type="hidden" name="revision" value="{{ old('revision',$row->revision) }}">

@if($row->status === 'Draft' && $submitter)<button name="action" value="submit" class="primary">Submit Request</button>@endif

@if($row->status === 'Request' && $manager)<button name="action" value="return">Return to Draft</button>@endif

@if(in_array($row->status,['Draft','Request']) && ($manager || ($row->status==='Draft' && $row->owner_id === auth()->id())))</form><form method="post" action="/purchases/{{ $row->id }}/transition" class="actions">@csrf<input type="hidden" name="_deliberate" value="1"><input type="hidden" name="revision" value="{{ $row->revision }}"><button name="action" value="cancel" class="discard" data-confirm="Cancel this request? Its details, notes and activity will be retained.">Cancel request</button>@endif</form>

<h2>Purchase planning and fulfillment</h2>
<p>Prepare purchase: optional planning and estimates. Record order: ordered expectations. Receive purchase: final actual receipt and costs. These stages are separate from request intake.</p>
<a class="button" href="/purchases/{{ $row->id }}/preparation">{{ $manager && in_array($row->status,['Draft','Request']) ? 'Prepare purchase' : 'View preparation' }}</a>
@if($fulfillmentReady)
<div class="actions"><a class="button" href="/purchases/{{ $row->id }}/fulfillment/order?view=1">View invoice</a>
@if($manager && in_array($row->status,['Draft','Request','Ordered','Shipped']))<a class="button" href="/purchases/{{ $row->id }}/fulfillment/order">{{ in_array($row->status,['Ordered','Shipped']) ? 'Edit order' : 'Record order' }}</a>@endif
@if($manager && in_array($row->status,['Ordered','Shipped']))<a class="button primary" href="/purchases/{{ $row->id }}/fulfillment/receipt">Receive purchase</a>@endif</div>
@if($manager && in_array($row->status,['Ordered','Shipped']))<form method="post" action="/purchases/{{ $row->id }}/fulfillment-transition" class="actions" id="purchase-shipping" data-request-form data-purchase-retain-form>@csrf<input type="hidden" name="_deliberate" value="1"><input type="hidden" name="revision" value="{{ $row->revision }}">
@if($row->status==='Ordered')<label>Shipped date<input type="date" name="shipped_date" value="{{ old('shipped_date') }}"></label><button name="action" value="shipped">Mark Shipped</button>@endif
<button name="action" value="backward">Return to {{ ['Request'=>'Draft','Ordered'=>'Request','Shipped'=>'Ordered'][$row->status] }}</button>
@if(in_array($row->status,['Ordered','Shipped']))</form><form method="post" action="/purchases/{{ $row->id }}/fulfillment-transition" class="actions">@csrf<input type="hidden" name="_deliberate" value="1"><input type="hidden" name="revision" value="{{ $row->revision }}"><button name="action" value="cancel" class="discard" data-confirm="Cancel this order and clear only its pending expectation? Held stock and costs stay unchanged.">Cancel order</button>@endif</form>@endif
@else<p>Ordering and receipt actions need guarded fulfillment preparation.</p>@endif

<h2>Permanent notes</h2><form method="post" action="/purchases/{{ $row->id }}/notes" id="purchase-note" data-request-form data-purchase-retain-form>@csrf<input type="hidden" name="_deliberate" value="1"><label>Add a permanent note<textarea name="note" maxlength="5000" required>{{ old('note') }}</textarea></label><div class="actions"><button>Add note</button></div></form>

<ol class="request-notes">@forelse($notes as $note)<li><p class="plain-text">{{ $note->body }}</p><small>{{ $note->actor_name }} · {{ \App\Support\DisplayDates::timestamp($note->occurred_at) }}</small></li>@empty<li>No notes yet.</li>@endforelse</ol>

@include('requests.activity')
<script src="/requests.js?v=purchase-progress-2" defer></script>

@endsection
