@extends('layout')
@section('title', $row->title ?: 'Purchase')
@section('title-status')<span class="status-badge">{{ $row->status }}</span>@endsection
@section('page-actions')
@if($editable)<button form="purchase-invoice-form" name="action" value="save" data-save-shortcut>Save progress</button>@endif
@endsection
@section('content-sections')
@php
    $readonly = !($editable ?? false) || in_array($row->status, ['Received','Cancelled'], true);
    $tabs = ['order'=>'Procurement','invoice'=>'Order','charges'=>$mode==='receipt' ? 'Receipt charges' : 'Order charges','order-review'=>'Order review','receive'=>'Receive','confirmation'=>'Confirmation'];
    $tab = array_key_exists($tab, $tabs) ? $tab : ($mode === 'receipt' ? 'receive' : 'order');
    $base = '/purchase/work/'.$token;
    $editTab = $mode === 'receipt' ? 'receive' : 'invoice';
    $reviewTab = $mode === 'receipt' ? 'confirmation' : 'order-review';
    $next = ['order'=>'invoice','invoice'=>'charges','charges'=>$reviewTab,'receive'=>'confirmation'];
    $requestedSupplier = str_starts_with($work['vendor_choice'] ?? '', 'supplier:') ? $suppliers->firstWhere('id', (int) substr($work['vendor_choice'], 9)) : null;
@endphp
@vite(['resources/css/purchase.css', 'resources/js/purchase-work-invoice.js'])
<x-app.panel-stack>
@include('purchase.tabs')
<div class="app-content" data-progress-feedback hidden></div>
@if($reviewErrors)<div class="app-content error" role="alert"><ul>
@foreach($reviewErrors as $field=>$messages)
@php
    $targetTab=null; $targetId=null;
    if(preg_match('/^lines\.([A-Za-z0-9-]+)\.(quantity|unit|cost|fee|description|item_id)$/',$field,$match)) { $targetTab=$editTab; $targetId='purchase-line-'.$match[1].'-'.($match[2]==='item_id'?'description':$match[2]); }
    elseif(in_array($field,['title','supplier_id','delivery_choice','delivery_name','delivery_address','delivery_email','delivery_phone','receiving_location_id','ordered_date','shipped_date','received_date'])) { $targetTab=$field==='received_date' ? 'receive' : 'order'; $targetId='purchase-'.$field; }
    elseif(in_array($field,['other_fees','discount','tax','shipping','shipping_method'])) { $targetTab='charges'; $targetId='purchase-'.$field; }
