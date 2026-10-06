@extends('layout')
@section('title', $item->name)
@section('content')
<p>{{ $item->sku }} · {{ $item->category_name }} · {{ $item->collection_name }}</p>
<p>Unit Cost: {{ \App\Support\StockNumbers::displayCost($item->unit_cost) }}</p>
<div class="section-heading"><h2>Storage locations</h2>
@if($canCorrect)<a class="button" href="/inventory/items/{{ $item->id }}/edit">Edit Inventory</a>
@endif</div>
<div class="table-wrap"><table><thead><tr><th>Location</th><th class="number">Available</th></tr></thead><tbody>

@foreach($locations as $location)<tr><td>{{ $location->name }}</td><td class="number">{{ number_format($location->quantity) }}</td></tr>
@endforeach
</tbody><tfoot><tr><th>Total Available</th><td class="number">{{ number_format($locations->sum('quantity')) }}</td></tr></tfoot></table></div>
<h2 id="history">History</h2><p>Sort by <a href="?sort=date#history">date</a> or <a href="?sort=description#history">description</a>.</p>

@include('inventory.history', ['entries' => $history, 'preview' => false])
<p><a href="/inventory/item-history">Items</a></p>

@endsection
