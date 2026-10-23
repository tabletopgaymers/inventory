@extends('layout')
@section('title', 'Purchase request')
@section('title-status')<span class="status-badge">{{ $row->status ?? 'New' }}</span>@endsection
@section('page-actions')
<button form="purchase-request-form" type="submit" name="action" value="save" class="primary app-save-progress" data-save-shortcut @disabled(!$editable)>Save progress</button>
@endsection
@section('content-sections')
<link rel="stylesheet" href="/purchase.css?v={{ substr(hash_file('sha256', public_path('purchase.css')),0,12) }}">
<style>
.purchase-tabs,.purchase-heading{display:flex;align-items:center;justify-content:space-between;gap:.5rem;flex-wrap:wrap}.purchase-tabs{justify-content:flex-start}.purchase-tabs [aria-selected="true"]{background:#e5dfef;border-color:var(--app-primary)}.purchase-heading h2{margin:0}.purchase-heading{margin-bottom:var(--app-density-field-y)}.purchase-material th,.purchase-material td{white-space:nowrap}.purchase-material .quantity{width:5%;text-align:right}.purchase-material input{width:10ch;text-align:right;margin:0}.purchase-material [data-field-qualifier]{display:none}.purchase-material .delete{width:1%}.purchase-remove{color:#991b1b;background:transparent;border:0;padding:.125rem .25rem;font-weight:bold}.purchase-invalid>td{background:#fff1f2;color:#991b1b}.purchase-add{display:flex;flex-wrap:wrap;align-items:end;gap:1rem}.purchase-add label{margin:0}.purchase-review dd{white-space:pre-wrap}#purchase-leave{max-width:32rem}#purchase-leave::backdrop{background:#0008}
</style>
<form id="purchase-request-form" action="/purchase/work/{{ $token }}" method="post" novalidate data-tab="{{ $tab }}" data-editable="{{ $editable ? 'true' : 'false' }}" data-can-submit="{{ $canSubmit ? 'true' : 'false' }}">
@csrf
<input type="hidden" name="revision" value="{{ $work['revision'] }}">
<input type="hidden" name="tab" value="{{ $tab }}" data-current-tab>
<div class="app-content purchase-tab-panel"><nav class="purchase-tabs" role="tablist" aria-label="Purchase request steps">
@foreach(['basic'=>'Basic info','material'=>'Material','review'=>'Review'] as $key=>$label)
<button type="button" role="tab" id="purchase-tab-{{ $key }}" aria-controls="purchase-panel-{{ $key }}" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" data-purchase-tab="{{ $key }}">{{ $label }}</button>
@endforeach
@if($work['id'])
@foreach(['order'=>'Order details','invoice'=>'Order invoice','charges'=>'Order charges','order-review'=>'Order review','receive'=>'Receive','confirmation'=>'Confirmation'] as $key=>$label)
@if(!in_array($key,['receive','confirmation']) || in_array($row->status,['Ordered','Shipped','Received']))<a class="button" href="/purchase/work/{{ $token }}?tab={{ $key }}">{{ $label }}</a>@endif
@endforeach
@endif
</nav></div>
<div class="error" role="alert" data-purchase-errors @if($tab !== 'review' || !$reviewErrors) hidden @endif>
@foreach($reviewErrors as $messages)@foreach((array)$messages as $message)<p>{{ $message }}</p>@endforeach @endforeach
</div>
<section class="app-content" role="tabpanel" id="purchase-panel-basic" aria-labelledby="purchase-tab-basic" @if($tab !== 'basic') hidden @endif>
<div class="purchase-heading"><h2>Basic info</h2><button type="submit" class="primary" name="action" value="next" @disabled(!$editable)>Next &gt;</button></div>
<fieldset class="app-entry-fieldset">
<label for="purchase-title">Title<input id="purchase-title" name="title" required maxlength="255" value="{{ old('title',$work['title']) }}" @readonly(!$editable)></label>
<label for="purchase-supplier">Suggested supplier<input id="purchase-supplier" name="suggested_merchant" data-optional maxlength="255" value="{{ old('suggested_merchant',$work['suggested_merchant']) }}" @readonly(!$editable)></label>
<label for="purchase-details">Request details<textarea id="purchase-details" name="details" data-optional maxlength="5000" rows="5" @readonly(!$editable)>{{ old('details',$work['details']) }}</textarea></label>
</fieldset>
</section>
<section role="tabpanel" id="purchase-panel-material" aria-labelledby="purchase-tab-material" @if($tab !== 'material') hidden @endif>
<x-app.table-region label="Requested material" class="purchase-material">
<div class="purchase-heading"><h2>Requested material</h2><button type="submit" class="primary" name="action" value="next" data-material-next>Next &gt;</button></div>
<table><thead><tr><th class="delete"><span class="sr-only">Remove</span></th><th>Item</th><th class="quantity">Requested</th></tr></thead><tbody>
@php($group = null)
@foreach($items as $item)
@php($nextGroup = $item['category'].' : '.$item['collection'])
@if($group !== $nextGroup)@php($group = $nextGroup)<tr class="group-row"><td></td><th colspan="2" scope="rowgroup">{{ $group }}</th></tr>@endif
<tr data-item-row data-item-id="{{ $item['id'] }}" data-name="{{ $item['name'] }}" data-group="{{ $group }}"><td><button type="submit" name="remove" value="{{ $item['id'] }}" class="purchase-remove" aria-label="Remove {{ $item['name'] }}" @disabled(!$editable)>✕</button></td><td>{{ $item['name'] }}</td><td class="quantity"><input name="lines[{{ $item['id'] }}]" value="{{ old('lines.'.$item['id'], $work['lines'][$item['id']] ?? '') }}" inputmode="numeric" aria-label="Requested {{ $item['name'] }}" data-quantity @readonly(!$editable)></td></tr>
@endforeach
@if(!$items)<tr><td colspan="3">No catalog items selected. You can describe uncataloged material in Request details.</td></tr>@endif
</tbody></table>
</x-app.table-region>
@if($editable)<div class="app-content"><h2>Add material</h2><div class="purchase-add"><label>Search catalog<input type="search" data-item-search autocomplete="off"></label><label>Item<select name="item_id" data-item-select><option value="">Choose an item</option>
@foreach($collections as $collection)<optgroup label="{{ $collection->category_name }} : {{ $collection->name }}">@foreach($catalogItems->where('collection_id',$collection->id)->where('state','active') as $catalogItem)@if(!array_key_exists($catalogItem->id,$work['lines']))<option value="{{ $catalogItem->id }}">{{ $catalogItem->name }}</option>@endif @endforeach</optgroup>@endforeach
</select></label><button name="action" value="add" type="submit">Add selected</button></div></div>@endif
</section>
<section role="tabpanel" id="purchase-panel-review" aria-labelledby="purchase-tab-review" @if($tab !== 'review') hidden @endif>
<div class="app-content"><div class="purchase-heading"><h2>Review purchase request</h2><button class="primary" type="submit" name="action" value="submit" data-submit-request @disabled(!$canSubmit || count($reviewErrors))>Submit</button></div>
<dl class="purchase-review"><dt>Title</dt><dd data-review="title">{{ $work['title'] ?: '—' }}</dd><dt>Requester</dt><dd>{{ $requesterName }}</dd><dt>Suggested supplier</dt><dd data-review="suggested_merchant">{{ $work['suggested_merchant'] ?: '—' }}</dd><dt>Request details</dt><dd data-review="details">{{ $work['details'] ?: '—' }}</dd></dl></div>
<x-app.table-region label="Requested material" class="purchase-material"><h2>Requested material</h2><table><thead><tr><th>Item</th><th class="quantity">Requested</th></tr></thead><tbody data-review-items></tbody></table></x-app.table-region>
</section>
</form>
@if($work['id'])
<div class="app-content"><details><summary>Permanent notes</summary><form method="post" action="/purchase/work/{{ $token }}/note">@csrf<label>Note<textarea name="note" required maxlength="5000" rows="3"></textarea></label><button type="submit">Add note</button></form>@foreach($notes as $note)<article><p><strong>{{ $note->actor_name }}</strong> · {{ \App\Support\DisplayDates::date($note->occurred_at) }}</p><p style="white-space:pre-wrap">{{ $note->body }}</p></article>@endforeach</details></div>
@if(!in_array($row->status, ['Received','Cancelled']) && ($editable || $procurement))
<div class="app-content"><details><summary>Other actions</summary><form method="post" action="/purchase/work/{{ $token }}/transition" data-confirm-transition>@csrf<input type="hidden" name="revision" value="{{ $work['revision'] }}">@if($procurement && in_array($row->status,['Request','Ordered','Shipped']))<button name="action" value="backward">Return to {{ ['Request'=>'Draft','Ordered'=>'Request','Shipped'=>'Ordered'][$row->status] }}</button>@endif <button name="action" value="cancel">Cancel purchase</button></form></details></div>
@endif
@endif
<dialog id="purchase-leave"><h2>Leave without saving?</h2><p>Your unsaved purchase changes will be lost.</p><div class="purchase-heading"><button type="button" data-stay>Keep editing</button><button type="button" data-leave>Leave without saving</button></div></dialog>
<script src="/purchase-request.js?v={{ substr(hash_file('sha256', public_path('purchase-request.js')),0,12) }}" defer></script>
@endsection
