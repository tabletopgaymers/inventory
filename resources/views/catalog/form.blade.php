@extends('layout')
@php($singular = \App\Support\CatalogRecords::SINGULAR[$kind])
@section('title', $record ? 'Edit '.$singular.' - '.$record->name : 'New '.$singular)
@section('page-actions')
<x-app.action variant="discard" href="{{ $record ? $recordBase.$record->id : $indexUrl }}">Cancel</x-app.action>
<button type="submit" class="primary app-save-progress" form="catalog-form" data-catalog-save aria-busy="false"><span data-save-label>{{ $record ? 'Save' : 'Create' }}</span><span class="app-search-spinner" aria-hidden="true"></span></button>
@endsection
@section('title-status')
@if($record)<x-app.status-badge :status="ucfirst($record->state)" />@endif
@endsection
@section('content-sections')
<div data-catalog-save-feedback role="status" hidden></div>
<div class="app-content">
<form id="catalog-form" method="post" data-catalog-progress data-record-base="{{ $recordBase }}" data-save-mode="{{ $record ? 'edit' : 'create' }}" data-save-dirty="{{ $errors->any() || session('error') ? 'true' : 'false' }}">@csrf
@if($kind !== 'items')<label>Name<input name="name" required maxlength="255" value="{{ old('name', $record?->name) }}"></label>@endif
@if($kind === 'collections')
<label>Category<select name="category_id" required>@if(!$record)<option value="" @selected(!old('category_id'))>– select –</option>@endif @foreach($categories as $category)@if($category->state === 'active' || $record?->category_id === $category->id)<option value="{{ $category->id }}" @selected(old('category_id', $record?->category_id) == $category->id)>{{ $category->name }} ({{ $category->state }})</option>@endif @endforeach</select></label>
<label>SKU prefix<input name="sku_prefix" required maxlength="50" pattern="[A-Za-z0-9_-]+" value="{{ old('sku_prefix', $record ? rtrim($record->sku_prefix, '-') : null) }}"></label>
@if($record)<p>Changing the prefix updates existing item SKUs. The separator hyphen is added automatically; historical records retain their recorded SKUs.</p>@endif
@endif
@if($kind === 'items')@include('catalog.item-fields')@endif
@if(in_array($kind, ['categories','collections','storage_locations']))<label>Description<input type="text" data-optional name="description" maxlength="5000" value="{{ old('description', $record?->description) }}"></label>@endif
@if($kind === 'storage_locations')<label>Mailing address<textarea data-optional name="mailing_address" maxlength="5000">{{ old('mailing_address', $record?->mailing_address) }}</textarea></label>@endif
@if($kind === 'suppliers')@foreach(['contact_name'=>'Contact name','email'=>'Email','phone'=>'Phone','website'=>'Website'] as $field=>$label)<label>{{ $label }}<input data-optional name="{{ $field }}" maxlength="255" value="{{ old($field, $record?->$field) }}"></label>@endforeach<label>Address<textarea data-optional name="address" maxlength="5000">{{ old('address', $record?->address) }}</textarea></label>@endif
@if(in_array($kind,['suppliers','purposes','programs']))<label>Notes<textarea data-optional name="notes" maxlength="5000">{{ old('notes', $kind === 'items' ? $metadata?->notes : $record?->notes) }}</textarea></label>@endif
</form>
</div>
@if($record)
<p>Status changes apply only to this record; associated records, stock and earlier history are retained.</p>
@if($kind === 'storage_locations' && $record->is_central)
<p>Central must remain the distinguished active storage location.</p>
@else
<form method="post" action="{{ $actionBase }}/lifecycle">@csrf
<div class="actions">
@if($record->state === 'archived')
<x-app.action type="submit" name="action" value="restore">Restore</x-app.action>
@else
<x-app.action variant="danger" href="{{ $actionBase }}/archive" data-catalog-archive-trigger aria-haspopup="dialog" aria-controls="catalog-archive-dialog">Archive</x-app.action>
<x-app.action type="submit" name="action" value="{{ $record->state === 'active' ? 'inactivate' : 'activate' }}">{{ $record->state === 'active' ? 'Deactivate' : 'Activate' }}</x-app.action>
@endif
</div>
</form>
@endif
@if($record->state !== 'archived' && !($kind === 'storage_locations' && $record->is_central))
@include('catalog.archive-dialog')
@endif
@endif
<script src="/catalog.js?v={{ filemtime(public_path('catalog.js')) }}" defer></script>
<script src="/catalog-progress.js?v={{ filemtime(public_path('catalog-progress.js')) }}" defer></script>
@endsection
