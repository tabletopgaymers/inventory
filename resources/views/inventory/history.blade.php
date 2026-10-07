<x-app.table-region label="Item history" class="table-wrap"><table class="history"><thead><tr><th>Date</th><th>Description</th><th class="number">Quantity</th><th class="number">Unit Cost</th></tr></thead><tbody>

@forelse($entries as $entry)
@php($sourceUrl = $entry->request_url ?? (isset($entry->cost_entry_id) ? '/inventory/cost-adjustments/'.$entry->cost_entry_id : '/inventory/adjustments/'.($entry->inventory_adjustment_id ?? '')))
<tr 
@if(!$preview)data-history-url="{{ $sourceUrl }}"
@endif>
<td>{{ $preview ? 'On Save' : \App\Support\DisplayDates::timestamp($entry->posted_at) }}</td>
<td>
@if(!$preview)<a href="{{ $sourceUrl }}">{{ $entry->description }}</a>
@else{{ $entry->description }}
@endif</td>
<td class="number">@if($entry->quantity_change === null)—@elseif(isset($entry->entry_kind) && $entry->entry_kind==='ordered')[{{ $entry->quantity_change > 0 ? '+' : '' }}{{ number_format($entry->quantity_change) }}]@elseif(isset($entry->entry_kind) && in_array($entry->entry_kind,['shipment','receipt_transfer','event_transfer']))±{{ number_format($entry->quantity_change) }}@else{{ $entry->quantity_change > 0 ? '+' : '' }}{{ number_format($entry->quantity_change) }}@endif</td><td class="number">{{ \App\Support\StockNumbers::displayCost($entry->unit_cost) }}</td></tr>

@empty<tr><td colspan="4">No changes recorded.</td></tr>
@endforelse
</tbody></table></x-app.table-region>
<script src="/inventory.js" defer></script>
