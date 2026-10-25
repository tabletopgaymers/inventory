@php
    $receiptRows=$preview['received_lines'] ?? $preview['lines'];
    $orderRows=$preview['ordered_lines'] ?? ($recordedOrder['lines'] ?? []);
    $comparison=\App\Support\PurchaseInvoiceLines::rows(array_replace($orderRows,$receiptRows));
    $group=null;
@endphp
<x-app.table-region data-invoice-derived label="Receiving confirmation" class="purchase-table" :nowrap="true" :separators="true" numeric="after-first">
<table><colgroup><col>@for($column=0;$column<7;$column++)<col class="purchase-material-value-column">@endfor</colgroup><thead><tr><th>Item</th><th>Requested</th><th>Ordered</th><th colspan="2" class="table-heading-center">Received</th><th>Acquisition ($)</th><th>Stock effect</th><th>Unit Cost</th></tr></thead><tbody>
@foreach($comparison as $item)
@php
    $key=$item['id'];$received=$receiptRows[$key] ?? [];$orderLine=$orderRows[$key] ?? [];
    $quantity=(int)($received['quantity'] ?? 0);$orderedQuantity=(int)($orderLine['quantity'] ?? 0);$variance=$quantity-$orderedQuantity;
    $sourceKey=$received['source_request_key'] ?? $orderLine['source_request_key'] ?? '';
    $requestedQuantity=$requestMaterial[$sourceKey]['quantity'] ?? ($item['item_id'] ? ($requested[$item['item_id']] ?? '—') : '—');
    $projection=$preview['projection'][$key] ?? null;
    $stockChange=$projection['stock_change'] ?? ($projection ? $projection['after_held']-$projection['held'] : 0);
@endphp
@if($group!==$item['group'])
@php $group=$item['group']; @endphp
<tr class="group-row"><th colspan="8" scope="rowgroup">{{ $group }}</th></tr>
@endif
<tr><th class="app-grouped-item">{{ $item['name'] }}</th><td>{{ is_numeric($requestedQuantity) ? number_format($requestedQuantity) : $requestedQuantity }}</td><td>{{ number_format($orderedQuantity) }}</td><td class="{{ $variance<0 ? 'table-variance-negative' : 'table-variance-positive' }}">{{ $variance ? '['.($variance>0?'+':'').number_format($variance).']' : '' }}</td><td>{{ number_format($quantity) }}</td><td>{{ $quantity ? \App\Support\PurchaseCosting::dollars($received['acquisition'] ?? 0) : '—' }}</td>
<td>{{ $stockChange ? '+'.number_format($stockChange).($row->status==='Received'?' at receipt':'') : 'No stock change' }}</td><td>
@if($projection)
{{ \App\Support\StockNumbers::displayCost($projection['before_cost']) }} → {{ \App\Support\StockNumbers::displayCost($projection['after_cost']) }}
@elseif(!$item['item_id'])
No catalog cost
@else
No change
@endif
</td></tr>
@endforeach
</tbody></table></x-app.table-region>
<p>Zero received items remain in the comparison and are excluded from final item costs. {{ $row->status==='Received' ? 'Effects above were recorded at receipt; they are not applied again.' : 'This is a preview. No stock or Unit Cost changes occur until final confirmation.' }}</p>
