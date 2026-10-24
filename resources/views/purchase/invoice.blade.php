@extends('layout')
@section('title', $row->title ?: 'Purchase')
@section('title-status')<span class="status-badge">{{ $row->status }}</span>@endsection
@section('page-actions')
@if($editable)<button form="purchase-invoice-form" name="action" value="save" data-save-shortcut>Save progress</button>@endif
@endsection
@section('content-sections')
@php
    $readonly = !($editable ?? false) || in_array($row->status, ['Received','Cancelled'], true);
    $tabs = ['order'=>'Order details','invoice'=>'Order invoice','charges'=>$mode==='receipt' ? 'Receipt charges' : 'Order charges','order-review'=>'Order review','receive'=>'Receive','confirmation'=>'Confirmation'];
    $tab = array_key_exists($tab, $tabs) ? $tab : ($mode === 'receipt' ? 'receive' : 'order');
    $base = '/purchase/work/'.$token;
    $editTab = $mode === 'receipt' ? 'receive' : 'invoice';
    $reviewTab = $mode === 'receipt' ? 'confirmation' : 'order-review';
    $next = ['order'=>'invoice','invoice'=>'charges','charges'=>$reviewTab,'receive'=>'confirmation'];
@endphp
<link rel="stylesheet" href="/purchase.css?v={{ substr(hash_file('sha256', public_path('purchase.css')),0,12) }}">
@include('purchase.tabs')
@if($reviewErrors)<div class="app-content error" role="alert"><ul>
@foreach($reviewErrors as $field=>$messages)
@php
    $targetTab=null; $targetId=null;
    if(preg_match('/^lines\.(\d+)\.(quantity|unit|cost|fee)$/',$field,$match)) { $targetTab=$editTab; $targetId='purchase-line-'.$match[1].'-'.$match[2]; }
    elseif(in_array($field,['title','supplier_id','receiving_location_id','ordered_date','shipped_date','received_date'])) { $targetTab=$field==='received_date' ? 'receive' : 'order'; $targetId='purchase-'.$field; }
    elseif(in_array($field,['other_fees','discount','tax','shipping','shipping_method'])) { $targetTab='charges'; $targetId='purchase-'.$field; }
