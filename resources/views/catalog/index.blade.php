@extends('layout')
@php
    $columnCount = $kind === 'categories' ? 4 : ($kind === 'collections' ? 5 : ($kind === 'suppliers' ? 5 : 3));
@endphp
@section('title', $collection ? 'Items in '.$collection->name : ($category ? 'Collections in '.$category->name : $kinds[$kind]))
@if($canManage)
@section('page-actions')
<a class="button app-new-action" href="/catalog/{{ $kind }}/create" title="Create {{ \App\Support\CatalogRecords::SINGULAR[$kind] }}">+ New</a>
@endsection
@endif
@section('content-sections')
@if($category)
<nav class="app-breadcrumb" aria-label="Breadcrumb"><a href="/catalog/categories">Categories</a><span>›</span><a href="/catalog/categories/{{ $category->id }}">{{ $category->name }}</a>@if($collection)<span>› {{ $collection->name }}</span>@endif</nav>
<p>Category: <strong>{{ $category->name }}</strong>@if($collection) · Collection: <strong>{{ $collection->name }}</strong>@endif. Only {{ $collection ? 'items in this collection' : 'collections in this category' }} are shown. <a href="/catalog/{{ $kind }}">Show all {{ strtolower($kinds[$kind]) }}</a>.</p>
@endif
<x-app.table-region :label="$kinds[$kind].' records'" class="app-list-table-panel">
<table class="catalog-table"><thead><tr><th>{{ $kind === 'categories' ? 'Category' : ($kind === 'collections' ? 'Category/Collection' : 'Name') }}</th>
@if($kind === 'categories')<th>Description</th><th class="number">Entries</th>
@elseif($kind === 'collections')<th>Description</th><th>SKU Prefix</th><th class="number">Entries</th>
@elseif($kind === 'items')<th>SKU</th>
@elseif($kind === 'storage_locations')<th>Description</th>
@elseif($kind === 'suppliers')<th>Contact</th><th>Email</th><th>Website</th>
@else<th>Notes</th>@endif
@include('catalog.status-filter')
</tr></thead><tbody>
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
<tr class="collection-heading"><th colspan="{{ $columnCount }}"><a href="/catalog/categories/{{ $record->category_id }}">{{ $record->category_name }}</a> : <a href="/catalog/collections/{{ $record->collection_id }}">{{ $record->collection_name }}</a></th></tr>
@endif
<tr><td @class(['app-grouped-item' => in_array($kind, ['collections', 'items'])])><a href="{{ \App\Support\CatalogRecords::viewUrl($kind, $record->id) }}">{{ $record->name }}</a></td>
@if($kind === 'categories')<td>{{ $record->description ?: '—' }}</td><td class="number"><a href="/catalog/categories/{{ $record->id }}">{{ $record->child_count }}<span class="sr-only"> collections in {{ $record->name }}</span></a></td>
@elseif($kind === 'collections')<td>{{ $record->description ?: '—' }}</td><td>{{ $record->sku_prefix }}</td><td class="number"><a href="/catalog/items?collection={{ $record->id }}">{{ $record->child_count }}<span class="sr-only"> items in {{ $record->name }}</span></a></td>
@elseif($kind === 'items')<td>{{ $record->sku }}</td><td><x-app.status-badge :status="ucfirst($record->state)" /></td>
@elseif($kind === 'storage_locations')<td>{{ $record->description ?: '—' }}</td><td><x-app.status-badge :status="ucfirst($record->state)" /></td>
@elseif($kind === 'suppliers')<td>{{ $record->contact_name ?: '—' }}</td><td>{{ $record->email ?: '—' }}</td>
@php
    $websiteHost = parse_url($record->website ?? '', PHP_URL_HOST);
    $websiteScheme = strtolower(parse_url($record->website ?? '', PHP_URL_SCHEME) ?? '');
@endphp
<td>@if($websiteHost && in_array($websiteScheme, ['http', 'https'], true))<a href="{{ $record->website }}" target="_blank" rel="noopener noreferrer">{{ preg_replace('/\Awww\./i', '', $websiteHost) }}</a>@else—@endif</td>
<td><x-app.status-badge :status="ucfirst($record->state)" /></td>
@else<td>{{ $record->notes ?: '—' }}</td><td><x-app.status-badge :status="ucfirst($record->state)" /></td>@endif
@if(in_array($kind, ['categories', 'collections']))
<td><x-app.status-badge :status="ucfirst($record->state)" /></td>
@endif
</tr>
@empty<tr><td colspan="{{ $columnCount }}">No records in this view.</td></tr>@endforelse
</tbody></table>
</x-app.table-region>
@vite('resources/js/catalog-status-filter.js')
@endsection
