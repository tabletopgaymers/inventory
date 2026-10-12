@extends('layout')
@section('title', $collection ? 'Items in '.$collection->name : ($category ? 'Collections in '.$category->name : $kinds[$kind]))
@section('page-actions')
@if($canManage)<a class="button app-new-action" href="/catalog/{{ $kind }}/create" title="Create {{ strtolower($kinds[$kind]) }}">+New</a>@endif
@endsection
@section('content-sections')
@if($category)
<nav class="app-breadcrumb" aria-label="Breadcrumb"><a href="/catalog/categories">Categories</a><span>›</span><a href="/catalog/collections?category={{ $category->id }}">{{ $category->name }}</a>@if($collection)<span>› {{ $collection->name }}</span>@endif</nav>
<p>Category: <strong>{{ $category->name }}</strong>@if($collection) · Collection: <strong>{{ $collection->name }}</strong>@endif. Only {{ $collection ? 'items in this collection' : 'collections in this category' }} are shown. <a href="/catalog/{{ $kind }}">Show all {{ strtolower($kinds[$kind]) }}</a>.</p>
@endif
@if($kind === 'collections')
<div class="app-content"><form method="get" action="/catalog/collections" class="app-actions">
    <select id="catalog-category" name="category" aria-label="Category" data-catalog-category-filter><option value="">All categories</option>@foreach($categories as $option)<option value="{{ $option->id }}" @selected($category?->id === $option->id)>{{ $option->name }}</option>@endforeach</select>
    <noscript><x-app.action variant="primary" type="submit">Apply</x-app.action></noscript>
</form></div>
@endif
<x-app.table-region :label="$kinds[$kind].' records'" class="app-list-table-panel">
<table class="catalog-table"><thead><tr><th>Name</th>
@if($kind === 'categories')<th>Description</th><th class="number">Collections</th><th>Status</th>
@elseif($kind === 'collections')<th>Category</th><th>SKU prefix</th><th class="number">Items</th><th>Status</th>
@elseif($kind === 'items')<th>SKU</th><th>Status</th>
@elseif($kind === 'storage_locations')<th>Description</th><th>Status</th>
@elseif($kind === 'suppliers')<th>Contact</th><th>Email</th><th>Status</th>
@else<th>Notes</th><th>Status</th>@endif
@if($canManage && !in_array($kind, ['categories', 'collections']))<th>Edit</th>@endif</tr></thead><tbody>
@php
    $collectionGroup = null;
@endphp
@forelse($records as $record)
@if($kind === 'collections' && $collectionGroup !== $record->category_id)
@php
    $collectionGroup = $record->category_id;
@endphp
<tr class="collection-heading"><th colspan="5">{{ $record->category_name }}</th></tr>
@elseif($kind === 'items' && $collectionGroup !== $record->collection_id)
@php
    $collectionGroup = $record->collection_id;
@endphp
<tr class="collection-heading"><th colspan="{{ 3 + ($canManage ? 1 : 0) }}">{{ $record->category_name }} : {{ $record->collection_name }}</th></tr>
@endif
<tr><td>@if($kind === 'items')<a href="/inventory/items/{{ $record->id }}">{{ $record->name }}</a>@elseif($kind === 'storage_locations')<a href="/inventory/locations/{{ $record->id }}">{{ $record->name }}</a>@elseif(in_array($kind, ['categories', 'collections']) && $canManage)<a href="/catalog/{{ $kind }}/{{ $record->id }}/edit">{{ $record->name }}</a>@else{{ $record->name }}@endif
@php
    $detailFields = match ($kind) {
        'categories' => ['notes' => 'Notes'],
        'collections' => ['description' => 'Description', 'notes' => 'Notes'],
        'items' => ['description' => 'Description', 'notes' => 'Notes'],
        'storage_locations' => ['notes' => 'Notes'],
        'suppliers' => ['phone' => 'Phone', 'website' => 'Website', 'address' => 'Address', 'notes' => 'Notes'],
        default => [],
    };
    $recordDetails = collect($detailFields)->filter(fn ($fieldLabel, $field) => isset($record->$field) && $record->$field !== '');
@endphp
@if($recordDetails->isNotEmpty() && !in_array($kind, ['categories', 'collections']))
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
@if(in_array($kind, ['categories', 'collections']))
<td><details class="app-record-details"><summary><x-app.status-badge :status="ucfirst($record->state)" /><span class="sr-only"> details for {{ $record->name }}</span></summary>
@if($recordDetails->isNotEmpty())
<dl class="item-metadata">@foreach($recordDetails as $field => $fieldLabel)<dt>{{ $fieldLabel }}</dt><dd>{{ $record->$field }}</dd>@endforeach</dl>
@else<p class="app-field-help">No additional details.</p>@endif
</details></td>
@elseif($canManage)<td><x-app.action size="small" href="/catalog/{{ $kind }}/{{ $record->id }}/edit">Edit<span class="sr-only"> {{ $record->name }}</span></x-app.action></td>@endif
</tr>
@empty<tr><td colspan="{{ $kind === 'categories' ? 4 : ($kind === 'collections' ? 5 : (($kind === 'suppliers' ? 4 : 3) + ($canManage ? 1 : 0))) }}">No records in this view.</td></tr>@endforelse
</tbody></table>
</x-app.table-region>
<p class="app-field-help">Retained catalog records. Status changes apply only to the selected record.</p>
@endsection
