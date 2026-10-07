@extends('layout')
@section('title', 'Count saved · '.$location->name)
@section('content')
<p>{{ \App\Support\DisplayDates::timestamp($operation->posted_at) }}</p><ul>@forelse($changes as $change)<li><a href="/inventory/adjustments/{{ $change->inventory_adjustment_id }}">{{ $change->item_name }} · {{ $change->item_sku }}</a></li>@empty<li>No item quantities changed; no correction history was created.</li>@endforelse</ul><p><a href="/inventory/location-counts">Location Counts</a></p>
@endsection
