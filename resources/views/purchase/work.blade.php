@extends('layout')
@php($finished = in_array($row->status ?? 'New', ['Received','Cancelled'], true))
@php($stepLabels = config('purchase-workflow.tabs'))
@php($panelLabels = config('purchase-workflow.panels'))
@php($buttonLabels = config('purchase-workflow.buttons'))
@section('title', 'Purchase request')
@section('subtitle'){{ $work['title'] }}@endsection
@section('title-status')<x-app.status-badge :status="$row->status ?? 'New'" data-purchase-status />@endsection
@section('page-actions')
<x-workflow.save-progress form="purchase-request-form" :disabled="!$editable" :hidden="!$editable || $tab === 'review'" />
@endsection
@section('content-sections')
@vite(['resources/css/purchase.css', 'resources/js/purchase-request.js'])

<x-app.panel-stack>
<form class="app-panel-stack" id="purchase-request-form" action="/purchase/work/{{ $token }}" method="post" novalidate data-tab="{{ $tab }}" data-editable="{{ $editable ? 'true' : 'false' }}" data-can-submit="{{ $canSubmit ? 'true' : 'false' }}" data-workspace-dirty="{{ !empty($work['material_dirty']) ? 'true' : 'false' }}">
@csrf
<input type="hidden" name="purchase_form" value="request">
<input type="hidden" name="revision" value="{{ $work['revision'] }}">
<input type="hidden" name="tab" value="{{ $tab }}" data-current-tab>
<div class="app-content" data-progress-feedback hidden></div>
<x-workflow.tabs prefix="purchase" label="Purchase request steps" :active="$tab" :tabs="array_intersect_key($stepLabels, array_flip(['basic','material','review']))">
<nav class="workflow-tabs" aria-label="Purchase fulfillment steps" data-fulfillment-links @if(!$work['id']) hidden @endif>
@foreach(array_diff_key($stepLabels, array_flip(['basic','material','review'])) as $key=>$label)
@if($key!=='receive' || in_array($row?->status,['Ordered','Shipped','Received']))<x-app.action  href="/purchase/work/{{ $token }}?tab={{ $key }}">{{ $label }}</x-app.action>@endif
@endforeach
</nav>
</x-workflow.tabs>
<div class="error" role="alert" data-purchase-errors @if(!$editable || $tab !== 'review' || !$reviewErrors) hidden @endif>
@foreach($reviewErrors as $messages)@foreach((array)$messages as $message)<p>{{ $message }}</p>@endforeach @endforeach
</div>
<x-workflow.tab-panel class="app-content" id="purchase-panel-basic" labelledby="purchase-tab-basic" :active="$tab === 'basic'">
<x-workflow.heading><h2>{{ $panelLabels['basic'] }}</h2><x-app.action variant="primary" type="submit"  name="action" value="next" :disabled="!$editable" :hidden="$finished">{{ $buttonLabels['basic'] }}</x-app.action></x-workflow.heading>
<fieldset class="app-entry-fieldset">
<div class="app-columns app-columns-fields">
<label for="purchase-title">Title<input id="purchase-title" name="title" required maxlength="255" value="{{ old('title',$work['title']) }}" @readonly(!$editable)></label>
<label for="purchase-requestor">Requestor<input id="purchase-requestor" value="{{ $requesterName }} ({{ $requesterId }})" readonly></label>
</div>
<div class="app-columns app-columns-fields">
<div>
<label for="purchase-vendor">Supplier (existing or suggested)<select id="purchase-vendor" name="vendor_choice" required data-other-choice="vendor" aria-controls="purchase-vendor-other" @disabled(!$editable)>
<option value="">Choose a supplier</option>
@if(str_starts_with($work['vendor_choice'], 'supplier:') && !$suppliers->firstWhere('id',(int)substr($work['vendor_choice'],9)))<option value="{{ $work['vendor_choice'] }}" selected data-other="{{ !empty($work['vendor_other_id']) ? 'true' : 'false' }}">{{ $work['vendor_name'] ?: ($submittedLabels['vendor'] ?? 'Submitted supplier') }}</option>@endif
@foreach($suppliers->sortBy(fn ($supplier) => (string) ($work['vendor_other_id'] ?? '') === (string) $supplier->id ? 1 : 0) as $supplier)
@if(in_array($supplier->state, ['active','unverified']) || $work['vendor_choice'] === 'supplier:'.$supplier->id)<option value="supplier:{{ $supplier->id }}" @if((string)($work['vendor_other_id'] ?? '') === (string)$supplier->id) data-other="true" @endif @selected($work['vendor_choice'] === 'supplier:'.$supplier->id)>{{ (string)($work['vendor_other_id'] ?? '') === (string)$supplier->id ? 'OTHER: '.$supplier->name : $supplier->name.($supplier->state === 'unverified' ? ' (Unverified)' : '') }}</option>@endif
@endforeach
@if(empty($work['vendor_other_id']) || $work['vendor_choice'] === 'other')<option value="other" @selected($work['vendor_choice'] === 'other')>OTHER</option>@endif
</select></label>
@if($procurement)
@foreach($suppliers as $supplier)@if($work['vendor_choice'] === 'supplier:'.$supplier->id && $supplier->state === 'unverified')<p class="app-field-help">This supplier needs verification before an order can be recorded. <a href="/catalog/suppliers/{{ $supplier->id }}/edit">Review supplier</a></p>@endif @endforeach
@endif
<fieldset id="purchase-vendor-other" data-other-fields="vendor" @if(!\App\Support\PurchaseRequestBasics::isOtherVendor($work)) hidden @endif><legend>Other supplier</legend>
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
<x-slot:header><x-workflow.heading><h2>{{ $panelLabels['material'] }}</h2><x-app.action variant="primary" type="submit"  name="action" value="next" data-material-next :disabled="!$editable" :hidden="$finished">{{ $buttonLabels['material'] }}</x-app.action></x-workflow.heading></x-slot:header>
<table data-request-material><colgroup><col class="table-action-column"><col><col class="purchase-material-value-column"><col class="purchase-material-value-column"><col class="purchase-material-value-column"></colgroup><thead><tr><th class="table-action-column"><span class="sr-only">Remove</span></th><th>Item</th><th class="table-heading-center">Qty</th><th class="table-heading-center">Est. unit price</th><th class="table-numeric">Est. total</th></tr></thead><tbody>
@php($group = null)
@foreach($items as $item)
@php($nextGroup = $item['category'].' : '.$item['collection'])
@if($group !== $nextGroup)@php($group = $nextGroup)<tr class="group-row"><th colspan="5" scope="rowgroup">{{ $group }}</th></tr>@endif
<tr data-item-row data-material-line data-item-id="{{ $item['id'] }}" data-name="{{ $work['material_names'][$item['id']] ?? $item['name'] }}" data-group="{{ $group }}"><td><x-app.action control="delete" type="button" data-material-remove name="remove" value="{{ $item['id'] }}" class="material-remove" aria-label="Remove {{ $item['name'] }}" :disabled="!$editable">✕</x-app.action></td><td class="app-grouped-item">{{ $work['material_names'][$item['id']] ?? $item['name'] }}</td><td class="table-numeric"><input name="lines[{{ $item['id'] }}]" value="{{ old('lines.'.$item['id'], $work['lines'][$item['id']] ?? '') }}" inputmode="numeric" aria-label="Requested {{ $item['name'] }}" data-quantity @readonly(!$editable)></td><td class="table-numeric"><input name="estimates[{{ $item['id'] }}]" value="{{ $work['estimates'][$item['id']] ?? '' }}" inputmode="decimal" aria-label="Estimated unit price {{ $item['name'] }}" data-estimated-unit @readonly(!$editable)></td><td class="table-numeric" data-material-total>—</td></tr>
@endforeach
@if($work['other_lines'])<tr class="group-row"><th colspan="5" scope="rowgroup">Other items</th></tr>@endif
@foreach($work['other_lines'] as $key=>$other)
<tr data-material-line><td><x-app.action control="delete" type="button" data-material-remove name="remove_other" value="{{ $key }}" class="material-remove" aria-label="Remove Other item {{ $other['description'] ?? '' }}" :disabled="!$editable">✕</x-app.action></td><td class="app-grouped-item"><input name="other_lines[{{ $key }}][description]" value="{{ $other['description'] ?? '' }}" maxlength="500" aria-label="Other item description" data-other-description @readonly(!$editable)></td><td class="table-numeric"><input name="other_lines[{{ $key }}][quantity]" value="{{ $other['quantity'] ?? '' }}" inputmode="numeric" aria-label="Other item quantity" data-quantity @readonly(!$editable)></td><td class="table-numeric"><input name="other_lines[{{ $key }}][unit]" value="{{ $other['unit'] ?? '' }}" inputmode="decimal" aria-label="Other item estimated unit price" data-estimated-unit @readonly(!$editable)></td><td class="table-numeric" data-material-total>—</td></tr>
@endforeach
@if(!$items && !$work['other_lines'])<tr><td colspan="5">No material selected.</td></tr>@endif
</tbody><tfoot><tr><th colspan="4" scope="row" class="table-numeric" data-material-grand-total-label>Estimated merchandise total</th><td class="table-numeric" data-material-grand-total>—</td></tr></tfoot></table>
</x-app.table-region>
@if($editable)
@include('purchase.partials.catalog-picker', ['destinationLocation' => str_starts_with($work['delivery_choice'] ?? '', 'location:') ? $locations->firstWhere('id', (int) substr($work['delivery_choice'], 9)) : null])
@endif
</x-workflow.tab-panel>
<x-workflow.tab-panel class="app-panel-stack" id="purchase-panel-review" labelledby="purchase-tab-review" :active="$tab === 'review'">
<div class="app-content"><x-workflow.heading><h2>{{ $panelLabels['review'] }}</h2><x-app.action variant="primary"  type="submit" name="action" value="submit" data-submit-request :disabled="!$canSubmit || count($reviewErrors)" :hidden="$finished">{{ $buttonLabels['review'] }}</x-app.action></x-workflow.heading>
<div class="app-columns app-columns-fields">
<x-app.detail-list variant="content" >
<dt>Title</dt><dd data-review="title">{{ $work['title'] ?: '—' }}</dd>
<dt>Supplier</dt><dd data-review-choice="vendor" data-submitted-label="{{ $submittedLabels['vendor'] ?? '' }}"></dd>
</x-app.detail-list>
<x-app.detail-list variant="content" >
<dt>Requestor</dt><dd>{{ $requesterName }}</dd>
<dt>Deliver to</dt><dd data-review-choice="delivery" data-submitted-label="{{ $submittedLabels['delivery'] ?? '' }}"></dd>
</x-app.detail-list>
</div></div>
<x-app.table-region label="Requested material" :nowrap="true" :quantity-fields="true" class="purchase-material"><x-slot:header><h2>Requested material</h2></x-slot:header><table><thead><tr><th>Item</th><th class="table-heading-center table-quantity-column">Requested</th><th class="table-numeric purchase-material-value-column">Est. unit price</th><th class="table-numeric purchase-material-value-column">Est. total</th></tr></thead><tbody data-review-items></tbody></table></x-app.table-region>
</x-workflow.tab-panel>
</form>
@if($work['id'])
<x-app.collapsible-panel title="Permanent notes"><form method="post" action="/purchase/work/{{ $token }}/note">@csrf<label>Note<textarea name="note" required maxlength="5000" rows="3"></textarea></label><x-app.action type="submit">Add note</x-app.action></form>@foreach($notes as $note)<article><p><strong>{{ $note->actor_name }}</strong> · {{ \App\Support\DisplayDates::date($note->occurred_at) }}</p><p class="plain-text">{{ $note->body }}</p></article>@endforeach</x-app.collapsible-panel>
@if(!in_array($row->status, ['Received','Cancelled']) && ($editable || $procurement))
<x-app.collapsible-panel title="Other actions"><form class="app-inline-group" method="post" action="/purchase/work/{{ $token }}/transition" data-confirm-transition>@csrf<input type="hidden" name="revision" value="{{ $work['revision'] }}">@if($procurement && in_array($row->status,['Request','Ordered','Shipped']))<x-app.action type="submit" name="action" value="backward">Return to {{ ['Request'=>'Draft','Ordered'=>'Request','Shipped'=>'Ordered'][$row->status] }}</x-app.action>@endif <x-app.action type="submit" name="action" value="cancel">Cancel purchase</x-app.action></form></x-app.collapsible-panel>
@endif
@endif

</x-app.panel-stack>
@endsection
