@php
    $itemCollections = $collections->filter(fn ($entry) => $entry->state === 'active' || $record?->collection_id === $entry->id)
        ->sort(fn ($a, $b) => strcasecmp($a->category_name, $b->category_name) ?: strcasecmp($a->name, $b->name) ?: ($a->id <=> $b->id));
@endphp
<div class="app-catalog-item-columns">
<div>
<label>Name<input name="name" required maxlength="255" value="{{ old('name', $record?->name) }}"></label>
<label>Category<input type="text" readonly data-collection-category value=""></label>
<label>Collection<select name="collection_id" required>
@if(!$record || !$itemCollections->contains('id', old('collection_id', $record?->collection_id)))<option value="" @selected(!$itemCollections->contains('id', old('collection_id', $record?->collection_id)))>– select –</option>@endif
@foreach($itemCollections->groupBy('category_id') as $group)
<optgroup label="{{ $group->first()->category_name }}">
@foreach($group as $entry)<option value="{{ $entry->id }}" data-prefix="{{ $entry->sku_prefix }}" data-category="{{ $entry->category_name }}" @selected(old('collection_id', $record?->collection_id) == $entry->id)>{{ $entry->name }}</option>@endforeach
</optgroup>
@endforeach
</select></label>
<label>SKU suffix<span class="app-sku-field"><span data-sku-prefix aria-hidden="true" hidden></span><input name="sku_suffix" required maxlength="50" pattern="[A-Za-z0-9_-]+" value="{{ old('sku_suffix', $record?->sku_suffix) }}"></span></label>
<label>Variety<input data-optional name="variety" maxlength="255" value="{{ old('variety', $metadata?->variety) }}"></label>
<label>Purpose<select data-optional name="purpose_id"><option value="">None</option>@foreach($purposes as $purpose)@if($purpose->state === 'active' || ($record && $metadata?->purpose_id === $purpose->id))<option value="{{ $purpose->id }}" @selected(old('purpose_id', $metadata?->purpose_id) == $purpose->id)>{{ $purpose->name }}</option>@endif @endforeach</select></label>
<fieldset data-optional><legend>Programs</legend>@foreach($programs as $program)@if($program->state === 'active' || ($record && in_array($program->id, $selectedPrograms)))<label class="app-choice"><input type="checkbox" name="programs[]" value="{{ $program->id }}" @checked(in_array($program->id, old('programs', $errors->any() ? [] : $selectedPrograms)))>{{ $program->name }}</label>@endif @endforeach</fieldset>
</div>
<div>
@if($record)<label>Unit cost<input readonly value="{{ \App\Support\StockNumbers::displayCost($record->unit_cost) }}"></label>
@if(auth()->user()->hasRole('admin'))<p class="app-field-help"><a href="{{ $recordBase }}{{ $record->id }}/cost">Adjust unit cost</a></p>@endif
@else<label>Unit cost<input data-optional name="unit_cost" inputmode="decimal" value="{{ old('unit_cost') }}"></label>@endif
@foreach(['in_person_ask'=>'In-person ask','online_ask'=>'Online ask','irs_fmv'=>'IRS Fair Market Value'] as $field=>$fieldLabel)<label>{{ $fieldLabel }}<input data-optional name="{{ $field }}" inputmode="decimal" value="{{ old($field, $metadata?->$field) }}"></label>@endforeach
<label>Bundle type<input data-optional name="bundle_type" @required(old('bundle_quantity', $metadata?->bundle_quantity) !== null && old('bundle_quantity', $metadata?->bundle_quantity) !== '') maxlength="255" value="{{ old('bundle_type', $metadata?->bundle_type) }}"></label>
<label>Bundle quantity<input data-optional name="bundle_quantity" inputmode="numeric" value="{{ old('bundle_quantity', $metadata?->bundle_quantity) }}"></label>
<label>Notes<textarea data-optional name="notes" maxlength="5000">{{ old('notes', $metadata?->notes) }}</textarea></label>
</div>
</div>
