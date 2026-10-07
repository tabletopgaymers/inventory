@extends('layout')
@section('title', $location->name.' inventory')
@section('content')
<p><span class="status-badge {{ $location->state }}">{{ ucfirst($location->state) }}</span></p><p>{{ $location->description }}</p><p>{{ $location->notes }}</p>
<x-app.table-region label="Location inventory" class="table-wrap"><table><thead><tr><th>Item</th><th class="number">At this location</th><th class="number">Available (all storage)</th></tr></thead><tbody>@foreach($result['rows'] as $row)<tr><td><a href="/inventory/items/{{ $row['id'] }}@if(is_string($browse) && \Illuminate\Support\Str::isUuid($browse))?browse={{ $browse }}@endif">{{ $row['category'] }} : {{ $row['collection'] }} · {{ $row['name'] }}</a></td><td class="number">{{ number_format($row['values']['storage:'.$location->id]) }}</td><td class="number">{{ number_format($row['available']) }}</td></tr>@endforeach</tbody></table></x-app.table-region>
@if(is_string($browse) && \Illuminate\Support\Str::isUuid($browse))<p><a href="/inventory/results/{{ $browse }}">Back to results</a></p>@else<p><a href="/inventory">Inventory search</a></p>@endif
@endsection
