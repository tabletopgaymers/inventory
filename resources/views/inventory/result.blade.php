@extends('layout')
@section('title', 'Inventory saved')
@section('content')
<p>{{ $group->item_name }} — entries recorded in History.</p>

@php($history = $entries->map(fn($entry) => (object) ((array)$entry + ['posted_at' => $group->posted_at])))

@include('inventory.history', ['entries' => $history, 'preview' => false])
<div class="actions"><a class="button primary" href="/inventory/items/{{ $group->item_id }}">OK</a></div>

@endsection
