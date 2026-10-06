@extends('layout')
@section('title', $item->name)
@section('content')
<p>{{ $item->sku }} · @if($catalogReady)<a href="/inventory/classification/category/{{ $item->category_id }}">{{ $item->category_name }}</a> · <a href="/inventory/classification/collection/{{ $item->collection_id }}">{{ $item->collection_name }}</a> · <span class="status-badge {{ $state }}">{{ ucfirst($state) }}</span>@else{{ $item->category_name }} · {{ $item->collection_name }}@endif</p>
<p>Unit Cost: {{ \App\Support\StockNumbers::displayCost($item->unit_cost) }}</p>
@if($catalogReady)
@if($canCorrect)<p><a class="button" href="/catalog/items/{{ $item->id }}/edit">Edit item details</a></p>@endif
<dl class="item-metadata"><dt>Variety</dt><dd>{{ $metadata?->variety ?? '—' }}</dd><dt>Purpose</dt><dd>{{ $purpose?->name ?? '—' }}</dd><dt>Programs</dt><dd>{{ $programs->pluck('name')->join(', ') ?: '—' }}</dd><dt>Request bundle</dt><dd>{{ $metadata?->bundle_type ?? '—' }} @if($metadata?->bundle_quantity)· {{ number_format($metadata->bundle_quantity) }} individual units @endif</dd>
@foreach(['irs_fmv'=>'IRS FMV','in_person_ask'=>'In-Person Ask','online_ask'=>'Online Ask'] as $field=>$label)<dt>{{ $label }}</dt><dd>{{ $metadata?->$field === null ? '—' : '$'.\Brick\Math\BigDecimal::of($metadata->$field)->toScale(2, \Brick\Math\RoundingMode::HalfUp) }}</dd>@endforeach<dt>Notes</dt><dd>{{ $metadata?->notes ?? '—' }}</dd></dl>
@endif
<div class="section-heading"><h2>Storage locations</h2>
@if($canCorrect)<a class="button" href="/inventory/items/{{ $item->id }}/edit">Edit Inventory</a>
@endif</div>
<div class="table-wrap"><table><thead><tr><th>Location</th><th class="number">Available</th></tr></thead><tbody>

@foreach($locations as $location)<tr><td>@if($catalogReady)<a href="/inventory/locations/{{ $location->id }}@if(is_string($browse) && \Illuminate\Support\Str::isUuid($browse))?browse={{ $browse }}@endif">{{ $location->name }}</a>@else{{ $location->name }}@endif</td><td class="number">{{ number_format($location->quantity) }}</td></tr>
@endforeach
</tbody><tfoot><tr><th>Total Available</th><td class="number">{{ number_format($locations->sum('quantity')) }}</td></tr></tfoot></table></div>
@if($catalogReady)<h2>Events and pending inventory</h2><p>These read-only balances are excluded from Available. Future transaction links are unavailable.</p><div class="table-wrap"><table><thead><tr><th>Source</th><th class="number">Quantity</th></tr></thead><tbody>@foreach($extraBalances as $balance)<tr><td>{{ $balance['name'] }}</td><td class="number">{{ number_format($balance['quantity']) }}</td></tr>@endforeach</tbody></table></div>@endif
<h2 id="history">History</h2><p>Sort by <a href="?sort=date#history">date</a> or <a href="?sort=description#history">description</a>.</p>

@include('inventory.history', ['entries' => $history, 'preview' => false])
@if($catalogReady && is_string($browse) && \Illuminate\Support\Str::isUuid($browse))<p><a href="/inventory/results/{{ $browse }}">Back to results</a></p>@else<p><a href="/inventory/item-history">Items</a></p>@endif

@endsection
