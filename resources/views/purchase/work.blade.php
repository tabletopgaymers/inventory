@extends('layout')
@section('title', 'Purchase request')
@section('title-status')<span class="status-badge">{{ $row->status ?? 'New' }}</span>@endsection
@section('page-actions')
<button form="purchase-request-form" type="submit" name="action" value="save" class="primary app-save-progress" data-save-shortcut @disabled(!$editable)>Save progress</button>
@endsection
@section('content-sections')
<link rel="stylesheet" href="/purchase.css?v={{ substr(hash_file('sha256', public_path('purchase.css')),0,12) }}">
<style>
.purchase-remove{color:#991b1b;background:transparent;border:0;padding:.125rem .25rem;font-weight:bold}.purchase-add{display:flex;flex-wrap:wrap;align-items:end;gap:1rem}.purchase-add label{margin:0}.purchase-review dd{white-space:pre-wrap}
</style>
<form id="purchase-request-form" action="/purchase/work/{{ $token }}" method="post" novalidate data-tab="{{ $tab }}" data-editable="{{ $editable ? 'true' : 'false' }}" data-can-submit="{{ $canSubmit ? 'true' : 'false' }}">
@csrf
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
<label for="purchase-title">Title<input id="purchase-title" name="title" required maxlength="255" value="{{ old('title',$work['title']) }}" @readonly(!$editable)></label>
<label for="purchase-supplier">Suggested supplier<input id="purchase-supplier" name="suggested_merchant" data-optional maxlength="255" value="{{ old('suggested_merchant',$work['suggested_merchant']) }}" @readonly(!$editable)></label>
<label for="purchase-details">Request details<textarea id="purchase-details" name="details" data-optional maxlength="5000" rows="5" @readonly(!$editable)>{{ old('details',$work['details']) }}</textarea></label>
</fieldset>
</x-workflow.tab-panel>
<x-workflow.tab-panel id="purchase-panel-material" labelledby="purchase-tab-material" :active="$tab === 'material'">
<x-app.table-region label="Requested material" :nowrap="true" :quantity-fields="true" class="purchase-material">
<x-slot:header><x-workflow.heading><h2>Requested material</h2><button type="submit" class="primary" name="action" value="next" data-material-next>Next &gt;</button></x-workflow.heading></x-slot:header>
<table><thead><tr><th class="table-action-column"><span class="sr-only">Remove</span></th><th>Item</th><th class="table-heading-center table-quantity-column">Requested</th></tr></thead><tbody>
@php($group = null)
@foreach($items as $item)
@php($nextGroup = $item['category'].' : '.$item['collection'])
@if($group !== $nextGroup)@php($group = $nextGroup)<tr class="group-row"><td></td><th colspan="2" scope="rowgroup">{{ $group }}</th></tr>@endif
<tr data-item-row data-item-id="{{ $item['id'] }}" data-name="{{ $item['name'] }}" data-group="{{ $group }}"><td><button type="submit" name="remove" value="{{ $item['id'] }}" class="purchase-remove" aria-label="Remove {{ $item['name'] }}" @disabled(!$editable)>✕</button></td><td class="app-grouped-item">{{ $item['name'] }}</td><td class="table-numeric table-quantity-column"><input name="lines[{{ $item['id'] }}]" value="{{ old('lines.'.$item['id'], $work['lines'][$item['id']] ?? '') }}" inputmode="numeric" aria-label="Requested {{ $item['name'] }}" data-quantity @readonly(!$editable)></td></tr>
@endforeach
@if(!$items)<tr><td colspan="3">No catalog items selected. You can describe uncataloged material in Request details.</td></tr>@endif
</tbody></table>
</x-app.table-region>
@if($editable)<div class="app-content"><h2>Add material</h2><x-app.inventory-search :definition="$searchDefinition" :endpoint="'/purchase/work/'.$token" instance="purchase-material-search" :script="false" /><div class="purchase-add"><label>Item<select name="item_id" data-item-select><option value="">Choose an item</option>
@foreach($collections as $collection)<optgroup data-collection="{{ $collection->id }}" label="{{ $collection->category_name }} : {{ $collection->name }}">@foreach($catalogItems->where('collection_id',$collection->id)->where('state','active') as $catalogItem)@if(!array_key_exists($catalogItem->id,$work['lines']))<option value="{{ $catalogItem->id }}">{{ $catalogItem->name }}</option>@endif @endforeach</optgroup>@endforeach
</select></label><button name="action" value="add" type="submit">Add selected</button></div></div>@endif
</x-workflow.tab-panel>
<x-workflow.tab-panel id="purchase-panel-review" labelledby="purchase-tab-review" :active="$tab === 'review'">
<div class="app-content"><x-workflow.heading><h2>Review purchase request</h2><button class="primary" type="submit" name="action" value="submit" data-submit-request @disabled(!$canSubmit || count($reviewErrors))>Submit</button></x-workflow.heading>
<dl class="purchase-review"><dt>Title</dt><dd data-review="title">{{ $work['title'] ?: '—' }}</dd><dt>Requester</dt><dd>{{ $requesterName }}</dd><dt>Suggested supplier</dt><dd data-review="suggested_merchant">{{ $work['suggested_merchant'] ?: '—' }}</dd><dt>Request details</dt><dd data-review="details">{{ $work['details'] ?: '—' }}</dd></dl></div>
<x-app.table-region label="Requested material" :nowrap="true" :quantity-fields="true" class="purchase-material"><x-slot:header><h2>Requested material</h2></x-slot:header><table><thead><tr><th>Item</th><th class="table-heading-center table-quantity-column">Requested</th></tr></thead><tbody data-review-items></tbody></table></x-app.table-region>
</x-workflow.tab-panel>
</form>
@if($work['id'])
<div class="app-content"><details><summary>Permanent notes</summary><form method="post" action="/purchase/work/{{ $token }}/note">@csrf<label>Note<textarea name="note" required maxlength="5000" rows="3"></textarea></label><button type="submit">Add note</button></form>@foreach($notes as $note)<article><p><strong>{{ $note->actor_name }}</strong> · {{ \App\Support\DisplayDates::date($note->occurred_at) }}</p><p style="white-space:pre-wrap">{{ $note->body }}</p></article>@endforeach</details></div>
@if(!in_array($row->status, ['Received','Cancelled']) && ($editable || $procurement))
<div class="app-content"><details><summary>Other actions</summary><form method="post" action="/purchase/work/{{ $token }}/transition" data-confirm-transition>@csrf<input type="hidden" name="revision" value="{{ $work['revision'] }}">@if($procurement && in_array($row->status,['Request','Ordered','Shipped']))<button name="action" value="backward">Return to {{ ['Request'=>'Draft','Ordered'=>'Request','Shipped'=>'Ordered'][$row->status] }}</button>@endif <button name="action" value="cancel">Cancel purchase</button></form></details></div>
@endif
@endif
@vite('resources/js/purchase-request.js')
@endsection
