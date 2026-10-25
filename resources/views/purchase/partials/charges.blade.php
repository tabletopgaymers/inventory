<p>{{ $mode==='receipt' ? 'Receipt charges' : 'Order charges' }} — USD. Charges are allocated across catalog and Other items. Open review for current calculated allocations.</p>
<div class="purchase-fields">
@foreach(['other_fees'=>'Other fees','discount'=>'Discount','tax'=>'Tax','shipping'=>'Shipping'] as $field=>$label)<label>{{ $label }}<input id="purchase-{{ $field }}" name="{{ $field }}" value="{{ $values[$field] ?? '0' }}" inputmode="decimal" @readonly($readonly)></label>@endforeach
<label>Shipping allocation<select id="purchase-shipping_method" name="shipping_method" @disabled($readonly)><option value="line_total" @selected(($values['shipping_method'] ?? 'line_total')==='line_total')>By line total</option><option value="quantity" @selected(($values['shipping_method'] ?? '')==='quantity')>By quantity</option></select></label>
</div>
