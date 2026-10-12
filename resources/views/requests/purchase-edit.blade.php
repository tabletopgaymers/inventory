@extends('layout')
@section('title', $row ? 'Edit purchase request' : 'New purchase request')
@section('purchase-progress')
@include('requests.purchase-progress', ['progressRow' => $row, 'progressActive' => $step, 'progressToken' => $token, 'progressSaveUrl' => '/purchases/work/'.$token, 'progressKey' => 'work:'.$token, 'progressRevision' => $draft['revision'], 'progressVersion' => $draft['version'], 'progressDirty' => \App\Support\PurchaseProgress::workDirty($draft)])
@endsection
@section('content')
<link rel="stylesheet" href="/request-workflow.css?v=desktop-800-1">
@if($row)<p data-purchase-identity>Purchase #{{ $row->id }} · {{ $row->status }}</p>@endif
<p>Title is required to save or submit. Suggested merchant and Request details are optional. Catalog items and new-item descriptions use the same request; no order or stock is created.</p>
<form id="purchase-work" method="post" action="/purchases/work/{{ $token }}" data-request-form data-purchase-form="work">
@if(!empty($legacyToken))<p data-purchase-legacy-key="{{ auth()->id() }}:intake:{{ $legacyToken }}">This older intake is now a working purchase request. Unsaved intake fields retained in this browser are restored below.</p>@endif
@csrf<input type="hidden" name="_deliberate" value="1"><input type="hidden" name="revision" value="{{ $draft['revision'] ?? 0 }}"><input type="hidden" name="work_version" value="{{ $draft['version'] }}"><input type="hidden" name="progress_step" value="{{ $step }}">
<section @if($step !== 'request') hidden @endif><label>Title<input name="title" required maxlength="255" value="{{ $draft['values']['title'] }}"></label><label>Suggested merchant (optional)<input name="suggested_merchant" maxlength="255" value="{{ $draft['values']['suggested_merchant'] }}"></label></section>
<label>{{ $step === 'items' ? 'If these are new items, describe them with their quantities here.' : 'Request details (optional)' }}<textarea name="details" maxlength="5000">{{ $draft['values']['details'] }}</textarea></label>
<section @if($step !== 'items') hidden @endif>
@include('requests.purchase-catalog', ['planning' => false, 'catalogEditable' => true])
</section>
<div class="actions">@if($step === 'items')<button name="action" value="request" formnovalidate>Back: request</button>@endif<a class="discard" href="{{ $row ? '/purchases/'.$row->id : '/purchases' }}">Leave request</a><button name="action" value="{{ $step === 'request' ? 'items' : 'review' }}" class="primary" formnovalidate>{{ $step === 'request' ? 'Next: items' : 'Next: review' }}</button></div>
</form><script src="/requests.js?v=purchase-wizard-1-sentence-case-1-pending-notice-1" defer></script>
@endsection
