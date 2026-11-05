<x-app.table-region data-invoice-derived data-allocation-preview label="Unit cost calculations" class="app-list-table-panel purchase-table" :nowrap="true" :quantity-fields="true" numeric="after-first">
<x-slot:header><h2>Unit cost calculations</h2>@if($showRecalculate ?? true)<p>Save progress to recalculate.</p>@endif</x-slot:header>
<table><thead><tr><th>Item</th><th class="table-numeric">Units</th>@foreach(['cost'=>'Cost','fee'=>'Fee','other'=>'Shared','discount'=>'Discount','tax'=>'Tax','shipping'=>'Shipping','acquisition'=>'Acquisition'] as $key=>$label)<th class="table-numeric">{{ $label }}</th>@endforeach<th class="table-numeric">Unit cost</th></tr></thead><tbody>
@php($group=null)
@foreach($items as $item)
@php($id=$item['id'])
@if(isset($preview['lines'][$id]))
@php($line=$preview['lines'][$id])
@if($group!==$item['group'])@php($group=$item['group'])<tr class="group-row"><th colspan="10" scope="rowgroup">{{ $group }}</th></tr>@endif
<tr><td class="app-grouped-item">{{ $item['name'] }}</td><td>{{ number_format($line['quantity']) }}</td>@foreach(['cost','fee','other','discount','tax','shipping','acquisition'] as $field)<td>{{ \App\Support\PurchaseCosting::dollars($line[$field]) }}</td>@endforeach<td>${{ \Brick\Math\BigDecimal::of($line['acquisition_unit'])->toScale(3, \Brick\Math\RoundingMode::HalfUp) }}</td></tr>
@endif
@endforeach
</tbody><tfoot><tr><th colspan="9">Total USD</th><td>{{ \App\Support\PurchaseCosting::dollars($preview['total']) }}</td></tr></tfoot></table></x-app.table-region>
