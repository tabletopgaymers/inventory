@extends('layout')
@section('title', $location->name.' inventory')
@section('content-sections')
<div class="app-content app-record-content">
@hasSection('relocation-progress')@yield('relocation-progress')@endif @hasSection('purchase-progress')@yield('purchase-progress')@endif
<p><span class="status-badge {{ $location->state }}">{{ ucfirst($location->state) }}</span></p><p>{{ $location->description }}</p><p>{{ $location->notes }}</p>
</div>
<x-app.table-region label="Location inventory" class="app-list-table-panel"><table><thead><tr><th>Item</th><th class="number">At this location</th><th class="number">Available (all storage)</th></tr></thead><tbody>@foreach($result['rows'] as $row)<tr><td><a href="/inventory/items/{{ $row['id'] }}@if(is_string($browse) && \Illuminate\Support\Str::isUuid($browse))?browse={{ $browse }}@endif">{{ $row['category'] }} : {{ $row['collection'] }} · {{ $row['name'] }}</a></td><td class="number">{{ number_format($row['values']['storage:'.$location->id]) }}</td><td class="number">{{ number_format($row['available']) }}</td></tr>@endforeach</tbody></table></x-app.table-region>
<div class="app-content app-record-content">
@if(is_string($browse) && \Illuminate\Support\Str::isUuid($browse))<p><a href="/inventory/results/{{ $browse }}">Back to results</a></p>@else<p><a href="/inventory">Inventory search</a></p>@endif
</div>
@endsection
