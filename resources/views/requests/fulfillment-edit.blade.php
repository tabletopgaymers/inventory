@extends('layout')
@section('title', ucfirst($mode).' — '.($row->title ?: 'Untitled request'))
@section('title-status')<span class="status-badge request-status {{ strtolower($row->status) }}">{{ $row->status }}</span>@endsection
@if($kind === 'relocation')
@section('relocation-progress')
@include('requests.relocation-progress', ['progressRow' => $row, 'progressContext' => 'counts', 'progressActive' => $mode === 'shipment' ? 'ship' : 'receive', 'progressReadOnly' => $readOnly, 'progressSaveUrl' => !$readOnly ? $base.'/fulfillment/'.$mode : null, 'progressKey' => 'counts:'.$token, 'progressRevision' => $draft['revision'], 'progressVersion' => $draft['version'] ?? 0, 'progressDirty' => !$readOnly && \App\Support\RelocationProgress::counts($draft['values'], $mode) !== ($draft['saved_values'] ?? \App\Support\RelocationProgress::counts($draft['values'], $mode))])
@endsection
@else
@section('purchase-progress')
@include('requests.purchase-progress', ['progressRow' => $row, 'progressActive' => $mode === 'order' ? 'order' : 'receive', 'progressSaveUrl' => !$readOnly ? $base.'/fulfillment/'.$mode : null, 'progressKey' => 'invoice:'.$token, 'progressRevision' => $draft['revision'], 'progressVersion' => $draft['version'] ?? 0, 'progressDirty' => !$readOnly && ((int) $draft['revision'] !== (int) $row->revision || \App\Support\PurchaseProgress::fields($draft['values'], 'invoice') !== ($draft['saved_fields'] ?? \App\Support\PurchaseProgress::fields($draft['values'], 'invoice')))])
@endsection
@endif
@section('content-sections')
<div class="app-content">
@hasSection('relocation-progress')@yield('relocation-progress')@endif @hasSection('purchase-progress')@yield('purchase-progress')@endif
@php($values = $draft['values'])
<p><a href="{{ $base }}">Back to {{ $kind }} #{{ $row->id }}</a></p>
@if(!$readOnly)<p>{{ $kind==='purchase' ? 'Save progress retains an unfinished invoice draft without changing expectations, stock or costs. Review verifies the invoice; Confirm records this order or final receipt once.' : ($mode==='receipt' ? 'Received counts are cumulative. Save counts moves no stock; confirmation posts once.' : 'Enter actual sent counts. Save counts moves no stock; confirmation posts once.') }}</p>@endif
@if($mode==='receipt')<p>{{ $kind==='purchase' ? 'Verify actual individual units and final amounts. Remove missing lines before the one final receipt. Cancel a wholly missing purchase.' : 'Enter the total received across all boxes. A Manager or Admin must confirm a permanent shortage or overage.' }}</p>@endif
</div><form method="post" action="{{ $base }}/fulfillment/{{ $mode }}" data-request-form @if($kind==='relocation' && !$readOnly) id="relocation-counts" data-relocation-form="counts" @endif @if($kind==='purchase') id="purchase-invoice" data-invoice-form @if(!$readOnly) data-purchase-form="invoice" @endif @endif>
<input type="hidden" name="progress_step" value="edit">@if($kind==='purchase')<input type="hidden" name="work_version" value="{{ $draft['version'] ?? 0 }}"><input type="hidden" name="revision" value="{{ $draft['revision'] }}">@endif
@csrf<input type="hidden" name="_deliberate" value="1"><input type="hidden" name="work" value="{{ $token }}">
<fieldset class="app-entry-fieldset" @disabled($readOnly)><legend class="sr-only">{{ ucfirst($mode) }} details</legend><div class="app-content app-record-content"><h2>{{ ucfirst($mode) }} details</h2>
@if($kind==='purchase')
<label>Title<input name="title" maxlength="255" required value="{{ $values['title'] }}"></label>
<label>Supplier<select name="supplier_id" required><option value="">Choose supplier</option>@foreach($suppliers as $supplier)@if($supplier->state==='active' || (string)$supplier->id===(string)$values['supplier_id'])<option value="{{ $supplier->id }}" @selected((string)$supplier->id===(string)$values['supplier_id'])>{{ $supplier->name }}</option>@endif @endforeach</select></label>
<label>Receiving storage<select name="receiving_location_id" required><option value="">Choose storage</option>@foreach($locations as $location)@if($location->state==='active' || (string)$location->id===(string)$values['receiving_location_id'])<option value="{{ $location->id }}" @selected((string)$location->id===(string)$values['receiving_location_id'])>{{ $location->name }}</option>@endif @endforeach</select></label>
@foreach(['ordered_date'=>'Ordered date','shipped_date'=>'Shipped date','received_date'=>'Received date'] as $field=>$label)<label>{{ $label }}<input type="date" name="{{ $field }}" value="{{ $values[$field] ?? '' }}" @if($field!=='ordered_date' && !($field==='received_date' && $mode==='receipt')) data-optional @endif @required($field==='ordered_date' || ($field==='received_date' && $mode==='receipt'))></label>@endforeach
</div><x-app.table-region label="Purchase invoice" class="app-list-table-panel"><table class="stock-editor"><thead><tr><th>Item</th><th>Individual units</th><th>Unit (merchandise USD)</th><th>Cost (USD)</th><th>Fee (USD)</th><th>Line (USD)</th><th>Actions</th></tr></thead><tbody>
@foreach($values['lines'] as $itemId=>$line)@php($item=$items->firstWhere('id',(int)$itemId))
<tr data-invoice-line><th>{{ $item?->name ?: 'Unavailable item' }}<small class="app-item-sku">{{ $item?->sku ?: '#'.$itemId }}</small></th>
@foreach(['quantity'=>'Individual units','unit'=>'Unit','cost'=>'Cost','fee'=>'Fee'] as $field=>$label)<td><input name="lines[{{ $itemId }}][{{ $field }}]" data-invoice-field="{{ $field }}" value="{{ $line[$field] ?? '' }}" inputmode="{{ $field==='quantity' ? 'numeric' : 'decimal' }}" aria-label="{{ $label }} for {{ ($item?->name ?: 'Unavailable item').' · '.($item?->sku ?: '#'.$itemId) }}" required></td>@endforeach
<td data-invoice-total>—</td><td><input type="hidden" name="lines[{{ $itemId }}][basis]" data-invoice-basis data-purchase-field value="{{ $line['basis'] ?? 'unit' }}"><button type="submit" name="recalculate_item" value="{{ $itemId }}" data-action="recalculate" title="Recalculate this row" aria-label="Recalculate {{ ($item?->name ?: 'Unavailable item').' · '.($item?->sku ?: '#'.$itemId) }}">↻</button> <button type="submit" name="remove_item" value="{{ $itemId }}" data-action="remove" formnovalidate>Remove</button></td></tr>
@endforeach</tbody></table></x-app.table-region><div class="app-content app-record-content">
@if(!$readOnly)<label>Add catalog item<select name="add_item"><option value="">Choose item</option>@foreach($items->where('state','active') as $item)<option value="{{ $item->id }}">{{ $item->name }} · {{ $item->sku }}</option>@endforeach</select></label><button name="action" value="add" formnovalidate>Add item</button>@endif
@foreach(['other_fees'=>'Other fees','discount'=>'Discount','tax'=>'Tax','shipping'=>'Shipping'] as $field=>$label)<label>{{ $label }} (USD)<input name="{{ $field }}" inputmode="decimal" value="{{ $values[$field] ?? '0.00' }}" required></label>@endforeach
<label>Shipping allocation<select name="shipping_method"><option value="line_total" @selected(($values['shipping_method']??'line_total')==='line_total')>By line total</option><option value="quantity" @selected(($values['shipping_method']??'line_total')==='quantity')>By quantity</option></select></label>
@else
<label>{{ $mode==='shipment' ? 'Shipped' : 'Received' }} date<input type="date" name="{{ $mode==='shipment' ? 'shipped_date' : 'received_date' }}" value="{{ $values[$mode==='shipment' ? 'shipped_date' : 'received_date'] ?? '' }}"></label>
</div><x-app.table-region label="Relocation counts" class="app-list-table-panel"><table><thead><tr><th>Item</th><th>Requested</th><th>Sent</th>@if($mode==='receipt')<th>Cumulative received</th>@endif</tr></thead><tbody>
@php($lastCollection=null)@foreach($relocationLines as $itemId=>$line)@php($item=$items->firstWhere('id',(int)$itemId))@php($requested=$requestedQuantities[$itemId] ?? 0)@php($collection=$collections->firstWhere('id',$item?->collection_id))@if($lastCollection!==$item?->collection_id)<tr class="group-row"><th colspan="{{ $mode==='receipt' ? 4 : 3 }}">{{ $collection?->category_name }}: {{ $collection?->name ?: 'Unavailable collection' }}</th></tr>@php($lastCollection=$item?->collection_id)@endif<tr><td class="app-grouped-item">{{ $item?->name ?: 'Unavailable item' }}</td><td>{{ number_format($requested) }}</td>
@if($mode==='shipment')<td><input name="lines[{{ $itemId }}][sent]" value="{{ $line['sent'] ?? '' }}" inputmode="numeric" aria-label="Sent for {{ ($item?->name ?: 'Unavailable item').' · '.($item?->sku ?: '#'.$itemId) }}"></td>@else<td>{{ number_format((int)$line['sent']) }}</td><td><input name="lines[{{ $itemId }}][received]" value="{{ $line['received'] ?? '' }}" inputmode="numeric" aria-label="Cumulative received for {{ ($item?->name ?: 'Unavailable item').' · '.($item?->sku ?: '#'.$itemId) }}"></td>@endif</tr>@endforeach
</tbody></table></x-app.table-region><div class="app-content app-record-content">
@if($mode==='receipt')<label>Explanation<textarea data-optional name="explanation" maxlength="5000">{{ $values['explanation'] ?? '' }}</textarea></label>@endif
@endif
</div></fieldset><div class="app-content app-record-content">
@if(!$readOnly)
@if($kind==='purchase' && (auth()->user()->hasRole('manager') || auth()->user()->hasRole('admin')))<p><a href="/catalog/items/create" target="_blank" rel="noopener">Create catalog item</a> · <a href="/catalog/suppliers/create" target="_blank" rel="noopener">Create supplier</a>. After creating a record, return here and refresh the choices.</p><button name="action" value="refresh" formnovalidate>Refresh catalog choices</button>@endif
<div class="actions"><a class="discard" href="{{ $base }}">{{ $mode==='receipt' ? 'Cancel receiving' : 'Discard changes' }}</a><button class="primary" name="action" value="review">Review {{ $mode }}</button></div>@endif
</div></form><script src="/requests.js?v={{ $kind === 'relocation' ? 'relocation-progress-1' : 'purchase-progress-2' }}-sentence-case-1-pending-notice-1" defer></script>@if($kind==='purchase')<script src="/purchase-invoice.js?v=purchase-progress-1" defer></script>@endif
@endsection
