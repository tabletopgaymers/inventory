@extends('layout')
@section('title', $list->name)
@section('page-actions')
@if($canEdit)<x-app.action href="/catalog/lists/{{ $list->id }}/edit">Edit</x-app.action>@endif
<form method="post" action="/catalog/lists/{{ $list->id }}/copy">@csrf<x-app.action type="submit">Copy</x-app.action></form>
@endsection
@section('content-sections')
@vite('resources/js/saved-lists.js')
<section class="app-content">
<h2>List information</h2>
<div class="app-columns app-columns-two">
    <x-app.detail-list>
        <dt>Name</dt><dd>{{ $list->name }}</dd>
        <dt>Location</dt><dd>{{ $location ?? 'No default location' }}</dd>
        <dt>Status</dt><dd><x-app.status-badge :status="$list->status" :label="\App\Models\SavedList::STATUSES[$list->status]" /></dd>
    </x-app.detail-list>
    <x-app.detail-list>
        <dt>Description</dt><dd class="plain-text">{{ $list->description ?: '—' }}</dd>
        <dt>Owner</dt><dd>{{ $list->owner->displayName() }}</dd>
        <dt>Updated</dt><dd>{{ \App\Support\DisplayDates::date($list->updated_at) }}</dd>
    </x-app.detail-list>
</div>
</section>
<x-app.table-region label="List items" :nowrap="true">
<x-slot:header><h2>Items</h2><p>{{ number_format($items->count()) }} {{ $items->count() === 1 ? 'item' : 'items' }}</p></x-slot:header>
<table><colgroup><col><col class="table-quantity-column"></colgroup><thead><tr><th>Item</th><th>SKU</th></tr></thead><tbody>
@php($group = null)
@forelse($items as $item)
@if($group !== $item->collection_id)
@php($group = $item->collection_id)
<tr class="group-row"><th colspan="2">{{ $item->category_name }} : {{ $item->collection_name }}</th></tr>
@endif
<tr><td class="app-grouped-item">{{ $item->name }}</td><td>{{ $item->sku }}</td></tr>
@empty
<tr><td colspan="2">No items in this list.</td></tr>
@endforelse
</tbody></table>
</x-app.table-region>
@if($canEdit)
<x-app.collapsible-panel title="Other actions">
    <form method="post" action="/catalog/lists/{{ $list->id }}/delete" data-list-delete>
        @csrf
        <x-app.action variant="danger" type="submit" aria-haspopup="dialog" aria-controls="app-confirmation-dialog">Delete list</x-app.action>
    </form>
</x-app.collapsible-panel>
@endif
@endsection
