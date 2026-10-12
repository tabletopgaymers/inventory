@extends('layout')
@php
    $showEditColumn = $canManage && !in_array($kind, ['categories', 'collections', 'items', 'storage_locations']);
    $columnCount = ($kind === 'categories' ? 4 : ($kind === 'collections' ? 5 : ($kind === 'suppliers' ? 4 : 3))) + ($showEditColumn ? 1 : 0);
@endphp
@section('title', $collection ? 'Items in '.$collection->name : ($category ? 'Collections in '.$category->name : $kinds[$kind]))
@section('page-actions')
@if($canManage)<a class="button app-new-action" href="/catalog/{{ $kind }}/create" title="Create {{ strtolower($kinds[$kind]) }}">+New</a>@endif
@endsection
@section('content-sections')
@if($category)
<nav class="app-breadcrumb" aria-label="Breadcrumb"><a href="/catalog/categories">Categories</a><span>›</span><a href="/catalog/categories/{{ $category->id }}">{{ $category->name }}</a>@if($collection)<span>› {{ $collection->name }}</span>@endif</nav>
<p>Category: <strong>{{ $category->name }}</strong>@if($collection) · Collection: <strong>{{ $collection->name }}</strong>@endif. Only {{ $collection ? 'items in this collection' : 'collections in this category' }} are shown. <a href="/catalog/{{ $kind }}">Show all {{ strtolower($kinds[$kind]) }}</a>.</p>
@endif
<x-app.table-region :label="$kinds[$kind].' records'" class="app-list-table-panel">
<table class="catalog-table"><thead><tr><th>{{ $kind === 'categories' ? 'Category' : ($kind === 'collections' ? 'Category/Collection' : 'Name') }}</th>
@if($kind === 'categories')<th>Description</th><th class="number">Entries</th><th>Status</th>
@elseif($kind === 'collections')<th>Description</th><th>SKU Prefix</th><th class="number">Entries</th><th>Status</th>
@elseif($kind === 'items')<th>SKU</th><th>Status</th>
@elseif($kind === 'storage_locations')<th>Description</th><th>Status</th>
@elseif($kind === 'suppliers')<th>Contact</th><th>Email</th><th>Status</th>
@else<th>Notes</th><th>Status</th>@endif
@if($showEditColumn)<th>Edit</th>@endif</tr></thead><tbody>
@php
    $collectionGroup = null;
@endphp
@forelse($records as $record)
@if($kind === 'collections' && $collectionGroup !== $record->category_id)
@php
    $collectionGroup = $record->category_id;
@endphp
<tr class="collection-heading"><th colspan="5"><a href="/catalog/categories/{{ $record->category_id }}">{{ $record->category_name }}</a></th></tr>
@elseif($kind === 'items' && $collectionGroup !== $record->collection_id)
@php
    $collectionGroup = $record->collection_id;
@endphp
<tr class="collection-heading"><th colspan="{{ $columnCount }}">{{ $record->category_name }} : {{ $record->collection_name }}</th></tr>
@endif
<tr><td @class(['app-grouped-item' => in_array($kind, ['collections', 'items'])])>@if($kind === 'items')<a href="/inventory/items/{{ $record->id }}">{{ $record->name }}</a>@elseif($kind === 'storage_locations')<a href="/inventory/locations/{{ $record->id }}">{{ $record->name }}</a>@elseif(in_array($kind, ['categories', 'collections']))<a href="/catalog/{{ $kind }}/{{ $record->id }}">{{ $record->name }}</a>@else{{ $record->name }}@endif
@php
    $detailFields = match ($kind) {
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
@if($kind === 'categories')<td>{{ $record->description ?: '—' }}</td><td class="number"><a href="/catalog/categories/{{ $record->id }}">{{ $record->child_count }}<span class="sr-only"> collections in {{ $record->name }}</span></a></td>
@elseif($kind === 'collections')<td>{{ $record->description ?: '—' }}</td><td>{{ $record->sku_prefix }}</td><td class="number"><a href="/catalog/items?collection={{ $record->id }}">{{ $record->child_count }}<span class="sr-only"> items in {{ $record->name }}</span></a></td>
@elseif($kind === 'items')<td>{{ $record->sku }}</td><td><x-app.status-badge :status="ucfirst($record->state)" /></td>
@elseif($kind === 'storage_locations')<td>{{ $record->description ?: '—' }}</td><td><x-app.status-badge :status="ucfirst($record->state)" /></td>
@elseif($kind === 'suppliers')<td>{{ $record->contact_name ?: '—' }}</td><td>{{ $record->email ?: '—' }}</td><td><x-app.status-badge :status="ucfirst($record->state)" /></td>
@else<td>{{ $record->notes ?: '—' }}</td><td><x-app.status-badge :status="ucfirst($record->state)" /></td>@endif
@if(in_array($kind, ['categories', 'collections']))
<td><x-app.status-badge :status="ucfirst($record->state)" /></td>
@elseif($showEditColumn)<td><x-app.action size="small" href="/catalog/{{ $kind }}/{{ $record->id }}/edit">Edit<span class="sr-only"> {{ $record->name }}</span></x-app.action></td>@endif
</tr>
@empty<tr><td colspan="{{ $columnCount }}">No records in this view.</td></tr>@endforelse
</tbody></table>
</x-app.table-region>
<p class="app-field-help">Retained catalog records. Status changes apply only to the selected record.</p>
@endsection
