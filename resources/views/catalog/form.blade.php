@extends('layout')
@section('title', ($record ? 'Edit ' : 'Create ').$label)
@section('title-status')
@if($record)<x-app.status-badge :status="ucfirst($record->state)" />@endif
@endsection
@section('content')
<nav class="app-breadcrumb" aria-label="Breadcrumb"><a href="/catalog/{{ $kind }}">{{ $label }}</a>@if($record)<span>› {{ $record->name }}</span>@endif</nav>
<form method="post" data-unsaved>@csrf
<label>Name<input name="name" required maxlength="255" value="{{ old('name', $record?->name) }}"></label>
@if($kind === 'collections')
<label>Category<select name="category_id" required>@foreach($categories as $category)@if($category->state === 'active' || $record?->category_id === $category->id)<option value="{{ $category->id }}" @selected(old('category_id', $record?->category_id) == $category->id)>{{ $category->name }} ({{ $category->state }})</option>@endif @endforeach</select></label>
<label>SKU prefix<input name="sku_prefix" required maxlength="50" pattern="[A-Za-z0-9_-]+" value="{{ old('sku_prefix', $record?->sku_prefix) }}" @if($record && $record->child_count)readonly @endif></label>
@endif
@if($kind === 'items')
<label>Collection<select name="collection_id" required>@foreach($collections as $collection)@if($collection->state === 'active' || $record?->collection_id === $collection->id)<option value="{{ $collection->id }}" data-prefix="{{ $collection->sku_prefix }}" @selected(old('collection_id', $record?->collection_id) == $collection->id)>{{ $collection->category_name }} : {{ $collection->name }} ({{ $collection->state }})</option>@endif @endforeach</select></label>
<label>SKU suffix<input name="sku_suffix" required maxlength="50" pattern="[A-Za-z0-9_-]+" value="{{ old('sku_suffix', $record?->sku_suffix) }}"></label><p>SKU preview: <output id="sku-preview"></output></p>
<label>Variety (optional)<input name="variety" maxlength="255" value="{{ old('variety', $metadata?->variety) }}"></label>
<label>Purpose (optional)<select name="purpose_id"><option value="">None</option>@foreach($purposes as $purpose)@if($purpose->state === 'active' || $metadata?->purpose_id === $purpose->id)<option value="{{ $purpose->id }}" @selected(old('purpose_id', $metadata?->purpose_id) == $purpose->id)>{{ $purpose->name }} ({{ $purpose->state }})</option>@endif @endforeach</select></label>
<fieldset><legend>Programs (optional)</legend>@foreach($programs as $program)@if($program->state === 'active' || in_array($program->id, $selectedPrograms))<label class="app-choice"><input type="checkbox" name="programs[]" value="{{ $program->id }}" @checked(in_array($program->id, old('programs', $errors->any() ? [] : $selectedPrograms)))>{{ $program->name }} ({{ $program->state }})</label>@endif @endforeach</fieldset>
<label>Request bundle type<span data-bundle-optional @if(old('bundle_quantity', $metadata?->bundle_quantity) !== null && old('bundle_quantity', $metadata?->bundle_quantity) !== '')hidden @endif> (optional unless quantity supplied)</span><input name="bundle_type" @required(old('bundle_quantity', $metadata?->bundle_quantity) !== null && old('bundle_quantity', $metadata?->bundle_quantity) !== '') maxlength="255" value="{{ old('bundle_type', $metadata?->bundle_type) }}"></label><label>Bundle quantity (individual units) (optional)<input name="bundle_quantity" inputmode="numeric" value="{{ old('bundle_quantity', $metadata?->bundle_quantity) }}"></label>
<p>Bundle values are hints; they do not enforce multiples or create kits.</p>
@foreach(['irs_fmv'=>'IRS FMV','in_person_ask'=>'In-Person Ask','online_ask'=>'Online Ask'] as $field=>$label)<label>{{ $label }} (optional)<input name="{{ $field }}" inputmode="decimal" value="{{ old($field, $metadata?->$field) }}"></label>@endforeach
<p>Optional monetary fields preserve blank and explicit zero. New items start with zero stock and unknown Unit Cost.</p>
@if($record)<label>Current Unit Cost<input disabled value="{{ \App\Support\StockNumbers::displayCost($record->unit_cost) }}"></label>@if(auth()->user()->hasRole('admin'))<p><a href="/inventory/items/{{ $record->id }}/cost">Adjust Unit Cost with immutable history</a></p>@endif @endif
@endif
@if(in_array($kind, ['categories','collections','storage_locations']))<label>Description (optional)<textarea name="description" maxlength="5000">{{ old('description', $record?->description) }}</textarea></label>@endif
@if($kind === 'suppliers')@foreach(['contact_name'=>'Contact name','email'=>'Email','phone'=>'Phone','website'=>'Website'] as $field=>$label)<label>{{ $label }} (optional)<input name="{{ $field }}" maxlength="255" value="{{ old($field, $record?->$field) }}"></label>@endforeach<label>Address (optional)<textarea name="address" maxlength="5000">{{ old('address', $record?->address) }}</textarea></label>@endif
@if(in_array($kind,['items','storage_locations','suppliers','purposes','programs']))<label>Notes (optional)<textarea name="notes" maxlength="5000">{{ old('notes', $kind === 'items' ? $metadata?->notes : $record?->notes) }}</textarea></label>@endif
<div class="actions"><a class="discard" href="/catalog/{{ $kind }}">Cancel</a><button class="primary">{{ $record ? 'Save' : 'Create' }}</button></div>
</form>
@if($record)
<x-app.panel heading="Record status">
<p>Status changes apply only to this record; associated records, stock and earlier history are retained.</p>
@if($kind === 'storage_locations' && $record->is_central)<p>Central must remain the distinguished active storage location.</p>
@else
<form method="post" action="/catalog/{{ $kind }}/{{ $record->id }}/lifecycle">@csrf
<div class="actions">
@if($record->state === 'archived')<x-app.action type="submit" name="action" value="restore">Restore to Inactive</x-app.action>
@else<x-app.action variant="danger" href="/catalog/{{ $kind }}/{{ $record->id }}/archive">Archive…</x-app.action><x-app.action type="submit" name="action" value="{{ $record->state === 'active' ? 'inactivate' : 'activate' }}">{{ $record->state === 'active' ? 'Set Inactive' : 'Activate' }}</x-app.action>@endif
</div></form>
@endif
</x-app.panel>
@endif
<script src="/catalog.js" defer></script>
@endsection
