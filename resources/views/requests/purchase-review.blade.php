@extends('layout')
@section('title', 'Review purchase request')
@section('purchase-progress')
@include('requests.purchase-progress', ['progressRow' => $row, 'progressActive' => 'review', 'progressToken' => $token, 'progressSaveUrl' => '/purchases/work/'.$token, 'progressKey' => 'work:'.$token, 'progressRevision' => $draft['revision'], 'progressVersion' => $draft['version'], 'progressDirty' => \App\Support\PurchaseProgress::workDirty($draft)])
@endsection
@section('content')
@if($row)<p data-purchase-identity>Purchase #{{ $row->id }} · {{ $row->status }}</p>@endif
<dl class="item-metadata"><dt>Title</dt><dd data-purchase-display="title">{{ $draft['values']['title'] }}</dd><dt>Suggested merchant</dt><dd data-purchase-display="suggested_merchant">{{ $draft['values']['suggested_merchant'] ?: 'Not provided' }}</dd><dt>Request details</dt><dd class="plain-text" data-purchase-display="details">{{ $draft['values']['details'] ?: 'Not provided' }}</dd></dl>
@include('requests.purchase-catalog', ['planning' => false, 'catalogEditable' => false])
<form id="purchase-work" method="post" action="/purchases/work/{{ $token }}/submit" class="actions" data-purchase-form="work-review">
@csrf<input type="hidden" name="_deliberate" value="1"><input type="hidden" name="progress_step" value="review"><input type="hidden" name="revision" value="{{ $draft['revision'] ?? 0 }}"><input type="hidden" name="work_version" value="{{ $draft['version'] }}"><input type="hidden" name="review_hash" value="{{ \App\Support\PurchaseProgress::workHash($draft) }}">
@foreach(\App\Support\PurchaseProgress::fields($draft['values'], 'work') as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}" data-purchase-field>@endforeach
<button name="action" value="cancel" class="discard" data-confirm="{{ $row ? 'Cancel this saved request? Saved details and history remain. Unsaved changes will not be saved.' : 'Abandon this unsaved request? No saved request will be cancelled.' }}">Cancel request</button><button name="action" value="submit" class="primary" @disabled(!$submitter || ($row && $row->status !== 'Draft'))>Submit request</button>
</form>
@endsection
