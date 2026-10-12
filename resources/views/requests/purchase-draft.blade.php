@extends('layout')
@section('title', $row->title ?: 'Untitled request')
@section('title-status')<span class="status-badge request-status draft">Draft</span>@endsection
@section('purchase-progress')
@include('requests.purchase-progress', ['progressRow' => $row, 'progressActive' => 'review', 'progressKey' => 'show:'.$row->id])
@endsection
@section('content-sections')
<div class="app-content">
@yield('purchase-progress')
<p>Purchase #{{ $row->id }} · Owner: {{ trim($owner->first_name.' '.$owner->last_name) ?: 'Account '.$owner->id }}</p>
<section id="purchase-review"><dl class="item-metadata" id="purchase-request"><dt>Title</dt><dd>{{ $row->title ?: 'Untitled request' }}</dd><dt>Suggested merchant</dt><dd>{{ $row->suggested_merchant ?: 'Not provided' }}</dd><dt>Request details</dt><dd class="plain-text">{{ $row->details ?: 'Not provided' }}</dd></dl></section>
</div><section id="purchase-items"><div class="app-content"><h2>Catalog lines</h2>@if(!$requestRows)<p>No catalog items requested.</p>@endif</div>
@if($requestRows)<x-app.table-region label="Requested purchase items" class="app-list-table-panel"><table><thead><tr><th>Item</th><th>Requested units</th></tr></thead><tbody>@php($group=null)@foreach($requestRows as $item)@if($group!==$item['category'].' : '.$item['collection'])@php($group=$item['category'].' : '.$item['collection'])<tr class="group-row"><th colspan="2">{{ $group }}</th></tr>@endif<tr><td class="app-grouped-item">{{ $item['name'] }}</td><td>{{ number_format((int)($requestLines[$item['id']] ?? 0)) }}</td></tr>@endforeach</tbody></table></x-app.table-region>@endif</section><div class="app-content">
<form method="post" action="/purchases/{{ $row->id }}/transition" class="actions">@csrf<input type="hidden" name="_deliberate" value="1"><input type="hidden" name="revision" value="{{ $row->revision }}">
<button name="action" value="cancel" class="discard" @disabled(!$manager && (int)$row->owner_id !== (int)auth()->id()) data-confirm="Cancel this request? Its saved details and history will remain.">Cancel request</button><button name="action" value="submit" class="primary" @disabled(!$submitter)>Submit request</button>
</form>
</div>
@endsection
