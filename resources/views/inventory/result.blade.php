@extends('layout')
@section('title', 'Inventory saved')
@section('content-sections')
<div class="app-content app-record-content">

<p>{{ $group->item_name }} — entries recorded in History.</p>

@php($history = $entries->map(fn($entry) => (object) ((array)$entry + ['posted_at' => $group->posted_at])))

</div>
@include('inventory.history', ['entries' => $history, 'preview' => false])
<div class="app-content app-record-content">
<div class="actions"><x-app.action variant="primary"  href="/inventory/items/{{ $group->item_id }}">OK</x-app.action></div>

</div>
@endsection
