@extends('layout')
@section('title', $record->name)
@section('title-status')<x-app.status-badge :status="ucfirst($record->state)" />@endsection
@section('page-actions')
@if($canManage)<a class="button" href="/catalog/{{ $kind }}/{{ $record->id }}/edit">Edit</a>@endif
@endsection
@section('content-sections')
<div class="app-content">
    <dl class="app-user-details">
        @if($kind === 'suppliers')
        @foreach(['contact_name' => 'Contact name', 'email' => 'Email', 'phone' => 'Phone', 'website' => 'Website', 'address' => 'Address'] as $field => $fieldLabel)
        <dt>{{ $fieldLabel }}</dt><dd class="plain-text">{{ $record->$field ?: '—' }}</dd>
        @endforeach
        @endif
        @if($category)<dt>Category</dt><dd><a href="/catalog/categories/{{ $category->id }}">{{ $category->name }}</a></dd><dt>SKU prefix</dt><dd>{{ $record->sku_prefix }}</dd>@endif
        @if(in_array($kind, ['categories', 'collections']))<dt>Description</dt><dd>{{ $record->description ?: '—' }}</dd>@endif
        @if(!in_array($kind, ['categories', 'collections']))<dt>Notes</dt><dd class="plain-text">{{ $record->notes ?: '—' }}</dd>@endif
        @if(in_array($kind, ['categories', 'collections']))<dt>{{ $kind === 'categories' ? 'Collections' : 'Items' }}</dt><dd><a href="{{ $kind === 'categories' ? '#catalog-child-table' : '/catalog/items?collection='.$record->id }}">{{ number_format($children->count()) }}</a></dd>@endif
    </dl>
</div>
@if($kind === 'categories' && $canManage)<div class="app-actions"><a class="button app-new-action" href="/catalog/collections/create?category={{ $record->id }}">+ New</a></div>@endif
@if(in_array($kind, ['categories', 'collections']))
<x-app.table-region :label="$kind === 'categories' ? 'Collections in category' : 'Items in collection'" class="app-list-table-panel" id="catalog-child-table">
<table class="catalog-table"><thead><tr><th>{{ $kind === 'categories' ? 'Collections' : 'Name' }}</th>@if($kind === 'categories')<th>Description</th>@endif<th>{{ $kind === 'categories' ? 'SKU Prefix' : 'SKU' }}</th>@if($kind === 'categories')<th class="number">Entries</th>@endif<th>Status</th></tr></thead><tbody>
@forelse($children as $child)
<tr><td><a href="{{ $kind === 'categories' ? '/catalog/collections/' : '/catalog/items/' }}{{ $child->id }}">{{ $child->name }}</a></td>@if($kind === 'categories')<td>{{ $child->description ?: '—' }}</td>@endif<td>{{ $kind === 'categories' ? $child->sku_prefix : $child->sku }}</td>@if($kind === 'categories')<td class="number"><a href="/catalog/items?collection={{ $child->id }}">{{ number_format($child->child_count) }}</a></td>@endif<td><x-app.status-badge :status="ucfirst($child->state)" /></td></tr>
@empty<tr><td colspan="{{ $kind === 'categories' ? 5 : 3 }}">No {{ $kind === 'categories' ? 'collections in this category' : 'items in this collection' }}.</td></tr>
@endforelse
</tbody></table>
</x-app.table-region>
<p class="app-field-help">All retained {{ $kind === 'categories' ? 'collections in this category' : 'items in this collection' }} are listed, including inactive and archived records.</p>
@endif
@endsection
