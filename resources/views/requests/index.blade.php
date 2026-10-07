@extends('layout')

@section('title', $kind === 'purchase' ? 'Purchase Requests' : 'Relocation Requests')

@section('content')

@php($base = $kind === 'purchase' ? '/purchases' : '/relocations')

@php($statuses = $kind === 'purchase' ? ['Draft','Request','Ordered','Shipped','Received','Cancelled'] : ['Draft','Requested','Shipped','Receiving','Complete','Cancelled'])

@php($defaults = $kind === 'purchase' ? ['Draft','Request','Ordered','Shipped'] : ['Draft','Requested','Shipped','Receiving'])

<div class="section-heading"><p>Save requests and preparation. Intake never reserves or moves stock.</p><a class="button primary" href="{{ $base }}/new">New {{ $kind }} request</a></div>

<section data-request-index="{{ $kind }}:{{ auth()->id() }}"><fieldset><legend>Show statuses</legend>@foreach($statuses as $status)<label class="app-choice inline"><input type="checkbox" data-status-filter value="{{ $status }}" @checked(in_array($status,$defaults))>{{ $status }}</label>@endforeach</fieldset>

<x-app.table-region :label="$kind === 'purchase' ? 'Purchase requests' : 'Relocation requests'" class="table-wrap"><table><thead><tr><th>ID</th><th>Title</th>@if($kind === 'purchase')<th>Owner</th>@else<th>Source</th><th>Destination</th>@endif<th>{{ $kind === 'purchase' ? 'Last Update' : 'Last updated' }}</th><th>Status</th></tr></thead><tbody>@foreach($rows as $row)<tr data-request-status="{{ $row->status }}" @if(!in_array($row->status,$defaults))hidden @endif><td>{{ $row->id }}</td><td><a href="{{ $base }}/{{ $row->id }}">{{ $row->title ?: 'Untitled request' }}</a></td>@if($kind === 'purchase')<td>{{ trim($row->first_name.' '.$row->last_name) ?: 'Account '.$row->owner_id }}</td>@else<td>{{ $row->source_name ?: '—' }}</td><td>{{ $row->destination_name ?: '—' }}</td>@endif<td>{{ \App\Support\DisplayDates::date($row->updated_at) }}</td><td><span class="status-badge request-status {{ strtolower($row->status) }}">{{ $row->status }}</span></td></tr>@endforeach<tr data-no-requests @if($rows->whereIn('status',$defaults)->isNotEmpty())hidden @endif><td colspan="{{ $kind === 'purchase' ? 5 : 6 }}">No requests match the selected statuses.</td></tr></tbody></table></x-app.table-region></section>

<script src="/requests.js" defer></script>

@endsection
