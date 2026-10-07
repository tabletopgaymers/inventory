@extends('layout')
@section('title', $collection ? 'Items in '.$collection->name : ($category ? 'Collections in '.$category->name : $kinds[$kind]))
@section('content')
@if($category)
<nav class="app-breadcrumb" aria-label="Breadcrumb"><a href="/catalog/categories">Categories</a><span>›</span><a href="/catalog/collections?category={{ $category->id }}">{{ $category->name }}</a>@if($collection)<span>› {{ $collection->name }}</span>@endif</nav>
<p>Category: <strong>{{ $category->name }}</strong>@if($collection) · Collection: <strong>{{ $collection->name }}</strong>@endif. Only {{ $collection ? 'items in this collection' : 'collections in this category' }} are shown. <a href="/catalog/{{ $kind }}">Show all {{ strtolower($kinds[$kind]) }}</a>.</p>
@endif
@if($kind === 'collections')
<form method="get" action="/catalog/collections" class="search-actions">
    <x-app.field label="Category" for="catalog-category"><select id="catalog-category" name="category"><option value="">All categories</option>@foreach($categories as $option)<option value="{{ $option->id }}" @selected($category?->id === $option->id)>{{ $option->name }}</option>@endforeach</select></x-app.field>
    <x-app.action variant="primary" type="submit">Apply</x-app.action>
</form>
@endif
<div class="section-heading"><p>Retained catalog records. Status changes apply only to the selected record.</p>@if($canManage)<x-app.action variant="primary" href="/catalog/{{ $kind }}/create">Create</x-app.action>@endif</div>
<x-app.table-region :label="$kinds[$kind].' records'">
<table class="catalog-table"><thead><tr><th>Name</th>
@if($kind === 'categories')<th>Description</th><th class="number">Collection count</th>
@elseif($kind === 'collections')<th>Category</th><th>SKU prefix</th><th class="number">Item count</th>
@elseif($kind === 'items')<th>SKU</th><th>Status</th>
@elseif($kind === 'storage_locations')<th>Description</th><th>Status</th>
@elseif($kind === 'suppliers')<th>Contact</th><th>Email</th><th>Status</th>
@else<th>Notes</th><th>Status</th>@endif
@if($canManage)<th>Edit</th>@endif</tr></thead><tbody>
@forelse($records as $record)
<tr><td>@if($kind === 'items')<a href="/inventory/items/{{ $record->id }}">{{ $record->name }}</a>@elseif($kind === 'storage_locations')<a href="/inventory/locations/{{ $record->id }}">{{ $record->name }}</a>@elseif($kind === 'collections')<a href="/catalog/items?collection={{ $record->id }}">{{ $record->name }}</a>@else{{ $record->name }}@endif
@php
    $detailFields = match ($kind) {
        'categories' => ['state' => 'Status', 'notes' => 'Notes'],
        'collections' => ['state' => 'Status', 'description' => 'Description', 'notes' => 'Notes'],
        'items' => ['description' => 'Description', 'notes' => 'Notes'],
        'storage_locations' => ['notes' => 'Notes'],
        'suppliers' => ['phone' => 'Phone', 'website' => 'Website', 'address' => 'Address', 'notes' => 'Notes'],
        default => [],
    };
    $recordDetails = collect($detailFields)->filter(fn ($fieldLabel, $field) => isset($record->$field) && $record->$field !== '');
@endphp
@if($recordDetails->isNotEmpty())
<details class="app-record-details"><summary>Details<span class="sr-only"> for {{ $record->name }}</span></summary><dl class="item-metadata">
@foreach($recordDetails as $field => $fieldLabel)<dt>{{ $fieldLabel }}</dt><dd>@if($field === 'state')<x-app.status-badge :status="ucfirst($record->state)" />@else{{ $record->$field }}@endif</dd>@endforeach
</dl></details>
@endif
</td>
@if($kind === 'categories')<td>{{ $record->description ?: '—' }}</td><td class="number"><a href="/catalog/collections?category={{ $record->id }}">{{ $record->child_count }}<span class="sr-only"> collections in {{ $record->name }}</span></a></td>
@elseif($kind === 'collections')<td>{{ $record->category_name }}</td><td>{{ $record->sku_prefix }}</td><td class="number"><a href="/catalog/items?collection={{ $record->id }}">{{ $record->child_count }}<span class="sr-only"> items in {{ $record->name }}</span></a></td>
@elseif($kind === 'items')<td>{{ $record->sku }}</td><td><x-app.status-badge :status="ucfirst($record->state)" /></td>
@elseif($kind === 'storage_locations')<td>{{ $record->description ?: '—' }}</td><td><x-app.status-badge :status="ucfirst($record->state)" /></td>
@elseif($kind === 'suppliers')<td>{{ $record->contact_name ?: '—' }}</td><td>{{ $record->email ?: '—' }}</td><td><x-app.status-badge :status="ucfirst($record->state)" /></td>
@else<td>{{ $record->notes ?: '—' }}</td><td><x-app.status-badge :status="ucfirst($record->state)" /></td>@endif
@if($canManage)<td><x-app.action size="small" href="/catalog/{{ $kind }}/{{ $record->id }}/edit">Edit<span class="sr-only"> {{ $record->name }}</span></x-app.action></td>@endif
</tr>
@empty<tr><td colspan="{{ ($kind === 'collections' || $kind === 'suppliers' ? 4 : 3) + ($canManage ? 1 : 0) }}">No records in this view.</td></tr>@endforelse
</tbody></table>
</x-app.table-region>
@endsection
