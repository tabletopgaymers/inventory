@extends('layout')
@section('title', $location->name.' inventory')
@section('title-status')<x-app.status-badge :status="ucfirst($location->state)" />@endsection
@section('page-actions')
@if($canManage)<a class="button" href="/catalog/storage_locations/{{ $location->id }}/edit">Edit</a>@endif
@endsection
@section('content-sections')
<div class="app-content">
    <dl class="app-user-details"><dt>Description</dt><dd>{{ $location->description ?: '—' }}</dd><dt>Mailing address</dt><dd class="plain-text">{{ $location->mailing_address ?: '—' }}</dd></dl>
</div>
<h2>Inventory</h2>
<x-app.table-region label="Location inventory" class="app-list-table-panel">
<table><thead><tr><th>Item</th><th class="number">At this location</th><th class="number">Available (all storage)</th></tr></thead><tbody>
@php
    $group = null;
@endphp
@forelse($result['rows'] as $row)
@if($group !== [$row['category'], $row['collection']])
@php
    $group = [$row['category'], $row['collection']];
@endphp
<tr class="collection-heading"><th colspan="3">{{ $row['category'] }} : {{ $row['collection'] }}</th></tr>
@endif
<tr><td class="app-grouped-item"><a href="/inventory/items/{{ $row['id'] }}@if(is_string($browse) && \Illuminate\Support\Str::isUuid($browse))?browse={{ $browse }}@endif">{{ $row['name'] }}</a></td><td class="number">{{ number_format($row['values']['storage:'.$location->id]) }}</td><td class="number">{{ number_format($row['available']) }}</td></tr>
@empty<tr><td colspan="3">No items at this location.</td></tr>
@endforelse
</tbody></table>
</x-app.table-region>
@endsection
