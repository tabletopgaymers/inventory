@extends('layout')
@section('title', 'Review Inventory — '.$item->name)
@section('content')
<p>Review the proposed History entries. Only Save records the correction.</p>

@php($entries = collect($changes)->map(fn($change) => (object) ($change + ['unit_cost' => $item->unit_cost])))

@include('inventory.history', ['entries' => $entries, 'preview' => true])

@foreach($changes as $change)
@if($change['rationale'] !== '')<p>{{ $change['description'] }} — Rationale: {{ $change['rationale'] }}</p>
@endif
@endforeach
<form method="post" action="/inventory/items/{{ $item->id }}/review/{{ $token }}" data-review-warning>@csrf<input type="hidden" name="_deliberate" value="1">
<div class="actions"><a class="discard" data-discard href="/inventory/items/{{ $item->id }}">Cancel</a><a href="/inventory/items/{{ $item->id }}/edit?operation={{ $token }}">Edit</a><button class="primary">Save</button></div></form>

@endsection
