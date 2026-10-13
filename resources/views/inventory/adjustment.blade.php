@extends('layout')
@section('title', 'Inventory adjustment - '.$group->item_name)
@section('content-sections')
<div class="app-content app-record-content">
@hasSection('relocation-progress')@yield('relocation-progress')@endif @hasSection('purchase-progress')@yield('purchase-progress')@endif
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,16rem),1fr));gap:.75rem 2rem">
<div><dl class="app-user-details">
<dt>Item</dt><dd><a href="/inventory/items/{{ $group->item_id }}">{{ $group->item_name }}</a></dd>
<dt>SKU</dt><dd>{{ $group->item_sku }}</dd>
</dl></div>
<div><dl class="app-user-details">
<dt>Category</dt><dd><a href="/catalog/categories/{{ $classification->category_id }}">{{ $classification->category_name }}</a></dd>
<dt>Collection</dt><dd><a href="/catalog/collections/{{ $classification->collection_id }}">{{ $classification->collection_name }}</a></dd>
</dl></div>
<div><dl class="app-user-details">
<dt>Recorded by</dt><dd>{{ $group->actor_name }}</dd>
<dt>Time recorded</dt><dd>{{ \App\Support\DisplayDates::timestamp($group->posted_at) }}</dd>
</dl></div>
</div>
</div>
<x-app.table-region label="Adjustment" class="app-list-table-panel"><table><thead><tr><th>Location</th><th class="number">Before</th><th class="number">After</th><th class="number">Change</th><th class="number">Unit cost</th><th>Rationale</th></tr></thead><tbody>

@forelse($entries as $entry)<tr><td>{{ $entry->location_name }}</td><td class="number">{{ number_format($entry->before_quantity) }}</td><td class="number">{{ number_format($entry->after_quantity) }}</td><td class="number">{{ $entry->quantity_change > 0 ? '+' : '' }}{{ number_format($entry->quantity_change) }}</td><td class="number">{{ \App\Support\StockNumbers::displayCost($entry->unit_cost) }}</td><td>{{ $entry->rationale ?? '—' }}</td></tr>
@empty<tr><td colspan="6">No quantities changed; no History entries were recorded.</td></tr>
@endforelse
</tbody></table></x-app.table-region>
@endsection
