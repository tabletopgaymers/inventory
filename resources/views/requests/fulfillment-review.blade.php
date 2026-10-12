@extends('layout')
@section('title', 'Review '.$mode.' — '.($row->title ?: 'Untitled request'))
@if($kind === 'relocation')
@section('relocation-progress')
@include('requests.relocation-progress', ['progressRow' => $row, 'progressContext' => 'counts-review', 'progressActive' => $mode === 'shipment' ? 'ship' : 'receive', 'progressSaveUrl' => $base.'/fulfillment/'.$mode, 'progressKey' => 'counts:'.$token, 'progressRevision' => $draft['revision'], 'progressVersion' => $draft['version'] ?? 0, 'progressDirty' => \App\Support\RelocationProgress::counts($draft['values'], $mode) !== ($draft['saved_values'] ?? \App\Support\RelocationProgress::counts($draft['values'], $mode))])
@endsection
@else
@section('purchase-progress')
@include('requests.purchase-progress', ['progressRow' => $row, 'progressActive' => $mode === 'order' ? 'order' : 'receive', 'progressSaveUrl' => $base.'/fulfillment/'.$mode, 'progressKey' => 'invoice:'.$token, 'progressRevision' => $draft['revision'], 'progressVersion' => $draft['version'] ?? 0, 'progressDirty' => (int) $draft['revision'] !== (int) $row->revision || \App\Support\PurchaseProgress::fields($draft['values'], 'invoice') !== ($draft['saved_fields'] ?? \App\Support\PurchaseProgress::fields($draft['values'], 'invoice'))])
@endsection
@endif
@section('content-sections')
<div class="app-content app-record-content">
@hasSection('relocation-progress')@yield('relocation-progress')@endif @hasSection('purchase-progress')@yield('purchase-progress')@endif
<p>Reviewing {{ $kind }} #{{ $row->id }}. No stock has moved.</p>
@if($kind==='purchase')
<p>Save progress retains this invoice draft without confirming it. Use Confirm order or Confirm receipt only when ready to record the reviewed outcome.</p>
<p>Supplier: {{ $suppliers->firstWhere('id',$review['supplier_id'])?->name }} · Receiving storage: {{ $locations->firstWhere('id',$review['receiving_location_id'])?->name }}</p>
<p>Ordered {{ \App\Support\DisplayDates::businessDate($review['ordered_date']) }} · Shipped {{ !empty($review['shipped_date']) ? \App\Support\DisplayDates::businessDate($review['shipped_date']) : '—' }} · Received {{ !empty($review['received_date']) ? \App\Support\DisplayDates::businessDate($review['received_date']) : '—' }} · Shipping: {{ $review['shipping_method']==='quantity' ? 'By quantity' : 'By line total' }}</p>
</div>
<x-app.table-region label="Acquisition cost breakdown" class="app-list-table-panel"><table><thead><tr><th>Item</th><th>Units</th><th>Cost</th><th>Fee</th><th>Other fees</th><th>Discount</th><th>Tax</th><th>Shipping</th><th>Acquisition</th><th>Acquisition/unit</th></tr></thead><tbody>
@foreach($review['lines'] as $itemId=>$line)<tr><th>{{ $items->firstWhere('id',(int)$itemId)?->name ?: '#'.$itemId }}</th><td>{{ number_format($line['quantity']) }}</td>@foreach(['cost','fee','other','discount','tax','shipping','acquisition'] as $field)<td>${{ \App\Support\PurchaseCosting::dollars($line[$field]) }}</td>@endforeach<td>${{ $line['acquisition_unit'] }}</td></tr>@endforeach
</tbody><tfoot><tr><th colspan="8">Total invoice (USD)</th><td colspan="2">${{ \App\Support\PurchaseCosting::dollars($review['total']) }}</td></tr></tfoot></table></x-app.table-region>
<div class="app-content app-record-content">
@if($mode==='receipt')<p>Receipt clears this purchase’s saved Ordered expectation and adds only these actual units. Catalog averages use current held storage, active events and In Transit under the confirmation lock; Ordered is excluded. Projections may change before confirmation.</p></div>
<x-app.table-region label="Receipt stock and cost preview" class="app-list-table-panel"><table><thead><tr><th>Item</th><th>Held now → after</th><th>Catalog cost now → after</th></tr></thead><tbody>@foreach($review['projection'] as $itemId=>$projection)<tr><th>{{ $items->firstWhere('id',(int)$itemId)?->name }}</th><td>{{ number_format($projection['held']) }} → {{ number_format($projection['after_held']) }}</td><td>{{ \App\Support\StockNumbers::displayCost($projection['before_cost']) }} → {{ \App\Support\StockNumbers::displayCost($projection['after_cost']) }}</td></tr>@endforeach</tbody></table></x-app.table-region>
<div class="app-content app-record-content">@else<p>Confirmation changes only Ordered expectations. Held stock and catalog cost stay unchanged.</p>@endif
@else
<p>{{ $mode==='shipment' ? 'Shipped' : 'Received' }} date: {{ \App\Support\DisplayDates::businessDate($review['date']) }}</p></div>
<x-app.table-region label="Relocation posting review" class="app-list-table-panel"><table><thead><tr><th>Item</th><th>Sent</th>@if($mode==='receipt')<th>Received</th><th>Stock effect</th>@endif</tr></thead><tbody>
@foreach($review['lines'] as $itemId=>$line)<tr><th>{{ $items->firstWhere('id',(int)$itemId)?->name ?: '#'.$itemId }}</th><td>{{ number_format((int)$line['sent']) }}</td>@if($mode==='receipt')@php($difference=(int)($line['received']??0)-(int)$line['sent'])<td>{{ number_format((int)($line['received']??0)) }}</td><td>@if($difference<0){{ number_format(-$difference) }} missing: remove this remainder from In Transit through a linked shortage adjustment.@elseif($difference>0){{ number_format($difference) }} extra: add this quantity at destination through a linked overage adjustment.@elseMatching transfer; held stock preserved.@endif</td>@endif</tr>@endforeach
</tbody></table></x-app.table-region>
<div class="app-content app-record-content">
@if($mode==='shipment')<p>Confirmation transfers actual sent units from {{ $locations->firstWhere('id',$row->source_location_id)?->name }} to In Transit. Requested quantities reserve nothing. Held quantity and Unit cost stay unchanged.</p>@else<p>Confirmation clears all sent units from this request’s In Transit contribution and adds the actual received units at {{ $locations->firstWhere('id',$row->destination_location_id)?->name }}. Unit cost stays unchanged.</p>@if($review['discrepancy'])<p class="warning">Permanent discrepancy: total held stock changes by received minus sent. This requires a Manager or Admin. Confirm only when this is the final outcome; otherwise go back and Save counts while awaiting more boxes.</p>@endif<p>{{ $review['explanation'] ?: 'No explanation entered.' }}</p>@endif
@endif
<form method="post" action="{{ $base }}/fulfillment/{{ $mode }}/confirm/{{ $token }}" class="actions" @if($kind === 'relocation') id="relocation-counts" data-relocation-form="counts" @else id="purchase-invoice" data-purchase-form="invoice-review" @endif>
@if($kind === 'relocation')<input type="hidden" name="work" value="{{ $token }}"><input type="hidden" name="progress_step" value="review"><input type="hidden" name="{{ $mode === 'shipment' ? 'shipped_date' : 'received_date' }}" value="{{ $draft['values'][$mode === 'shipment' ? 'shipped_date' : 'received_date'] ?? '' }}"><input type="hidden" name="explanation" value="{{ $draft['values']['explanation'] ?? '' }}">@foreach($draft['values']['lines'] as $progressItem => $progressLine)<input type="hidden" name="lines[{{ $progressItem }}][{{ $mode === 'shipment' ? 'sent' : 'received' }}]" value="{{ $progressLine[$mode === 'shipment' ? 'sent' : 'received'] ?? '' }}">@endforeach
@endif
@if($kind === 'purchase')
<input type="hidden" name="work_version" value="{{ $draft['version'] ?? 0 }}"><input type="hidden" name="confirmation_hash" value="{{ hash('sha256', json_encode(\App\Support\PurchaseProgress::fields($draft['values'], 'invoice'), JSON_THROW_ON_ERROR)) }}">
<input type="hidden" name="work" value="{{ $token }}"><input type="hidden" name="progress_step" value="review"><input type="hidden" name="revision" value="{{ $draft['revision'] }}">
@foreach(\App\Support\PurchaseProgress::fields($draft['values'], 'invoice') as $progressName => $progressValue)<input type="hidden" data-purchase-field name="{{ $progressName }}" value="{{ $progressValue }}">@endforeach
@endif
@csrf<input type="hidden" name="_deliberate" value="1"><a href="{{ $base }}/fulfillment/{{ $mode }}?work={{ $token }}">Back to details</a><a class="discard" href="{{ $base }}">Cancel {{ $mode }}</a>
@if($kind!=='relocation' || $mode!=='receipt' || !$review['discrepancy'] || \App\Support\RequestValues::manages(auth()->user(),'manager'))<button class="primary">{{ $mode==='order' ? 'Confirm order' : ($mode==='shipment' ? 'Confirm shipment' : ($kind==='purchase' ? 'Confirm receipt' : 'Complete receiving')) }}</button>@endif</form>
</div>
@endsection
