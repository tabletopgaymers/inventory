@extends('layout')
@section('title', 'Purchase request')
@section('subtitle'){{ $values['title'] ?? $row->title }}@endsection
@section('title-status')<x-app.status-badge :status="$row->status" />@endsection
@section('page-actions')
@if($editable && in_array($tab, ['order','invoice','charges'], true))<x-workflow.save-progress form="purchase-invoice-form" />@endif
@endsection
@section('content-sections')
@php
    $readonly = !($editable ?? false) || in_array($row->status, ['Received','Cancelled'], true);
    $stepLabels = config('purchase-workflow.tabs');
    $panelLabels = config('purchase-workflow.panels');
    $buttonLabels = config('purchase-workflow.buttons');
    $tabs = array_diff_key($stepLabels, array_flip(['basic','material','review','receive']));
    $tab = array_key_exists($tab, $tabs) ? $tab : 'order';
    $base = '/purchase/work/'.$token;
    $editTab = 'invoice';
    $reviewTab = 'order-review';
    $next = ['order'=>'invoice','invoice'=>'charges','charges'=>$reviewTab];
    $requestedSupplier = str_starts_with($work['vendor_choice'] ?? '', 'supplier:') ? $suppliers->firstWhere('id', (int) substr($work['vendor_choice'], 9)) : null;
@endphp
@vite(['resources/css/purchase.css', 'resources/js/purchase-work-invoice.js'])
<x-app.panel-stack>
<div class="app-content" data-progress-feedback hidden></div>
@include('purchase.tabs')
@if($reviewErrors)<div class="app-content error" role="alert"><ul>
@foreach($reviewErrors as $field=>$messages)
@php
    $targetTab=null; $targetId=null; $targetMode=$mode;
    if(preg_match('/^lines\.([A-Za-z0-9-]+)\.(quantity|unit|cost|fee|description|item_id)$/',$field,$match)) { $targetTab=$editTab; $targetId='purchase-line-'.$match[1].'-'.($match[2]==='item_id'?'description':$match[2]); }
    elseif(in_array($field,['title','supplier_id','delivery_choice','delivery_name','delivery_address','delivery_email','delivery_phone','receiving_location_id','ordered_date','shipped_date','received_date'])) { $targetTab=$field==='received_date' ? 'receive' : 'order'; $targetId='purchase-'.$field; if($field!=='received_date')$targetMode='order'; }
    elseif(in_array($field,['other_fees','discount','tax','shipping','shipping_method'])) { $targetTab='charges'; $targetId='purchase-'.$field; }
    elseif($field==='order') { $targetTab='order-review';$targetMode='order';$targetId='app-main'; }
@endphp
@foreach((array)$messages as $message)<li>@if($targetTab)<a href="{{ $base }}?{{ http_build_query(['tab'=>$targetTab,'mode'=>$targetMode]) }}#{{ $targetId }}">{{ $message }}</a>@else{{ $message }}@endif

