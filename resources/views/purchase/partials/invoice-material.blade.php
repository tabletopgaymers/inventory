<x-app.table-region label="Invoice material" class="app-list-table-panel purchase-table purchase-material" :nowrap="true" :quantity-fields="true" :separators="true" numeric="after-first">
<table><colgroup><col class="table-action-column"><col>@for($column=0;$column<7;$column++)<col class="purchase-material-value-column">@endfor</colgroup>
<thead><tr><th><span class="sr-only">Remove</span></th><th>Item</th><th>{{ $mode==='receipt' ? 'Ordered' : 'Requested' }}</th><th>{{ $mode==='receipt' ? 'Received' : 'Quantity' }}</th><th>Unit ($)</th><th>Cost ($)</th><th>Fee ($)</th><th>Line ($)</th><th><span class="sr-only">Recalculate</span></th></tr></thead><tbody data-invoice-body>
@php($group=null)
@foreach($items as $item)
@php($key=$item['id']) @php($line=$values['lines'][$key])
@if($group!==$item['group'])@php($group=$item['group'])<tr class="group-row"><th colspan="9" scope="rowgroup">{{ $group }}</th></tr>@endif
<tr data-invoice-line data-line-key="{{ $key }}" data-group="{{ $group }}"><td>@if(!$readonly)<button type="button" data-remove-invoice class="material-remove" aria-label="Remove {{ $item['name'] }}">✕</button>@endif</td><td class="app-grouped-item">
<input name="lines[{{ $key }}][description]" value="{{ $item['name'] }}" maxlength="500" data-invoice-description aria-label="Item description" @readonly($readonly || $item['item_id'])>
<input type="hidden" name="lines[{{ $key }}][item_id]" value="{{ $item['item_id'] }}" data-invoice-item>
<input type="hidden" name="lines[{{ $key }}][group_label]" value="{{ $group }}" data-invoice-group>
<input type="hidden" name="lines[{{ $key }}][source_request_key]" value="{{ $line['source_request_key'] ?? '' }}">
@if(!$readonly && !$item['item_id'])@include('purchase.partials.catalog-link')@endif
</td><td class="table-numeric">{{ $mode==='receipt' ? ($ordered[$key] ?? 0) : ($requestMaterial[$line['source_request_key'] ?? '']['quantity'] ?? $requested[$item['item_id']] ?? '—') }}</td>
@foreach(['quantity','unit','cost','fee'] as $field)<td><input id="purchase-line-{{ $key }}-{{ $field }}" name="lines[{{ $key }}][{{ $field }}]" data-invoice-field="{{ $field }}" aria-label="{{ ucfirst($field) }} for {{ $item['name'] }}" inputmode="{{ $field==='quantity' ? 'numeric' : 'decimal' }}" value="{{ $line[$field] ?? '' }}" @readonly($readonly)></td>@endforeach
<td data-line-total class="table-numeric">—</td><td><input type="hidden" name="lines[{{ $key }}][basis]" value="{{ $line['basis'] ?? 'unit' }}" data-line-basis>@if(!$readonly)<button type="button" data-recalculate>Recalculate</button>@endif</td></tr>
@endforeach
@if(!$items)<tr><td colspan="9">No order material selected.</td></tr>@endif
</tbody></table></x-app.table-region>
@if(!$readonly)@include('purchase.partials.catalog-picker',['quantityLabel'=>'Quantity'])@endif

@if(!$readonly)<template data-catalog-link-template>@include('purchase.partials.catalog-link')</template>@endif
