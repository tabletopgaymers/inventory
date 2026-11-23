@extends('layout')
@section('title', $location->name.' inventory')
@section('title-status')<x-app.status-badge :status="ucfirst($location->state)" />@endsection
@section('page-actions')
@if($canManage)<x-app.action  href="/catalog/storage_locations/{{ $location->id }}/edit">Edit</x-app.action>@endif
@endsection
@section('content-sections')
<div class="app-location-report app-print-report app-panel-stack">
<div class="app-columns app-columns-two">
<div class="app-content">
    <x-app.detail-list variant="content" ><dt>Description</dt><dd>{{ $location->description ?: '—' }}</dd><dt>Mailing address</dt><dd class="plain-text">{{ $location->mailing_address ?: '—' }}</dd></x-app.detail-list>
</div>
<form method="get" action="/inventory/locations/{{ $location->id }}" class="app-content app-screen-only" data-location-view-filter>
    @if(is_string($browse) && \Illuminate\Support\Str::isUuid($browse))<input type="hidden" name="browse" value="{{ $browse }}">@endif
    <label class="app-choice"><input type="checkbox" name="include_zero_stock" value="1" @checked($includeZeroStock)> Include zero-stock items</label>
    @if($result['showTransit'] || $result['showOrdered'])
    <input type="hidden" name="show_incoming" value="0">
    <label class="app-choice"><input type="checkbox" name="show_incoming" value="1" @checked($showIncoming)> Show Ordered and In Transit</label>
    @endif
</form>
</div>
@php
    $showTransit = $showIncoming && $result['showTransit'];
    $showOrdered = $showIncoming && $result['showOrdered'];
    $showExpected = $showTransit || $showOrdered;
    $columnCount = 2 + (int) $showTransit + (int) $showOrdered + (int) $showExpected;
@endphp
<x-app.table-region label="Location inventory" :nowrap="true" class="app-list-table-panel">
<x-slot:header><h2 class="app-screen-only">Inventory</h2></x-slot:header>
<table><colgroup><col>@for($column = 1; $column < $columnCount; $column++)<col class="table-quantity-column">@endfor</colgroup>
<thead><tr><th>Item</th><th class="table-numeric">{{ $location->name }}</th>@if($showTransit)<th class="table-numeric">In Transit</th>@endif @if($showOrdered)<th class="table-numeric">Ordered</th>@endif @if($showExpected)<th class="table-numeric">Expected</th>@endif</tr></thead><tbody>
@php
    $group = null;
@endphp
@forelse($result['rows'] as $row)
@if($group !== [$row['category'], $row['collection']])
@php
    $group = [$row['category'], $row['collection']];
@endphp
<tr class="collection-heading"><th colspan="{{ $columnCount }}">{{ $row['category'] }} : {{ $row['collection'] }}</th></tr>
@endif
<tr><td class="app-grouped-item"><a href="/inventory/items/{{ $row['id'] }}@if(is_string($browse) && \Illuminate\Support\Str::isUuid($browse))?browse={{ $browse }}@endif">{{ $row['name'] }}</a></td><td class="number">{{ number_format($row['values']['storage:'.$location->id]) }}</td>@if($showTransit)<td class="number">{{ number_format($row['incoming_transit']) }}</td>@endif @if($showOrdered)<td class="number">{{ number_format($row['incoming_ordered']) }}</td>@endif @if($showExpected)<td class="number">{{ number_format($row['expected']) }}</td>@endif</tr>
@empty<tr><td colspan="{{ $columnCount }}">No items at this location.</td></tr>
@endforelse
</tbody></table>
<x-slot:footer>
    <p>Event, In Transit and Ordered balances are separate. Expected includes current counts at this location, In Transit, and Ordered balances.</p>
</x-slot:footer>
</x-app.table-region>
</div>
@vite('resources/js/inventory.js')
@endsection
