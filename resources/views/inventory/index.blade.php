@extends('layout')
@section('title', 'Item & History')
@section('content')
<p>View an item’s storage balances and history. Managers and Admins can correct storage inventory.</p>
<ul>
@forelse($items as $item)<li><a href="/inventory/items/{{ $item->id }}">{{ $item->name }} — {{ $item->sku }}</a></li>
@empty<li>No items are available.</li>
@endforelse</ul>

@endsection
