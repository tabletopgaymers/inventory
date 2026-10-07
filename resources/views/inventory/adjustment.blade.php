@extends('layout')
@section('title', 'Adjustment — '.$group->item_name)
@section('content')
<p>{{ $group->item_sku }} · Recorded by {{ $group->actor_name }}</p>
<p>{{ \App\Support\DisplayDates::timestamp($group->posted_at) }}</p>
<x-app.table-region label="Adjustment" class="table-wrap"><table><thead><tr><th>Location</th><th class="number">Before</th><th class="number">After</th><th class="number">Change</th><th class="number">Unit Cost</th><th>Rationale</th></tr></thead><tbody>

@forelse($entries as $entry)<tr><td>{{ $entry->location_name }}</td><td class="number">{{ number_format($entry->before_quantity) }}</td><td class="number">{{ number_format($entry->after_quantity) }}</td><td class="number">{{ $entry->quantity_change > 0 ? '+' : '' }}{{ number_format($entry->quantity_change) }}</td><td class="number">{{ \App\Support\StockNumbers::displayCost($entry->unit_cost) }}</td><td>{{ $entry->rationale ?? '—' }}</td></tr>
@empty<tr><td colspan="6">No quantities changed; no History entries were recorded.</td></tr>
@endforelse
</tbody></table></x-app.table-region><p><a href="/inventory/items/{{ $group->item_id }}#history">Item History</a></p>

@endsection
