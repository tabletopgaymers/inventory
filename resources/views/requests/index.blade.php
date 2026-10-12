@extends('layout')

@section('title', $kind === 'purchase' ? 'Purchase requests' : 'Relocation requests')

@section('page-actions')
<a class="button app-new-action" href="{{ $kind === 'purchase' ? '/purchases/new' : '/relocations/new' }}" title="New {{ $kind }} request">+New</a>
@endsection

@section('content')

@php($base = $kind === 'purchase' ? '/purchases' : '/relocations')

@php($statuses = $kind === 'purchase' ? ['Draft','Request','Ordered','Shipped','Received','Cancelled'] : ['Draft','Requested','Shipped','Receiving','Complete','Cancelled'])

@php($defaults = $kind === 'purchase' ? ['Draft','Request','Ordered','Shipped'] : ['Draft','Requested','Shipped','Receiving'])

<p>Save requests and preparation. Intake never reserves or moves stock.</p>

<section data-request-index="{{ $kind }}:{{ auth()->id() }}"><div class="actions"><button type="button" data-my-drafts>My drafts</button><button type="button" data-show-all-requests>Show all / clear filters</button></div><p>Default filters hide completed and cancelled requests. My drafts shows only your unfinished Drafts.</p><fieldset><legend>Show statuses</legend>@foreach($statuses as $status)<label class="app-choice inline"><input type="checkbox" data-status-filter value="{{ $status }}" @checked(in_array($status,$defaults))>{{ $status }}</label>@endforeach</fieldset>

<x-app.table-region :label="$kind === 'purchase' ? 'Purchase requests' : 'Relocation requests'" class="table-wrap"><table><thead><tr><th>ID</th><th>Title</th>@if($kind === 'purchase')<th>Owner</th>@else<th>Source</th><th>Destination</th>@endif<th>{{ $kind === 'purchase' ? 'Last update' : 'Last updated' }}</th><th>Status</th></tr></thead><tbody>@foreach($rows as $row)<tr data-request-status="{{ $row->status }}" data-request-owner="{{ $row->owner_id }}" @if(!in_array($row->status,$defaults))hidden @endif><td>{{ $row->id }}</td><td><a href="{{ $base }}/{{ $row->id }}">{{ $row->title ?: 'Untitled request' }}</a>@if($kind === 'purchase' && $row->status === 'Draft' && ($row->owner_id == auth()->id() || \App\Support\RequestValues::manages(auth()->user(), $kind === 'purchase' ? 'procurement' : 'manager')))<br><a href="{{ $base }}/{{ $row->id }}/edit">Resume draft</a>@endif @if($kind === 'purchase' && in_array($row->status, ['Draft','Request']) && \App\Support\RequestValues::manages(auth()->user(), 'procurement'))<div class="actions"><a href="/purchases/{{ $row->id }}/preparation">Prepare purchase</a>@if($fulfillmentReady ?? false)<a href="/purchases/{{ $row->id }}/fulfillment/order">Record order</a>@endif</div>@endif</td>@if($kind === 'purchase')<td>{{ trim($row->first_name.' '.$row->last_name) ?: 'Account '.$row->owner_id }}</td>@else<td>{{ $row->source_name ?: '—' }}</td><td>{{ $row->destination_name ?: '—' }}</td>@endif<td>{{ \App\Support\DisplayDates::date($row->updated_at) }}</td><td><span class="status-badge request-status {{ strtolower($row->status) }}">{{ $row->status }}</span></td></tr>@endforeach<tr data-no-requests @if($rows->whereIn('status',$defaults)->isNotEmpty())hidden @endif><td colspan="{{ $kind === 'purchase' ? 5 : 6 }}">No requests match these filters. Completed or cancelled requests may be hidden; choose Show all / clear filters to see every saved request.</td></tr></tbody></table></x-app.table-region></section>

<script src="/requests.js?v=inline-decisions-1-sentence-case-1" defer></script>

@endsection
