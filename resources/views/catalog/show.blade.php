@extends('layout')
@section('title', $record->name)
@section('title-status')<x-app.status-badge :status="ucfirst($record->state)" />@endsection
@section('page-actions')
@if($purchaseReturnUrl)<x-app.action href="{{ $purchaseReturnUrl }}">Return to purchase</x-app.action>@endif
@if($canManage)<x-app.action  href="/catalog/{{ $kind }}/{{ $record->id }}/edit{{ $purchaseReturnQuery }}">Edit</x-app.action>@endif
@endsection
@section('content-sections')
<div class="app-content">
    @if($kind === 'suppliers')
        @php
            $emailHref = $record->contact_name
                ? 'mailto:%22'.rawurlencode($record->contact_name).'%22<'.$record->email.'>'
                : 'mailto:'.$record->email;
            $websiteText = preg_replace('/\Ahttps?:\/\//i', '', $record->website ?? '');
            $websiteText = preg_replace('/\Awww\./i', '', $websiteText);
            if (mb_strlen($websiteText) > 30) $websiteText = mb_substr($websiteText, 0, 30).'…';
            $websiteScheme = strtolower(parse_url($record->website ?? '', PHP_URL_SCHEME) ?? '');
        @endphp
        <div class="app-columns app-columns-two">
            <x-app.detail-list variant="content">
                @foreach(['contact_name' => 'Contact name', 'email' => 'Email', 'phone' => 'Phone', 'website' => 'Website'] as $field => $fieldLabel)
                    <dt>{{ $fieldLabel }}</dt>
                    @if(!$record->$field)
                        <dd>—</dd>
                    @elseif($field === 'email')
                        <dd><a href="{{ $emailHref }}">{{ $record->email }}</a></dd>
                    @elseif($field === 'phone')
                        <dd><a href="tel:{{ preg_replace('/\s+/u', '', $record->phone) }}">{{ $record->phone }}</a></dd>
                    @elseif($field === 'website' && in_array($websiteScheme, ['http', 'https'], true))
                        <dd><a href="{{ $record->website }}" title="{{ $record->website }}" target="_blank" rel="noopener noreferrer">{{ $websiteText }}</a></dd>
                    @else
                        <dd>{{ $record->$field }}</dd>
                    @endif
                @endforeach
            </x-app.detail-list>
            <x-app.detail-list variant="content">
                <dt>Address</dt><dd class="plain-text">{{ $record->address ?: '—' }}</dd>
                <dt>Notes</dt><dd class="plain-text">{{ $record->notes ?: '—' }}</dd>
            </x-app.detail-list>
        </div>
    @else
        <x-app.detail-list variant="content">
            @if($category)<dt>Category</dt><dd><a href="/catalog/categories/{{ $category->id }}">{{ $category->name }}</a></dd><dt>SKU prefix</dt><dd>{{ $record->sku_prefix }}</dd>@endif
            @if(in_array($kind, ['categories', 'collections']))<dt>Description</dt><dd>{{ $record->description ?: '—' }}</dd>@endif
            @if(in_array($kind, ['purposes', 'programs'], true))
                <dt>Description</dt><dd class="plain-text">{{ filled($record->description) ? $record->description : '—' }}</dd>
            @endif
            @if($kind === 'purposes')
                <dt>Examples</dt><dd>{{ filled($record->examples) ? $record->examples : '—' }}</dd>
            @endif
            @if(in_array($kind, ['categories', 'collections']))<dt>{{ $kind === 'categories' ? 'Collections' : 'Items' }}</dt><dd><a href="{{ $kind === 'categories' ? '#catalog-child-table' : '/catalog/items?collection='.$record->id }}">{{ number_format($children->count()) }}</a></dd>@endif
        </x-app.detail-list>
    @endif
</div>
@if($kind === 'categories' && $canManage)<div class="app-actions"><x-app.action variant="new"  href="/catalog/collections/create?category={{ $record->id }}">＋ New</x-app.action></div>@endif
@if($kind === 'collections' && $canManage)<div class="app-actions"><x-app.action variant="new" href="/catalog/items/create?collection={{ $record->id }}">＋ New</x-app.action></div>@endif
@if(in_array($kind, ['categories', 'collections']))
<x-app.table-region :label="$kind === 'categories' ? 'Collections in category' : 'Items in collection'" class="app-list-table-panel" id="catalog-child-table">
<table class="catalog-table"><thead><tr><th>{{ $kind === 'categories' ? 'Collections' : 'Name' }}</th>@if($kind === 'categories')<th>Description</th>@endif<th>{{ $kind === 'categories' ? 'SKU Prefix' : 'SKU' }}</th>@if($kind === 'categories')<th class="number">Entries</th>@endif<th>Status</th></tr></thead><tbody>
@forelse($children as $child)
<tr><td><a href="{{ $kind === 'categories' ? '/catalog/collections/' : '/catalog/items/' }}{{ $child->id }}">{{ $child->name }}</a></td>@if($kind === 'categories')<td>{{ $child->description ?: '—' }}</td>@endif<td>{{ $kind === 'categories' ? $child->sku_prefix : $child->sku }}</td>@if($kind === 'categories')<td class="number"><a href="/catalog/items?collection={{ $child->id }}">{{ number_format($child->child_count) }}</a></td>@endif<td><x-app.status-badge :status="ucfirst($child->state)" /></td></tr>
@empty<tr><td colspan="{{ $kind === 'categories' ? 5 : 3 }}">No {{ $kind === 'categories' ? 'collections in this category' : 'items in this collection' }}.</td></tr>
@endforelse
</tbody></table>
</x-app.table-region>
@endif
@endsection