</li>@endforeach
@endforeach
</ul></div>@endif
<form class="app-panel-stack" id="purchase-invoice-form" method="post" action="{{ $base }}/invoice" data-purchase-invoice data-workspace-dirty="{{ $invoiceDirty ? 'true' : 'false' }}" novalidate>
@csrf
<input type="hidden" name="purchase_form" value="invoice">
<input type="hidden" name="revision" value="{{ $revision }}"><input type="hidden" name="mode" value="{{ $mode }}"><input type="hidden" name="tab" value="{{ $tab }}"><input type="hidden" name="next_tab" value="{{ $next[$tab] ?? $tab }}">
@foreach($tabs as $section=>$heading)
<section class="purchase-step app-panel-stack" data-step="{{ $section }}" @if($section!==$tab) hidden @endif>
<div class="app-content app-record-content purchase-panel"><x-workflow.heading><h2>{{ $panelLabels[$section] }}</h2>
@if(!$readonly)
@if(isset($next[$section]))<x-app.action variant="primary" type="submit" name="action" value="next" >{{ $buttonLabels[$section] }}</x-app.action>@elseif($section===$reviewTab)<x-app.action variant="primary" type="submit" name="action" value="confirm"  :disabled="!$preview || count($reviewErrors)>0">{{ $buttonLabels[$section] }}</x-app.action>@endif
@endif
</x-workflow.heading>
@if($section==='order')
@php($actualSupplier=$suppliers->firstWhere('id',(int)($values['supplier_id'] ?? 0)))
<fieldset class="app-entry-fieldset" @disabled($mode==='receipt')>
<div class="app-columns app-columns-fields">
<div>
<label>Title<input id="purchase-title" name="title" value="{{ $values['title'] ?? $row->title }}" @readonly($readonly)></label>
<label>Supplier<select id="purchase-supplier_id" name="supplier_id" @disabled($readonly)><option value="">Choose a supplier</option>@foreach($suppliers as $choice)@if(in_array($choice->state,['active','unverified']) || (string)$choice->id===(string)($values['supplier_id'] ?? ''))<option value="{{ $choice->id }}" data-supplier-info="{{ json_encode(['state'=>$choice->state,'website'=>$choice->website,'contact_name'=>$choice->contact_name,'email'=>$choice->email,'phone'=>$choice->phone]) }}" @selected((string)($values['supplier_id'] ?? '')===(string)$choice->id)>{{ $choice->name }}{{ $choice->state==='unverified' ? ' (Unverified)' : '' }}</option>@endif @endforeach</select></label>
<fieldset data-supplier-information @if(!$actualSupplier) hidden @endif><legend>Supplier information</legend>
<x-app.detail-list variant="content"  data-supplier-details>
@if($actualSupplier)<dt>Supplier status</dt><dd>{{ ucfirst($actualSupplier->state) }}</dd>@foreach(['website'=>'Website','contact_name'=>'Contact','email'=>'Email','phone'=>'Phone'] as $field=>$label)@if($actualSupplier->$field)<dt>{{ $label }}</dt><dd>{{ $actualSupplier->$field }}</dd>@endif @endforeach
@endif
</x-app.detail-list>
@if(!$readonly)<p class="notice" data-supplier-verification @if($actualSupplier?->state!=='unverified') hidden @endif>This supplier needs explicit verification before Record order. <x-app.action type="button" size="small" data-supplier-review data-purchase-work="{{ $token }}" data-review-url="/catalog/suppliers/{{ $actualSupplier?->id }}/edit?purchase_work={{ $token }}">Review and verify supplier</x-app.action></p>@endif
</fieldset>
@if(!$readonly && $row->status!=='Draft')<div class="app-inline-group"><x-app.action type="submit" name="action" value="replace_vendor" data-replace-vendor data-original-supplier="{{ $row->supplier_id ?: $requestedSupplier?->id }}" :disabled="true">Replace supplier</x-app.action><label class="app-choice"><input type="checkbox" name="delete_unused_vendor" value="1" disabled> Remove the original supplier if unused</label></div><p class="app-field-help">Replacement preserves the submitted and current supplier details in a permanent note. Used or shared suppliers are retained.</p>@endif
<label>Procurer<input value="{{ $values['procurer_name'] ?? auth()->user()->displayName() }}" readonly></label>
@foreach(['ordered_date'=>'Ordered','shipped_date'=>'Shipped'] as $field=>$label)<label>{{ $label }}<input id="purchase-{{ $field }}" type="date" name="{{ $field }}" value="{{ $values[$field] ?? '' }}" @readonly($readonly)></label>@endforeach
@if($mode==='order')<input type="hidden" name="received_date" value="{{ $values['received_date'] ?? '' }}">@endif
</div>
<div>
<label>Deliver to<select id="purchase-delivery_choice" name="delivery_choice" data-delivery-choice @disabled($readonly)><option value="">Choose destination</option>
@if($readonly)<option value="{{ $values['delivery_choice'] ?? '' }}" selected>{{ $values['delivery_name'] ?? 'Saved destination' }}</option>@else
<optgroup label="Storage locations">@foreach($locations as $location)@if($location->state==='active' || ($values['delivery_choice'] ?? '')==='location:'.$location->id)<option value="location:{{ $location->id }}" @selected(($values['delivery_choice'] ?? '')==='location:'.$location->id) data-name="{{ $location->name }}" data-address="{{ $location->mailing_address }}">{{ $location->name }}</option>@endif @endforeach</optgroup>
<optgroup label="Current users">@foreach($deliveryUsers as $user)<option value="user:{{ $user->id }}" @selected(($values['delivery_choice'] ?? '')==='user:'.$user->id) data-name="{{ $user->displayName() }}" data-address="{{ $user->mailing_address }}" data-email="{{ $user->deliveryEmail() }}" data-phone="{{ $user->mobile_number }}">{{ $user->displayName() }}{{ !$user->enabled ? ' (Inactive)' : '' }}</option>@endforeach</optgroup>
<option value="other" @selected(($values['delivery_choice'] ?? '')==='other')>OTHER</option>@endif</select></label>
<input type="hidden" name="receiving_location_id" value="{{ str_starts_with($values['delivery_choice'] ?? '', 'location:') ? substr($values['delivery_choice'],9) : '' }}">
@foreach(['delivery_name'=>'Name','delivery_address'=>'Mailing address','delivery_email'=>'Email','delivery_phone'=>'Phone'] as $field=>$label)
<x-app.field :label="$label" :for="'purchase-'.$field" :name="$field">@if($field==='delivery_address')<textarea id="purchase-{{ $field }}" name="{{ $field }}" rows="4" maxlength="5000" placeholder="Provide the delivery address in a format appropriate to its location." @readonly($readonly)>{{ $values[$field] ?? '' }}</textarea>@else<input id="purchase-{{ $field }}" name="{{ $field }}" value="{{ $values[$field] ?? '' }}" maxlength="255" @if(in_array($field,['delivery_email','delivery_phone'])) data-optional @endif @readonly($readonly)>@endif</x-app.field>
@endforeach
<p class="app-field-help">These delivery details belong to this purchase. Changes do not update the user or storage location.</p>
</div></div>
</fieldset>
@if(!$readonly && $mode==='order' && $row->status==='Ordered')<p>Mark shipped records the date and status; it does not move stock.</p><x-app.action type="submit" name="action" value="ship">Mark shipped</x-app.action>@endif
@elseif($section==='invoice')
<p>Enter the actual supplier invoice. Requested quantities remain a reference.</p>
@elseif($section==='charges')
@include('purchase.partials.charges')
@elseif($section==='order-review')
<x-app.detail-list variant="content" ><dt>Title</dt><dd>{{ $values['title'] ?? $row->title }}</dd><dt>Supplier</dt><dd>{{ $preview['vendor_snapshot']['name'] ?? $suppliers->firstWhere('id',(int)($values['supplier_id'] ?? 0))?->name ?? '—' }}</dd><dt>Deliver to</dt><dd>{{ $values['delivery_name'] ?? '—' }}</dd><dt>Mailing address</dt><dd>{{ $values['delivery_address'] ?? '—' }}</dd><dt>{{ $mode==='receipt' ? 'Received' : 'Ordered' }}</dt><dd>{{ $reviewDate }}</dd></x-app.detail-list>
@if($row->status==='Received')<p>Received is final. These are stored purchase values.</p>
@elseif($mode==='order')@if($section!=='order-review')<p>Recording the order changes Ordered quantities only for catalog items going to storage. Held stock and catalog Unit Cost do not change until final receipt.</p>@endif
@else<p>Final receipt clears this purchase’s Ordered quantities and applies the confirmed stock and Unit Cost effects.</p>@endif
@if(!$preview)<p>Complete the invoice and required details before confirmation.</p>@endif
@endif
</div>
@if($section===$editTab)
@include('purchase.partials.invoice-material')
@endif
@if($section===$reviewTab || $section==='charges')
<div data-allocation-preview-host>
@if($preview)@include('purchase.partials.acquisition-breakdown', ['showRecalculate' => $section !== 'order-review'])@endif
</div>

