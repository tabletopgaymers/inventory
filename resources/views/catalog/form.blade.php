@extends('layout')
@php($singular = \App\Support\CatalogRecords::SINGULAR[$kind])
@section('title', $record ? 'Edit '.$singular.' - '.$record->name : 'New '.$singular)
@section('page-actions')
<x-app.action variant="discard" href="{{ $record ? \App\Support\CatalogRecords::viewUrl($kind, $record->id) : '/catalog/'.$kind }}">Cancel</x-app.action>
<button type="submit" class="primary app-save-progress" form="catalog-form" data-catalog-save aria-busy="false"><span data-save-label>{{ $record ? 'Save' : 'Create' }}</span><span class="app-search-spinner" aria-hidden="true"></span></button>
@endsection
@section('title-status')
@if($record)<x-app.status-badge :status="ucfirst($record->state)" />@endif
@endsection
@section('content-sections')
<div data-catalog-save-feedback role="status" hidden></div>
<div class="app-content">
<form id="catalog-form" method="post" data-catalog-progress data-record-base="{{ \App\Support\CatalogRecords::viewBase($kind) }}" data-save-mode="{{ $record ? 'edit' : 'create' }}" data-save-dirty="{{ $errors->any() || session('error') ? 'true' : 'false' }}">@csrf
<label>Name<input name="name" required maxlength="255" value="{{ old('name', $record?->name) }}"></label>
@if($kind === 'collections')
<label>Category<select name="category_id" required>@if(!$record && $selectedCategory && $selectedCategory->state !== 'active' && !old('category_id'))<option value="" disabled selected>{{ $selectedCategory->name }} ({{ $selectedCategory->state }}) — choose an active Category</option>@endif @foreach($categories as $category)@if($category->state === 'active' || $record?->category_id === $category->id)<option value="{{ $category->id }}" @selected(old('category_id', $record?->category_id ?? $selectedCategory?->id) == $category->id)>{{ $category->name }} ({{ $category->state }})</option>@endif @endforeach</select></label>
<label>SKU prefix<input name="sku_prefix" required maxlength="50" pattern="[A-Za-z0-9_-]+" value="{{ old('sku_prefix', $record ? rtrim($record->sku_prefix, '-') : null) }}"></label>
@if($record)<p>Changing the prefix updates existing item SKUs. The separator hyphen is added automatically; historical records retain their recorded SKUs.</p>@endif
@endif
@if($kind === 'items')
<label>Collection<select name="collection_id" required>@foreach($collections as $collection)@if($collection->state === 'active' || $record?->collection_id === $collection->id)<option value="{{ $collection->id }}" data-prefix="{{ $collection->sku_prefix }}" @selected(old('collection_id', $record?->collection_id) == $collection->id)>{{ $collection->category_name }} : {{ $collection->name }} ({{ $collection->state }})</option>@endif @endforeach</select></label>
<label>SKU suffix<input name="sku_suffix" required maxlength="50" pattern="[A-Za-z0-9_-]+" value="{{ old('sku_suffix', $record?->sku_suffix) }}"></label><p>SKU preview: <output id="sku-preview"></output></p>
<label>Variety<input data-optional name="variety" maxlength="255" value="{{ old('variety', $metadata?->variety) }}"></label>
<label>Purpose<select data-optional name="purpose_id"><option value="">None</option>@foreach($purposes as $purpose)@if($purpose->state === 'active' || $metadata?->purpose_id === $purpose->id)<option value="{{ $purpose->id }}" @selected(old('purpose_id', $metadata?->purpose_id) == $purpose->id)>{{ $purpose->name }} ({{ $purpose->state }})</option>@endif @endforeach</select></label>
<fieldset data-optional><legend>Programs</legend>@foreach($programs as $program)@if($program->state === 'active' || in_array($program->id, $selectedPrograms))<label class="app-choice"><input type="checkbox" name="programs[]" value="{{ $program->id }}" @checked(in_array($program->id, old('programs', $errors->any() ? [] : $selectedPrograms)))>{{ $program->name }} ({{ $program->state }})</label>@endif @endforeach</fieldset>
<label>Request bundle type<input data-optional name="bundle_type" @required(old('bundle_quantity', $metadata?->bundle_quantity) !== null && old('bundle_quantity', $metadata?->bundle_quantity) !== '') maxlength="255" value="{{ old('bundle_type', $metadata?->bundle_type) }}"></label><label>Bundle quantity (individual units)<input data-optional name="bundle_quantity" inputmode="numeric" value="{{ old('bundle_quantity', $metadata?->bundle_quantity) }}"></label>
<p>Bundle values are hints; they do not enforce multiples or create kits.</p>
@foreach(['irs_fmv'=>'IRS FMV','in_person_ask'=>'In-person ask','online_ask'=>'Online ask'] as $field=>$label)<label>{{ $label }}<input data-optional name="{{ $field }}" inputmode="decimal" value="{{ old($field, $metadata?->$field) }}"></label>@endforeach
<p>Optional monetary fields preserve blank and explicit zero. New items start with zero stock and unknown Unit cost.</p>
@if($record)<label>Current unit cost<input readonly value="{{ \App\Support\StockNumbers::displayCost($record->unit_cost) }}"></label>@if(auth()->user()->hasRole('admin'))<p><a href="/inventory/items/{{ $record->id }}/cost">Adjust unit cost with immutable history</a></p>@endif @endif
@endif
@if(in_array($kind, ['categories','collections','storage_locations']))<label>Description<textarea data-optional name="description" maxlength="5000">{{ old('description', $record?->description) }}</textarea></label>@endif
@if($kind === 'suppliers')@foreach(['contact_name'=>'Contact name','email'=>'Email','phone'=>'Phone','website'=>'Website'] as $field=>$label)<label>{{ $label }}<input data-optional name="{{ $field }}" maxlength="255" value="{{ old($field, $record?->$field) }}"></label>@endforeach<label>Address<textarea data-optional name="address" maxlength="5000">{{ old('address', $record?->address) }}</textarea></label>@endif
@if(in_array($kind,['items','storage_locations','suppliers','purposes','programs']))<label>Notes<textarea data-optional name="notes" maxlength="5000">{{ old('notes', $kind === 'items' ? $metadata?->notes : $record?->notes) }}</textarea></label>@endif
</form>
</div>
@if($record)
<p>Status changes apply only to this record; associated records, stock and earlier history are retained.</p>
@if($kind === 'storage_locations' && $record->is_central)
<p>Central must remain the distinguished active storage location.</p>
@else
<form method="post" action="/catalog/{{ $kind }}/{{ $record->id }}/lifecycle">@csrf
<div class="actions">
@if($record->state === 'archived')
<x-app.action type="submit" name="action" value="restore">Restore</x-app.action>
@else
<x-app.action variant="danger" href="/catalog/{{ $kind }}/{{ $record->id }}/archive" data-catalog-archive-trigger aria-haspopup="dialog" aria-controls="catalog-archive-dialog">Archive</x-app.action>
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