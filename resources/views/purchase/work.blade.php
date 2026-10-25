@extends('layout')
@section('title', 'Purchase request')
@section('title-status')<span class="status-badge">{{ $row->status ?? 'New' }}</span>@endsection
@section('page-actions')
<button form="purchase-request-form" type="submit" name="action" value="save" class="primary app-save-progress" data-save-shortcut @disabled(!$editable)>Save progress</button>
@endsection
@section('content-sections')
@vite(['resources/css/purchase.css', 'resources/js/purchase-request.js'])

<x-app.panel-stack>
<form class="app-panel-stack" id="purchase-request-form" action="/purchase/work/{{ $token }}" method="post" novalidate data-tab="{{ $tab }}" data-editable="{{ $editable ? 'true' : 'false' }}" data-can-submit="{{ $canSubmit ? 'true' : 'false' }}" data-workspace-dirty="{{ !empty($work['material_dirty']) ? 'true' : 'false' }}">
@csrf
<input type="hidden" name="purchase_form" value="request">
<input type="hidden" name="revision" value="{{ $work['revision'] }}">
<input type="hidden" name="tab" value="{{ $tab }}" data-current-tab>
<x-workflow.tabs prefix="purchase" label="Purchase request steps" :active="$tab" :tabs="['basic'=>'Basic info','material'=>'Material','review'=>'Review']">
@if($work['id'])
<nav class="workflow-tabs" aria-label="Purchase fulfillment steps">
@foreach(['order'=>'Order details','invoice'=>'Order invoice','charges'=>'Order charges','order-review'=>'Order review','receive'=>'Receive','confirmation'=>'Confirmation'] as $key=>$label)
@if(!in_array($key,['receive','confirmation']) || in_array($row->status,['Ordered','Shipped','Received']))<a class="button" href="/purchase/work/{{ $token }}?tab={{ $key }}">{{ $label }}</a>@endif
@endforeach
</nav>
@endif
</x-workflow.tabs>
<div class="error" role="alert" data-purchase-errors @if($tab !== 'review' || !$reviewErrors) hidden @endif>
@foreach($reviewErrors as $messages)@foreach((array)$messages as $message)<p>{{ $message }}</p>@endforeach @endforeach
</div>
<x-workflow.tab-panel class="app-content" id="purchase-panel-basic" labelledby="purchase-tab-basic" :active="$tab === 'basic'">
<x-workflow.heading><h2>Basic info</h2><button type="submit" class="primary" name="action" value="next" @disabled(!$editable)>Next &gt;</button></x-workflow.heading>
<fieldset class="app-entry-fieldset">
<div class="app-form-row">
<label for="purchase-title">Title<input id="purchase-title" name="title" required maxlength="255" value="{{ old('title',$work['title']) }}" @readonly(!$editable)></label>
<label for="purchase-requestor">Requestor<input id="purchase-requestor" value="{{ $requesterName }} ({{ $requesterId }})" readonly></label>
</div>
<div class="app-form-row">
<div>
<label for="purchase-vendor">Vendor or suggested source<select id="purchase-vendor" name="vendor_choice" required data-other-choice="vendor" aria-controls="purchase-vendor-other" @disabled(!$editable)>
<option value="">Choose a supplier</option>
@foreach($suppliers as $supplier)
@if(in_array($supplier->state, ['active','unverified']) || $work['vendor_choice'] === 'supplier:'.$supplier->id)<option value="supplier:{{ $supplier->id }}" @if((string)($work['vendor_other_id'] ?? '') === (string)$supplier->id) data-other="true" @endif @selected($work['vendor_choice'] === 'supplier:'.$supplier->id)>{{ (string)($work['vendor_other_id'] ?? '') === (string)$supplier->id ? 'OTHER: '.$supplier->name : $supplier->name.($supplier->state === 'unverified' ? ' (Unverified)' : '') }}</option>@endif
@endforeach
@if(empty($work['vendor_other_id']) || $work['vendor_choice'] === 'other')<option value="other" @selected($work['vendor_choice'] === 'other')>OTHER</option>@endif
</select></label>
@if($procurement)
@foreach($suppliers as $supplier)@if($work['vendor_choice'] === 'supplier:'.$supplier->id && $supplier->state === 'unverified')<p class="app-field-help">This supplier needs verification before an order can be recorded. <a href="/catalog/suppliers/{{ $supplier->id }}/edit">Review supplier</a></p>@endif @endforeach
@endif
<fieldset id="purchase-vendor-other" data-other-fields="vendor" @if(!\App\Support\PurchaseRequestBasics::isOtherVendor($work)) hidden @endif><legend>Other vendor or source</legend>
@foreach(['name'=>'Name','website'=>'Website','contact'=>'Contact','email'=>'Email','phone'=>'Phone'] as $key=>$label)
<x-app.field :label="$label" :for="'purchase-vendor-'.$key" :name="'vendor_'.$key"><input id="purchase-vendor-{{ $key }}" name="vendor_{{ $key }}" type="{{ $key === 'email' ? 'email' : ($key === 'phone' ? 'tel' : 'text') }}" @if($key === 'name') required @else data-optional @endif maxlength="255" value="{{ $work['vendor_'.$key] }}" @readonly(!$editable)></x-app.field>
@endforeach
</fieldset>
</div>
<div>
<label for="purchase-delivery">Deliver to<select id="purchase-delivery" name="delivery_choice" required data-other-choice="delivery" aria-controls="purchase-delivery-other" @disabled(!$editable)>
<option value="">Choose a delivery destination</option>
<optgroup label="Storage locations">@foreach($locations as $location)@if($location->state === 'active' || $work['delivery_choice'] === 'location:'.$location->id)<option value="location:{{ $location->id }}" @selected($work['delivery_choice'] === 'location:'.$location->id)>{{ $location->name }}</option>@endif @endforeach</optgroup>
<optgroup label="Current users">@foreach($deliveryUsers as $user)@if($user->enabled || $work['delivery_choice'] === 'user:'.$user->id)<option value="user:{{ $user->id }}" @selected($work['delivery_choice'] === 'user:'.$user->id)>{{ $user->displayName() }}</option>@endif @endforeach</optgroup>
<option value="other" @selected($work['delivery_choice'] === 'other')>OTHER</option>
</select></label>
<fieldset id="purchase-delivery-other" data-other-fields="delivery" @if($work['delivery_choice'] !== 'other') hidden @endif><legend>Other delivery address</legend>
<x-app.field label="Name" for="purchase-delivery-name" name="delivery_name"><input id="purchase-delivery-name" name="delivery_name" required maxlength="255" value="{{ $work['delivery_name'] }}" @readonly(!$editable)></x-app.field>
<x-app.field label="Mailing address" for="purchase-delivery-address" name="delivery_address"><textarea id="purchase-delivery-address" name="delivery_address" required rows="4" maxlength="5000" autocomplete="street-address" placeholder="Provide the delivery address in a format appropriate to its location." @readonly(!$editable)>{{ $work['delivery_address'] }}</textarea></x-app.field>
@foreach(['email'=>'Email','phone'=>'Phone'] as $key=>$label)
<x-app.field :label="$label" :for="'purchase-delivery-'.$key" :name="'delivery_'.$key"><input id="purchase-delivery-{{ $key }}" name="delivery_{{ $key }}" type="{{ $key === 'email' ? 'email' : 'tel' }}" data-optional maxlength="255" value="{{ $work['delivery_'.$key] }}" @readonly(!$editable)></x-app.field>
@endforeach
</fieldset>
</div>
</div>
</fieldset>
</x-workflow.tab-panel>
<x-workflow.tab-panel class="app-panel-stack" id="purchase-panel-material" labelledby="purchase-tab-material" :active="$tab === 'material'">
<x-app.table-region label="Requested material" :nowrap="true" :quantity-fields="true" class="purchase-material">
<x-slot:header><x-workflow.heading><h2>Requested material</h2><button type="submit" class="primary" name="action" value="next" data-material-next>Next &gt;</button></x-workflow.heading></x-slot:header>
<table data-request-material><colgroup><col class="table-action-column"><col><col class="purchase-material-value-column"><col class="purchase-material-value-column"><col class="purchase-material-value-column"></colgroup><thead><tr><th class="table-action-column"><span class="sr-only">Remove</span></th><th>Item</th><th class="table-heading-center">Qty</th><th class="table-heading-center">Est. unit price</th><th class="table-numeric">Est. total</th></tr></thead><tbody>
@php($group = null)
@foreach($items as $item)
@php($nextGroup = $item['category'].' : '.$item['collection'])
@if($group !== $nextGroup)@php($group = $nextGroup)<tr class="group-row"><th colspan="5" scope="rowgroup">{{ $group }}</th></tr>@endif
<tr data-item-row data-material-line data-item-id="{{ $item['id'] }}" data-name="{{ $work['material_names'][$item['id']] ?? $item['name'] }}" data-group="{{ $group }}"><td><button type="button" data-material-remove name="remove" value="{{ $item['id'] }}" class="material-remove" aria-label="Remove {{ $item['name'] }}" @disabled(!$editable)>✕</button></td><td class="app-grouped-item">{{ $work['material_names'][$item['id']] ?? $item['name'] }}</td><td class="table-numeric"><input name="lines[{{ $item['id'] }}]" value="{{ old('lines.'.$item['id'], $work['lines'][$item['id']] ?? '') }}" inputmode="numeric" aria-label="Requested {{ $item['name'] }}" data-quantity @readonly(!$editable)></td><td class="table-numeric"><input name="estimates[{{ $item['id'] }}]" value="{{ $work['estimates'][$item['id']] ?? '' }}" inputmode="decimal" aria-label="Estimated unit price {{ $item['name'] }}" data-estimated-unit @readonly(!$editable)></td><td class="table-numeric" data-material-total>—</td></tr>
@endforeach
@if($work['other_lines'])<tr class="group-row"><th colspan="5" scope="rowgroup">Other items</th></tr>@endif
@foreach($work['other_lines'] as $key=>$other)
<tr data-material-line><td><button type="button" data-material-remove name="remove_other" value="{{ $key }}" class="material-remove" aria-label="Remove Other item {{ $other['description'] ?? '' }}" @disabled(!$editable)>✕</button></td><td class="app-grouped-item"><input name="other_lines[{{ $key }}][description]" value="{{ $other['description'] ?? '' }}" maxlength="500" aria-label="Other item description" data-other-description @readonly(!$editable)></td><td class="table-numeric"><input name="other_lines[{{ $key }}][quantity]" value="{{ $other['quantity'] ?? '' }}" inputmode="numeric" aria-label="Other item quantity" data-quantity @readonly(!$editable)></td><td class="table-numeric"><input name="other_lines[{{ $key }}][unit]" value="{{ $other['unit'] ?? '' }}" inputmode="decimal" aria-label="Other item estimated unit price" data-estimated-unit @readonly(!$editable)></td><td class="table-numeric" data-material-total>—</td></tr>
@endforeach
@if(!$items && !$work['other_lines'])<tr><td colspan="5">No material selected.</td></tr>@endif
</tbody><tfoot><tr><th colspan="4" scope="row" class="table-numeric" data-material-grand-total-label>Estimated merchandise total</th><td class="table-numeric" data-material-grand-total>—</td></tr></tfoot></table>
</x-app.table-region>
@if($editable)
<x-app.table-region label="Catalog search results" :nowrap="true" :quantity-fields="true" class="purchase-material">
<x-slot:header><x-workflow.heading><h2>Add material</h2><button type="button" name="action" value="add_other" data-add-other>Add Other item</button></x-workflow.heading>
<x-app.inventory-search :definition="$searchDefinition" :endpoint="'/purchase/work/'.$token.'/search'" instance="purchase-material-search" :script="false" />
<p class="warning" role="status" data-purchase-search-feedback hidden></p></x-slot:header>
<table><colgroup><col class="table-action-column"><col><col class="table-quantity-column"><col class="table-quantity-column"></colgroup><thead><tr><th><span class="sr-only">Select</span></th><th>Item</th><th class="table-heading-center">Est. unit price</th><th class="table-heading-center">Request</th></tr></thead><tbody data-purchase-search-results><tr><td colspan="4">Use Search or Show all to find items.</td></tr></tbody></table>
<x-slot:footer><div class="app-table-panel-toolbar app-material-add-actions"><div class="app-actions"><button name="action" value="add_selected" type="button" class="primary" data-add-selected disabled>Add selected</button></div></div></x-slot:footer>
</x-app.table-region>
@endif
</x-workflow.tab-panel>
<x-workflow.tab-panel class="app-panel-stack" id="purchase-panel-review" labelledby="purchase-tab-review" :active="$tab === 'review'">
<div class="app-content"><x-workflow.heading><h2>Review purchase request</h2><button class="primary" type="submit" name="action" value="submit" data-submit-request @disabled(!$canSubmit || count($reviewErrors))>Submit</button></x-workflow.heading>
<dl class="purchase-review"><dt>Title</dt><dd data-review="title">{{ $work['title'] ?: '—' }}</dd><dt>Requestor</dt><dd>{{ $requesterName }}</dd><dt>Vendor or suggested source</dt><dd data-review-choice="vendor"></dd><dt>Deliver to</dt><dd data-review-choice="delivery"></dd></dl></div>
<x-app.table-region label="Requested material" :nowrap="true" :quantity-fields="true" class="purchase-material"><x-slot:header><h2>Requested material</h2></x-slot:header><table><thead><tr><th>Item</th><th class="table-heading-center table-quantity-column">Requested</th></tr></thead><tbody data-review-items></tbody></table></x-app.table-region>
</x-workflow.tab-panel>
</form>
@if($work['id'])
<x-app.collapsible-panel title="Permanent notes"><form method="post" action="/purchase/work/{{ $token }}/note">@csrf<label>Note<textarea name="note" required maxlength="5000" rows="3"></textarea></label><button type="submit">Add note</button></form>@foreach($notes as $note)<article><p><strong>{{ $note->actor_name }}</strong> · {{ \App\Support\DisplayDates::date($note->occurred_at) }}</p><p class="plain-text">{{ $note->body }}</p></article>@endforeach</x-app.collapsible-panel>
@if(!in_array($row->status, ['Received','Cancelled']) && ($editable || $procurement))
<x-app.collapsible-panel title="Other actions"><form method="post" action="/purchase/work/{{ $token }}/transition" data-confirm-transition>@csrf<input type="hidden" name="revision" value="{{ $work['revision'] }}">@if($procurement && in_array($row->status,['Request','Ordered','Shipped']))<button name="action" value="backward">Return to {{ ['Request'=>'Draft','Ordered'=>'Request','Shipped'=>'Ordered'][$row->status] }}</button>@endif <button name="action" value="cancel">Cancel purchase</button></form></x-app.collapsible-panel>
@endif
@endif

</x-app.panel-stack>
@endsection
