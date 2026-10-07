@extends('layout')

@section('title', $row->title ?: 'Untitled request')

@section('title-status')<span class="status-badge request-status {{ strtolower($row->status) }}">{{ $row->status }}</span>@endsection

@section('content')

<p>Purchase request #{{ $row->id }} · Owner: {{ trim($owner->first_name.' '.$owner->last_name) ?: 'Account '.$owner->id }}</p>

<div class="actions"><a href="/purchases">All purchase requests</a><a class="button" href="/purchases/{{ $row->id }}/preparation">{{ $manager && in_array($row->status,['Draft','Request']) ? 'Prepare purchase' : 'View preparation' }}</a>@if($editable)<a class="button" href="/purchases/{{ $row->id }}/edit">Edit details</a>@endif</div>

<dl class="item-metadata"><dt>Suggested merchant</dt><dd>{{ $row->suggested_merchant ?: '—' }}</dd><dt>Request details</dt><dd>{{ $row->details ?: '—' }}</dd></dl>

<form method="post" action="/purchases/{{ $row->id }}/transition" class="actions">@csrf<input type="hidden" name="_deliberate" value="1"><input type="hidden" name="revision" value="{{ old('revision',$row->revision) }}">

@if($row->status === 'Draft' && $submitter)<button name="action" value="submit" class="primary">Submit Request</button>@endif

@if($row->status === 'Request' && $manager)<button name="action" value="return">Return to Draft</button>@endif

@if(in_array($row->status,['Draft','Request']) && ($manager || ($row->status==='Draft' && $row->owner_id === auth()->id())))<button name="action" value="cancel" class="discard" data-confirm="Cancel this request? Its details, notes and activity will be retained.">Cancel request</button>@endif</form>

@if($fulfillmentReady)
<div class="actions"><a class="button" href="/purchases/{{ $row->id }}/fulfillment/order?view=1">View invoice</a>
@if($manager && in_array($row->status,['Draft','Request','Ordered','Shipped']))<a class="button" href="/purchases/{{ $row->id }}/fulfillment/order">{{ in_array($row->status,['Ordered','Shipped']) ? 'Edit order' : 'Record order' }}</a>@endif
@if($manager && in_array($row->status,['Ordered','Shipped']))<a class="button primary" href="/purchases/{{ $row->id }}/fulfillment/receipt">Receive purchase</a>@endif</div>
@if($manager && in_array($row->status,['Ordered','Shipped']))<form method="post" action="/purchases/{{ $row->id }}/fulfillment-transition" class="actions" data-request-form>@csrf<input type="hidden" name="_deliberate" value="1"><input type="hidden" name="revision" value="{{ $row->revision }}">
@if($row->status==='Ordered')<label>Shipped date<input type="date" name="shipped_date" value="{{ old('shipped_date') }}"></label><button name="action" value="shipped">Mark Shipped</button>@endif
<button name="action" value="backward">Return to {{ ['Request'=>'Draft','Ordered'=>'Request','Shipped'=>'Ordered'][$row->status] }}</button>
@if(in_array($row->status,['Ordered','Shipped']))<button name="action" value="cancel" class="discard" data-confirm="Cancel this order and clear only its pending expectation? Held stock and costs stay unchanged.">Cancel order</button>@endif</form>@endif
@else<p>Ordering and receipt actions need guarded fulfillment preparation.</p>@endif

<h2>Permanent notes</h2><form method="post" action="/purchases/{{ $row->id }}/notes" data-request-form>@csrf<input type="hidden" name="_deliberate" value="1"><label>Add a permanent note<textarea name="note" maxlength="5000" required>{{ old('note') }}</textarea></label><div class="actions"><button>Add note</button></div></form>

<ol class="request-notes">@forelse($notes as $note)<li><p class="plain-text">{{ $note->body }}</p><small>{{ $note->actor_name }} · {{ \App\Support\DisplayDates::timestamp($note->occurred_at) }}</small></li>@empty<li>No notes yet.</li>@endforelse</ol>

@include('requests.activity')
<script src="/requests.js" defer></script>

@endsection