@endphp
@foreach((array)$messages as $message)<li>@if($targetTab)<a href="{{ $base }}?{{ http_build_query(['tab'=>$targetTab,'mode'=>$mode]) }}#{{ $targetId }}">{{ $message }}</a>@else{{ $message }}@endif</li>@endforeach
@endforeach
</ul></div>@endif
<form id="purchase-invoice-form" method="post" action="{{ $base }}/invoice" data-purchase-invoice novalidate>
@csrf
<input type="hidden" name="revision" value="{{ $revision }}"><input type="hidden" name="mode" value="{{ $mode }}"><input type="hidden" name="tab" value="{{ $tab }}"><input type="hidden" name="next_tab" value="{{ $next[$tab] ?? $tab }}">
@foreach($tabs as $section=>$heading)
<section class="purchase-step" data-step="{{ $section }}" @if($section!==$tab) hidden @endif>
<div class="app-content app-record-content purchase-panel"><x-workflow.heading><h2>{{ $section==='order' && $mode==='receipt' ? 'Receipt details' : $heading }}</h2>
@if(!$readonly)
@if(isset($next[$section]))<button name="action" value="next" class="primary">Next &gt;</button>@elseif($section===$reviewTab)<button name="action" value="confirm" class="primary" @disabled(!$preview || count($reviewErrors)>0)>{{ $mode==='receipt' ? 'Confirm receipt' : (in_array($row->status,['Ordered','Shipped']) ? 'Update order' : 'Record order') }}</button>@endif
@endif
</x-workflow.heading>
@if($section==='order')
<div class="purchase-fields">
<label>Title<input id="purchase-title" name="title" value="{{ $values['title'] ?? $row->title }}" @readonly($readonly)></label>
@foreach(['supplier_id'=>['Supplier',$suppliers],'receiving_location_id'=>['Receiving location',$locations]] as $field=>$definition)
<label>{{ $definition[0] }}<select id="purchase-{{ $field }}" name="{{ $field }}" @disabled($readonly)><option value="">Choose…</option>@foreach($definition[1] as $choice)<option value="{{ $choice->id }}" @selected((string)($values[$field] ?? '')===(string)$choice->id)>{{ $choice->name }}</option>@endforeach</select></label>
@endforeach
@foreach(['ordered_date'=>'Ordered','shipped_date'=>'Shipped'] as $field=>$label)<label>{{ $label }}<input id="purchase-{{ $field }}" type="date" name="{{ $field }}" value="{{ $values[$field] ?? '' }}" @readonly($readonly)></label>@endforeach
@if($mode==='order')<input type="hidden" name="received_date" value="{{ $values['received_date'] ?? '' }}">@endif
</div>
@if(!$readonly && $row->status==='Ordered')<p>Mark shipped records the shipping date and status. Use Update order on Order review to change the recorded invoice.</p><button name="action" value="ship">Mark shipped</button>@endif
@elseif($section==='invoice' || $section==='receive')
<p>{{ $section==='receive' ? 'Verify the actual delivery and final invoice. Saving does not add stock.' : 'Enter the actual supplier invoice. Requested quantities remain a reference.' }}</p>
@if($section==='receive' && $mode==='receipt')<label>Received<input id="purchase-received_date" type="date" name="received_date" value="{{ $values['received_date'] ?? '' }}" @readonly($readonly)></label><p><a href="{{ $base }}?tab=order&mode=receipt">{{ $readonly ? 'Receipt details' : 'Edit receipt details' }}</a> · <a href="{{ $base }}?tab=charges&mode=receipt">{{ $readonly ? 'Receipt charges' : 'Edit receipt charges' }}</a></p>@endif
@elseif($section==='charges')
<p>{{ $mode==='receipt' ? 'Receipt charges' : 'Order charges' }} — USD. Final allocations are calculated when saved or reviewed.</p><div class="purchase-fields">
@foreach(['other_fees'=>'Other fees','discount'=>'Discount','tax'=>'Tax','shipping'=>'Shipping'] as $field=>$label)<label>{{ $label }}<input id="purchase-{{ $field }}" name="{{ $field }}" value="{{ $values[$field] ?? '0' }}" inputmode="decimal" @readonly($readonly)></label>@endforeach
<label>Shipping allocation<select id="purchase-shipping_method" name="shipping_method" @disabled($readonly)><option value="line_total" @selected(($values['shipping_method'] ?? 'line_total')==='line_total')>By line total</option><option value="quantity" @selected(($values['shipping_method'] ?? '')==='quantity')>By quantity</option></select></label></div>
@elseif($section==='order-review' || $section==='confirmation')
<dl><dt>Supplier</dt><dd>{{ $suppliers->firstWhere('id',(int)($values['supplier_id'] ?? 0))?->name ?? '—' }}</dd><dt>Receiving location</dt><dd>{{ $locations->firstWhere('id',(int)($values['receiving_location_id'] ?? 0))?->name ?? '—' }}</dd><dt>{{ $mode==='receipt' ? 'Received' : 'Ordered' }}</dt><dd>{{ $reviewDate }}</dd></dl>
@if($row->status==='Received')
<p>{{ $mode==='receipt' ? 'Receipt confirmed. Pending quantities were cleared, actual stock was added, and catalog Unit Cost was updated. Received is final.' : 'This is the recorded order. Confirmation shows the final received quantities and costs.' }}</p>
@else<p>{{ $mode==='receipt' ? 'Confirming receipt clears this purchase’s Ordered expectation, adds actual stock, and updates catalog Unit Cost. Received is final.' : 'Recording an order changes only its Ordered expectation. Storage stock and catalog Unit Cost do not change.' }}</p>@endif
@if(!$preview)<p>Complete the invoice and required details before confirmation.</p>@endif
@endif
</div>
@if($section===$editTab)
<x-app.table-region label="Invoice material" class="app-list-table-panel purchase-table" :nowrap="true" :quantity-fields="true" numeric="all"><table><thead><tr><th></th><th>Item</th><th>{{ $mode==='receipt' ? 'Ordered' : 'Requested' }}</th><th>{{ $mode==='receipt' ? 'Received' : 'Quantity' }}</th><th>Unit ($)</th><th>Cost ($)</th><th>Fee ($)</th><th>Line ($)</th><th></th></tr></thead><tbody>
@php($group=null)
@foreach($items as $item)
@php($id=$item['id'])
@if($group!==$item['category'].' : '.$item['collection']) @php($group=$item['category'].' : '.$item['collection'])<tr><td></td><th colspan="8">{{ $group }}</th></tr>@endif
<tr data-invoice-line><td>@if(!$readonly)<button name="remove" value="{{ $id }}" aria-label="Remove {{ $item['name'] }}" class="purchase-remove">✕</button>@endif</td><th>{{ $item['name'] }}</th><td>{{ $mode==='receipt' ? ($ordered[$id] ?? 0) : (is_array($requested[$id] ?? null) ? ($requested[$id]['quantity'] ?? '—') : ($requested[$id] ?? '—')) }}</td>
@foreach(['quantity','unit','cost','fee'] as $field)<td><input id="purchase-line-{{ $id }}-{{ $field }}" name="lines[{{ $id }}][{{ $field }}]" data-invoice-field="{{ $field }}" aria-label="{{ ucfirst($field) }} for {{ $item['name'] }}" inputmode="{{ $field==='quantity' ? 'numeric' : 'decimal' }}" value="{{ $values['lines'][$id][$field] ?? '' }}" @readonly($readonly)></td>@endforeach
<td data-line-total>—</td><td><input type="hidden" name="lines[{{ $id }}][basis]" value="{{ $values['lines'][$id]['basis'] ?? 'unit' }}" data-line-basis>@if(!$readonly)<button name="recalculate" value="{{ $id }}">Recalculate</button>@endif</td></tr>
@endforeach
</tbody></table></x-app.table-region>
@if(!$readonly)<div class="app-content purchase-panel purchase-fields"><label>Add material<select name="item_id"><option value="">Choose item…</option>
@foreach($collections as $collection)<optgroup label="{{ $collection->category_name }} : {{ $collection->name }}">@foreach($catalogItems->where('collection_id',$collection->id)->where('state','active') as $choice)@if(!array_key_exists($choice->id,$values['lines'] ?? []))<option value="{{ $choice->id }}">{{ $choice->name }}</option>@endif @endforeach</optgroup>@endforeach
</select></label><button name="action" value="add">Add selected</button></div>@endif
@endif
@if(($section===$reviewTab || $section==='charges') && $preview)
<x-app.table-region label="Acquisition cost breakdown" class="app-list-table-panel purchase-table" :nowrap="true" :quantity-fields="true" numeric="all"><table><thead><tr><th>Item</th><th>Units</th>@foreach(['cost'=>'Cost','fee'=>'Fee','other'=>'Other fees','discount'=>'Discount','tax'=>'Tax','shipping'=>'Shipping','acquisition'=>'Acquisition'] as $key=>$label)<th>{{ $label }} ($)</th>@endforeach<th>Acquisition / unit</th></tr></thead><tbody>
@php($group=null)
@foreach($items as $item)
@php($id=$item['id'])
@if(isset($preview['lines'][$id]))
@php($line=$preview['lines'][$id])
@if($group!==$item['category'].' : '.$item['collection'])@php($group=$item['category'].' : '.$item['collection'])<tr><th colspan="10">{{ $group }}</th></tr>@endif
<tr><th>{{ $item['name'] }}</th><td>{{ number_format($line['quantity']) }}</td>@foreach(['cost','fee','other','discount','tax','shipping','acquisition'] as $field)<td>{{ \App\Support\PurchaseCosting::dollars($line[$field]) }}</td>@endforeach<td>${{ \Brick\Math\BigDecimal::of($line['acquisition_unit'])->toScale(3, \Brick\Math\RoundingMode::HalfUp) }}</td></tr>
@endif
@endforeach
</tbody><tfoot><tr><th colspan="9">Total USD</th><td>{{ \App\Support\PurchaseCosting::dollars($preview['total']) }}</td></tr></tfoot></table></x-app.table-region>
@if($section==='confirmation')
<x-app.table-region label="Received material" class="app-list-table-panel purchase-table" :nowrap="true" :quantity-fields="true" numeric="all"><table><thead><tr><th>Item</th><th>Ordered</th><th>Received</th><th>Difference</th></tr></thead><tbody>
@foreach(array_unique(array_merge(array_keys($ordered),array_keys($preview['lines']))) as $id)
@php($receivedQuantity=$preview['lines'][$id]['quantity'] ?? 0)
@php($variance=$receivedQuantity-($ordered[$id] ?? 0))
<tr><th>{{ $catalogItems->firstWhere('id',(int)$id)?->name ?? '#'.$id }}</th><td>{{ number_format($ordered[$id] ?? 0) }}</td><td>{{ number_format($receivedQuantity) }}</td><td style="color:{{ $variance<0 ? '#991b1b' : '#166534' }}">{{ $variance ? '['.($variance>0 ? '+' : '').number_format($variance).']' : '' }}</td></tr>
@endforeach
</tbody></table></x-app.table-region>
<x-app.table-region :label="$row->status==='Received' ? 'Receipt stock and cost' : 'Receipt stock and cost preview'" class="app-list-table-panel purchase-table" :nowrap="true" :quantity-fields="true" numeric="all">
<table><thead><tr><th>Item</th><th>{{ $row->status==='Received' ? 'Current location stock' : 'Location now → after' }}</th><th>{{ $row->status==='Received' ? 'Total held before → after receipt' : 'Total held now → after' }}</th><th>{{ $row->status==='Received' ? 'Unit Cost before → after receipt' : 'Catalog Unit Cost now → after' }}</th></tr></thead><tbody>
@foreach($preview['projection'] ?? [] as $id=>$projection)
<tr><th>{{ collect($items)->firstWhere('id',(int)$id)['name'] ?? '#'.$id }}</th>
<td>{{ number_format($locationStocks[$id] ?? 0) }}@if($row->status!=='Received') → {{ number_format(($locationStocks[$id] ?? 0)+$preview['lines'][$id]['quantity']) }}@endif</td>
<td>{{ number_format($projection['held']) }} → {{ number_format($projection['after_held']) }}</td>
<td>{{ \App\Support\StockNumbers::displayCost($projection['before_cost']) }} → {{ \App\Support\StockNumbers::displayCost($projection['after_cost']) }}</td></tr>
@endforeach
</tbody></table></x-app.table-region>
<div class="app-content"><p>Total held includes all storage, active events and In Transit.</p><p>{{ $row->status==='Received' ? 'Held and Unit Cost values were recorded at receipt. Location stock shows the current count.' : 'Preview may change before confirmation.' }}</p></div>
@endif
@endif
</section>
@endforeach
@if(!$readonly && $mode==='receipt')<div class="app-content purchase-panel"><button name="action" value="cancel_receipt">Cancel receiving</button></div>@endif
</form>
<div class="app-content"><details><summary>Permanent notes</summary><form method="post" action="{{ $base }}/note">@csrf<label>Note<textarea name="note" required maxlength="5000" rows="3"></textarea></label><button>Add note</button></form>@foreach($notes as $note)<article><p><strong>{{ $note->actor_name }}</strong> · {{ \App\Support\DisplayDates::timestamp($note->occurred_at) }}</p><p style="white-space:pre-wrap">{{ $note->body }}</p></article>@endforeach</details></div>
@if($procurement && in_array($row->status,['Draft','Request','Ordered','Shipped']))
<div class="app-content"><details><summary>Other actions</summary><form method="post" action="{{ $base }}/transition">@csrf<input type="hidden" name="revision" value="{{ $revision }}">@if($row->status!=='Draft')<button name="action" value="backward">Return to {{ ['Request'=>'Draft','Ordered'=>'Request','Shipped'=>'Ordered'][$row->status] }}</button>@endif <button name="action" value="cancel">Cancel purchase</button></form></details></div>
@endif
@vite('resources/js/purchase-work-invoice.js')
@endsection


