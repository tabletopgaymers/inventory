@extends('layout')
@section('title', 'Inventory adjustment - '.$group->item_name)
@section('content-sections')
<div class="app-content app-record-content">

<div class="app-columns app-columns-small">
<div><x-app.detail-list variant="content" >
<dt>Item</dt><dd><a href="/inventory/items/{{ $group->item_id }}">{{ $group->item_name }}</a></dd>
<dt>SKU</dt><dd>{{ $group->item_sku }}</dd>
</x-app.detail-list></div>
<div><x-app.detail-list variant="content" >
<dt>Category</dt><dd><a href="/catalog/categories/{{ $classification->category_id }}">{{ $classification->category_name }}</a></dd>
<dt>Collection</dt><dd><a href="/catalog/collections/{{ $classification->collection_id }}">{{ $classification->collection_name }}</a></dd>
</x-app.detail-list></div>
<div><x-app.detail-list variant="content" >
<dt>Recorded by</dt><dd>{{ $group->actor_name }}</dd>
<dt>Time recorded</dt><dd>{{ \App\Support\DisplayDates::timestamp($group->posted_at) }}</dd>
</x-app.detail-list></div>
</div>
</div>
<x-app.table-region label="Adjustment" class="app-list-table-panel"><table><thead><tr><th>Location</th><th class="number">Before</th><th class="number">After</th><th class="number">Change</th><th class="number">Unit cost</th><th>Rationale</th></tr></thead><tbody>

@forelse($entries as $entry)<tr><td>{{ $entry->location_name }}</td><td class="number">{{ number_format($entry->before_quantity) }}</td><td class="number">{{ number_format($entry->after_quantity) }}</td><td class="number">{{ $entry->quantity_change > 0 ? '+' : '' }}{{ number_format($entry->quantity_change) }}</td><td class="number">{{ \App\Support\StockNumbers::displayCost($entry->unit_cost) }}</td><td>{{ $entry->rationale ?? '—' }}</td></tr>
@empty<tr><td colspan="6">No quantities changed; no History entries were recorded.</td></tr>
@endforelse
</tbody></table></x-app.table-region>
@endsection