@endphp
@foreach((array)$messages as $message)<li>@if($targetTab)<a href="{{ $base }}?{{ http_build_query(['tab'=>$targetTab,'mode'=>$mode]) }}#{{ $targetId }}">{{ $message }}</a>@else{{ $message }}@endif</li>@endforeach
@endforeach
</ul></div>@endif
<form class="app-panel-stack" id="purchase-invoice-form" method="post" action="{{ $base }}/invoice" data-purchase-invoice novalidate>
@csrf
<input type="hidden" name="revision" value="{{ $revision }}"><input type="hidden" name="mode" value="{{ $mode }}"><input type="hidden" name="tab" value="{{ $tab }}"><input type="hidden" name="next_tab" value="{{ $next[$tab] ?? $tab }}">
@foreach($tabs as $section=>$heading)
<section class="purchase-step app-panel-stack" data-step="{{ $section }}" @if($section!==$tab) hidden @endif>
<div class="app-content app-record-content purchase-panel"><x-workflow.heading><h2>{{ $section==='order' && $mode==='receipt' ? 'Receipt details' : $heading }}</h2>
@if(!$readonly)
@if(isset($next[$section]))<button name="action" value="next" class="primary">Next &gt;</button>@elseif($section===$reviewTab)<button name="action" value="confirm" class="primary" @disabled(!$preview || count($reviewErrors)>0)>{{ $mode==='receipt' ? 'Confirm receipt' : (in_array($row->status,['Ordered','Shipped']) ? 'Update order' : 'Record order') }}</button>@endif
@endif
</x-workflow.heading>
@if($section==='order')
@php($actualSupplier=$suppliers->firstWhere('id',(int)($values['supplier_id'] ?? 0)))
<div class="app-form-row">
<div>
<label>Title<input id="purchase-title" name="title" value="{{ $values['title'] ?? $row->title }}" @readonly($readonly)></label>
<label>Vendor<select id="purchase-supplier_id" name="supplier_id" @disabled($readonly)><option value="">Choose a vendor</option>@foreach($suppliers as $choice)@if(in_array($choice->state,['active','unverified']) || (string)$choice->id===(string)($values['supplier_id'] ?? ''))<option value="{{ $choice->id }}" @selected((string)($values['supplier_id'] ?? '')===(string)$choice->id)>{{ $choice->name }}{{ $choice->state==='unverified' ? ' (Unverified)' : '' }}</option>@endif @endforeach</select></label>
@if($actualSupplier)
<dl><dt>Vendor status</dt><dd>{{ ucfirst($actualSupplier->state) }}</dd>@foreach(['website'=>'Website','contact_name'=>'Contact','email'=>'Email','phone'=>'Phone'] as $field=>$label)@if($actualSupplier->$field)<dt>{{ $label }}</dt><dd>{{ $actualSupplier->$field }}</dd>@endif @endforeach</dl>
@if(!$readonly && $actualSupplier->state==='unverified')<p class="notice">This vendor needs explicit verification before Record order. <a href="/catalog/suppliers/{{ $actualSupplier->id }}/edit">Review and verify vendor</a></p>@endif
@endif
@if(!$readonly && $row->status!=='Draft')<label><input type="checkbox" name="delete_unused_vendor" value="1"> Remove the original vendor if unused</label><button name="action" value="replace_vendor" data-replace-vendor>Replace vendor</button><p class="app-field-help">Replacement preserves the submitted and current vendor details in a permanent note. Used or shared vendors are retained.</p>@endif
<label>Procurer<input value="{{ $values['procurer_name'] ?? auth()->user()->displayName() }}" readonly></label>
@foreach(['ordered_date'=>'Ordered','shipped_date'=>'Shipped'] as $field=>$label)<label>{{ $label }}<input id="purchase-{{ $field }}" type="date" name="{{ $field }}" value="{{ $values[$field] ?? '' }}" @readonly($readonly)></label>@endforeach
@if($mode==='order')<input type="hidden" name="received_date" value="{{ $values['received_date'] ?? '' }}">@endif
</div>
<div>
<label>Deliver to<select id="purchase-delivery_choice" name="delivery_choice" data-delivery-choice @disabled($readonly)><option value="">Choose destination</option>
@if($readonly)<option value="{{ $values['delivery_choice'] ?? '' }}" selected>{{ $values['delivery_name'] ?? 'Saved destination' }}</option>@else
<optgroup label="Storage locations">@foreach($locations as $location)@if($location->state==='active' || ($values['delivery_choice'] ?? '')==='location:'.$location->id)<option value="location:{{ $location->id }}" @selected(($values['delivery_choice'] ?? '')==='location:'.$location->id) data-name="{{ $location->name }}" data-address="{{ $location->mailing_address }}">{{ $location->name }}</option>@endif @endforeach</optgroup>
<optgroup label="Current users">@foreach($deliveryUsers as $user)<option value="user:{{ $user->id }}" @selected(($values['delivery_choice'] ?? '')==='user:'.$user->id) data-name="{{ $user->displayName() }}" data-address="{{ $user->mailing_address }}" data-email="{{ $user->personal_email ?: $user->contact_email }}" data-phone="{{ $user->mobile_number }}">{{ $user->displayName() }}{{ !$user->enabled ? ' (Inactive)' : '' }}</option>@endforeach</optgroup>
<option value="other" @selected(($values['delivery_choice'] ?? '')==='other')>OTHER</option>@endif</select></label>
<input type="hidden" name="receiving_location_id" value="{{ str_starts_with($values['delivery_choice'] ?? '', 'location:') ? substr($values['delivery_choice'],9) : '' }}">
@foreach(['delivery_name'=>'Name','delivery_address'=>'Mailing address','delivery_email'=>'Email','delivery_phone'=>'Phone'] as $field=>$label)
<x-app.field :label="$label" :for="'purchase-'.$field" :name="$field">@if($field==='delivery_address')<textarea id="purchase-{{ $field }}" name="{{ $field }}" rows="4" maxlength="5000" placeholder="Provide the delivery address in a format appropriate to its location." @readonly($readonly)>{{ $values[$field] ?? '' }}</textarea>@else<input id="purchase-{{ $field }}" name="{{ $field }}" value="{{ $values[$field] ?? '' }}" maxlength="255" @if(in_array($field,['delivery_email','delivery_phone'])) data-optional @endif @readonly($readonly)>@endif</x-app.field>
@endforeach
@if(!empty($values['legacy_delivery']))<p class="notice">The recorded order identifies {{ $locations->firstWhere('id',(int)$values['receiving_location_id'])?->name ?? $values['delivery_name'] }}. No historical mailing address was recorded.</p>@endif
<p class="app-field-help">These delivery details belong to this purchase. Changes do not update the user or storage location.</p>
</div></div>
@if(!$readonly && $row->status==='Ordered')<p>Mark shipped records the date and status; it does not move stock.</p><button name="action" value="ship">Mark shipped</button>@endif
@elseif($section==='invoice' || $section==='receive')
<p>{{ $section==='receive' ? 'Verify the actual delivery and final invoice. Saving does not add stock.' : 'Enter the actual supplier invoice. Requested quantities remain a reference.' }}</p>
@if($section==='receive' && $mode==='receipt')<label>Received<input id="purchase-received_date" type="date" name="received_date" value="{{ $values['received_date'] ?? '' }}" @readonly($readonly)></label><p><a href="{{ $base }}?tab=order&mode=receipt">{{ $readonly ? 'Receipt details' : 'Edit receipt details' }}</a> · <a href="{{ $base }}?tab=charges&mode=receipt">{{ $readonly ? 'Receipt charges' : 'Edit receipt charges' }}</a></p>@endif
@elseif($section==='charges')
@include('purchase.partials.charges')
@elseif($section==='order-review' || $section==='confirmation')
<dl class="purchase-review"><dt>Title</dt><dd>{{ $values['title'] ?? $row->title }}</dd><dt>Vendor</dt><dd>{{ $preview['vendor_snapshot']['name'] ?? $suppliers->firstWhere('id',(int)($values['supplier_id'] ?? 0))?->name ?? '—' }}</dd><dt>Deliver to</dt><dd>{{ $values['delivery_name'] ?? '—' }}</dd><dt>Mailing address</dt><dd>{{ $values['delivery_address'] ?? '—' }}</dd><dt>{{ $mode==='receipt' ? 'Received' : 'Ordered' }}</dt><dd>{{ $reviewDate }}</dd></dl>
@if($row->status==='Received')<p>Received is final. These are stored purchase values.</p>
@elseif($mode==='order')<p>Recording the order changes Ordered quantities only for catalog items going to storage. Held stock and catalog Unit Cost do not change until final receipt.</p>
@else<p>Final receipt clears this purchase’s Ordered quantities and applies the confirmed stock and Unit Cost effects.</p>@endif
@if(!$preview)<p>Complete the invoice and required details before confirmation.</p>@endif
@if($section==='order-review' && $preview)
<h3>Ordered quantities</h3><dl class="purchase-review">@foreach($preview['lines'] as $line)<dt>{{ $line['description'] }}</dt><dd>{{ $line['item_id'] && str_starts_with($values['delivery_choice'] ?? '', 'location:') ? number_format($line['quantity']).' Ordered' : 'No inventory quantity' }}</dd>@endforeach</dl>
@endif
@endif
</div>
@if($section===$editTab)
@include('purchase.partials.invoice-material')
@endif
@if(($section===$reviewTab || $section==='charges') && $preview)
<p data-derived-stale hidden>Amounts changed. Open a fresh review to calculate the saved allocations.</p>
<x-app.table-region data-invoice-derived label="Acquisition cost breakdown" class="app-list-table-panel purchase-table" :nowrap="true" :quantity-fields="true" numeric="all"><table><thead><tr><th>Item</th><th>Units</th>@foreach(['cost'=>'Cost','fee'=>'Fee','other'=>'Other fees','discount'=>'Discount','tax'=>'Tax','shipping'=>'Shipping','acquisition'=>'Acquisition'] as $key=>$label)<th>{{ $label }} ($)</th>@endforeach<th>Acquisition / unit</th></tr></thead><tbody>
@php($group=null)
@foreach($items as $item)
@php($id=$item['id'])
@if(isset($preview['lines'][$id]))
@php($line=$preview['lines'][$id])
@if($group!==$item['group'])@php($group=$item['group'])<tr><th colspan="10">{{ $group }}</th></tr>@endif
<tr><th>{{ $item['name'] }}</th><td>{{ number_format($line['quantity']) }}</td>@foreach(['cost','fee','other','discount','tax','shipping','acquisition'] as $field)<td>{{ \App\Support\PurchaseCosting::dollars($line[$field]) }}</td>@endforeach<td>${{ \Brick\Math\BigDecimal::of($line['acquisition_unit'])->toScale(3, \Brick\Math\RoundingMode::HalfUp) }}</td></tr>
@endif
@endforeach
</tbody><tfoot><tr><th colspan="9">Total USD</th><td>{{ \App\Support\PurchaseCosting::dollars($preview['total']) }}</td></tr></tfoot></table></x-app.table-region>
@if($section==='confirmation')
<x-app.table-region data-invoice-derived label="Received material" class="app-list-table-panel purchase-table" :nowrap="true" :quantity-fields="true" numeric="all"><table><thead><tr><th>Item</th><th>Ordered</th><th>Received</th><th>Difference</th></tr></thead><tbody>
@foreach(array_unique(array_merge(array_keys($ordered),array_keys($preview['lines']))) as $id)
@php($receivedQuantity=$preview['lines'][$id]['quantity'] ?? 0)
@php($variance=$receivedQuantity-($ordered[$id] ?? 0))
<tr><th>{{ $catalogItems->firstWhere('id',(int)$id)?->name ?? '#'.$id }}</th><td>{{ number_format($ordered[$id] ?? 0) }}</td><td>{{ number_format($receivedQuantity) }}</td><td class="{{ $variance<0 ? 'table-variance-negative' : 'table-variance-positive' }}">{{ $variance ? '['.($variance>0 ? '+' : '').number_format($variance).']' : '' }}</td></tr>
@endforeach
</tbody></table></x-app.table-region>
<x-app.table-region data-invoice-derived :label="$row->status==='Received' ? 'Receipt stock and cost' : 'Receipt stock and cost preview'" class="app-list-table-panel purchase-table" :nowrap="true" :quantity-fields="true" numeric="all">
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
<x-app.collapsible-panel title="Permanent notes"><form method="post" action="{{ $base }}/note">@csrf<label>Note<textarea name="note" required maxlength="5000" rows="3"></textarea></label><button>Add note</button></form>@foreach($notes as $note)<article><p><strong>{{ $note->actor_name }}</strong> · {{ \App\Support\DisplayDates::timestamp($note->occurred_at) }}</p><p class="plain-text">{{ $note->body }}</p></article>@endforeach</x-app.collapsible-panel>
@if($procurement && in_array($row->status,['Draft','Request','Ordered','Shipped']))
<x-app.collapsible-panel title="Other actions"><form method="post" action="{{ $base }}/transition">@csrf<input type="hidden" name="revision" value="{{ $revision }}">@if($row->status!=='Draft')<button name="action" value="backward">Return to {{ ['Request'=>'Draft','Ordered'=>'Request','Shipped'=>'Ordered'][$row->status] }}</button>@endif <button name="action" value="cancel">Cancel purchase</button></form></x-app.collapsible-panel>
@endif

</x-app.panel-stack>
@endsection