@endif
</section>
@endforeach
</form>
<x-app.collapsible-panel title="Permanent notes"><form method="post" action="{{ $base }}/note">@csrf<label>Note<textarea name="note" required maxlength="5000" rows="3"></textarea></label><x-app.action type="submit">Add note</x-app.action></form>@foreach($notes as $note)<article><p><strong>{{ $note->actor_name }}</strong> · {{ \App\Support\DisplayDates::timestamp($note->occurred_at) }}</p><p class="plain-text">{{ $note->body }}</p></article>@endforeach</x-app.collapsible-panel>
@if($procurement && in_array($row->status,['Draft','Request','Ordered','Shipped']))
<x-app.collapsible-panel title="Other actions"><div class="app-inline-group">@if(!$readonly && $mode==='order')<x-app.action type="submit" form="purchase-invoice-form" name="action" value="discard_order">Discard order draft</x-app.action>@endif<form id="purchase-transition-form" class="app-inline-group" method="post" action="{{ $base }}/transition" data-confirm-transition>@csrf<input type="hidden" name="revision" value="{{ $revision }}">@if($row->status!=='Draft')<x-app.action type="submit" name="action" value="backward">Return to {{ ['Request'=>'Draft','Ordered'=>'Request','Shipped'=>'Ordered'][$row->status] }}</x-app.action>@endif <x-app.action type="submit" name="action" value="cancel">Cancel purchase</x-app.action></form></div></x-app.collapsible-panel>
@endif

</x-app.panel-stack>
@endsection


