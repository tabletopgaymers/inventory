@extends('layout')
@section('title', 'Inventory saved')
@section('content-sections')
<div class="app-content app-record-content">
@hasSection('relocation-progress')@yield('relocation-progress')@endif @hasSection('purchase-progress')@yield('purchase-progress')@endif
<p>{{ $group->item_name }} — entries recorded in History.</p>

@php($history = $entries->map(fn($entry) => (object) ((array)$entry + ['posted_at' => $group->posted_at])))

</div>
@include('inventory.history', ['entries' => $history, 'preview' => false])
<div class="app-content app-record-content">
<div class="actions"><a class="button primary" href="/inventory/items/{{ $group->item_id }}">OK</a></div>

</div>
@endsection
