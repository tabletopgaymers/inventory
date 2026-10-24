@extends('layout')
@section('title', $item->name)
@section('title-status')
@if($catalogReady)<x-app.status-badge :status="ucfirst($state)" />@endif
@endsection
@section('page-actions')
@if($catalogReady && $canCorrect)<a class="button" href="{{ $itemEditUrl }}">Edit</a>@endif
@endsection
@section('content-sections')
<div class="app-content app-record-content">
@hasSection('relocation-progress')@yield('relocation-progress')@endif @hasSection('purchase-progress')@yield('purchase-progress')@endif
@php($historyContext = is_string($browse) && \Illuminate\Support\Str::isUuid($browse) ? ['browse' => $browse] : [])
@if($catalogReady)
<div class="app-catalog-item-columns">
<div><dl class="app-user-details">
<dt>Name</dt><dd>{{ $item->name }}</dd>
<dt>Collection</dt><dd><a href="/catalog/categories/{{ $item->category_id }}">{{ $item->category_name }}</a> : <a href="/catalog/collections/{{ $item->collection_id }}">{{ $item->collection_name }}</a></dd>
<dt>SKU</dt><dd>{{ $item->sku }}</dd>
<dt>Variety</dt><dd>{{ $metadata?->variety ?? '—' }}</dd><dt>Purpose</dt><dd>{{ $purpose?->name ?? '—' }}</dd><dt>Programs</dt><dd>{{ $programs->pluck('name')->join(', ') ?: '—' }}</dd>
</dl></div>
<div><dl class="app-user-details">
<dt>Unit cost</dt><dd>{{ \App\Support\StockNumbers::displayCost($item->unit_cost) }}@if($dailyReady && $canCost) (<a href="{{ $itemBase }}{{ $item->id }}/cost">adjust</a>)@endif</dd>
@foreach(['in_person_ask'=>'In-person ask','online_ask'=>'Online ask','irs_fmv'=>'IRS Fair Market Value'] as $field=>$label)<dt>{{ $label }}</dt><dd>{{ $metadata?->$field === null ? '—' : '$'.\Brick\Math\BigDecimal::of($metadata->$field)->toScale(2, \Brick\Math\RoundingMode::HalfUp) }}</dd>@endforeach
<dt>Bundle type</dt><dd>{{ $metadata?->bundle_type ?? '—' }}</dd><dt>Bundle quantity</dt><dd>{{ $metadata?->bundle_quantity === null ? '—' : number_format($metadata->bundle_quantity) }}</dd>
<dt>Notes</dt><dd class="plain-text">{{ $metadata?->notes ?? '—' }}</dd>
</dl></div>
</div>
@else
<p>{{ $item->sku }} · {{ $item->category_name }} · {{ $item->collection_name }}</p>
<p>Unit cost: {{ \App\Support\StockNumbers::displayCost($item->unit_cost) }}</p>
@endif
</div>
<x-app.table-region label="Item quantities by location" class="app-list-table-panel">
<x-slot:header>
<div class="app-table-panel-toolbar">
<h2 class="app-heading-flush">Locations</h2>
@if($canCorrect)<div class="app-actions"><a class="button" href="/inventory/items/{{ $item->id }}/edit">Edit inventory</a></div>@endif
</div>
</x-slot:header>
<table><thead><tr><th>Location</th><th class="number">Available</th></tr></thead><tbody>

@foreach($locations as $location)<tr><td>@if($catalogReady)<a href="/inventory/locations/{{ $location->id }}@if(is_string($browse) && \Illuminate\Support\Str::isUuid($browse))?browse={{ $browse }}@endif">{{ $location->name }}</a>@else{{ $location->name }}@endif</td><td class="number">{{ number_format($location->quantity) }}</td></tr>
@endforeach
</tbody><tfoot><tr><th>Total available</th><td class="number">{{ number_format($locations->sum('quantity')) }}</td></tr></tfoot></table></x-app.table-region>
@if($catalogReady)
<x-app.table-region label="Events and pending inventory" class="app-list-table-panel">
<x-slot:header>
<h2 class="app-heading-flush">Events and pending inventory</h2>
<p class="app-field-help">These read-only balances are excluded from Available. Purchase and relocation history links open their source requests.</p>
</x-slot:header>
<table><thead><tr><th>Source</th><th class="number">Quantity</th></tr></thead><tbody>@foreach($extraBalances as $balance)<tr><td>{{ $balance['name'] }}</td><td class="number">{{ number_format($balance['quantity']) }}</td></tr>@endforeach</tbody></table>
</x-app.table-region>
@endif
@include('inventory.history', ['entries' => $history, 'preview' => false, 'historyHeader' => true])
@if($catalogReady && is_string($browse) && \Illuminate\Support\Str::isUuid($browse))<p><a href="/inventory/results/{{ $browse }}">Back to results</a></p>@endif
@endsection
