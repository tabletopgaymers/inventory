@php
    $collectionValue = old('collection_id', $record?->collection_id ?? $selectedCollection?->id);
    $itemCollections = $collections->filter(fn ($entry) => $entry->state === 'active' || $record?->collection_id === $entry->id)
        ->sort(fn ($a, $b) => strcasecmp($a->category_name, $b->category_name) ?: strcasecmp($a->name, $b->name) ?: ($a->id <=> $b->id));
@endphp
<div class="app-columns app-columns-wide app-columns-trim app-columns-fields">
<div>
<label>Name<input name="name" required maxlength="255" value="{{ old('name', $record?->name) }}"></label>
<label>Category<input type="text" readonly data-collection-category value=""></label>
<label>Collection<select name="collection_id" required>
@if(!$record || !$itemCollections->contains('id', $collectionValue))<option value="" @selected(!$itemCollections->contains('id', $collectionValue))>– select –</option>@endif
@foreach($itemCollections->groupBy('category_id') as $group)
<optgroup label="{{ $group->first()->category_name }}">
@foreach($group as $entry)<option value="{{ $entry->id }}" data-prefix="{{ $entry->sku_prefix }}" data-category="{{ $entry->category_name }}" @selected($collectionValue == $entry->id)>{{ $entry->name }}</option>@endforeach
</optgroup>
@endforeach
</select></label>
<label>SKU suffix<span class="app-sku-field"><span data-sku-prefix aria-hidden="true" hidden></span><input name="sku_suffix" required maxlength="50" pattern="[A-Za-z0-9_-]+" value="{{ old('sku_suffix', $record?->sku_suffix) }}"></span></label>
<label>Variety<input data-optional name="variety" maxlength="255" value="{{ old('variety', $metadata?->variety) }}"></label>
</div>
<div>
@if($record)<label>Unit cost<input readonly value="{{ \App\Support\StockNumbers::displayCost($record->unit_cost) }}"></label>
@if(auth()->user()->canAdjustUnitCost())<p class="app-field-help"><a href="{{ $recordBase }}{{ $record->id }}/cost">Adjust unit cost</a></p>@endif
@else<label>Unit cost<input data-optional name="unit_cost" inputmode="decimal" value="{{ old('unit_cost') }}"></label>@endif
@foreach(['in_person_ask'=>'In-person ask','online_ask'=>'Online ask','irs_fmv'=>'IRS Fair Market Value'] as $field=>$fieldLabel)<label>{{ $fieldLabel }}<input data-optional name="{{ $field }}" inputmode="decimal" value="{{ old($field, $metadata?->$field) }}"></label>@endforeach
<label>Bundle type<input data-optional name="bundle_type" @required(old('bundle_quantity', $metadata?->bundle_quantity) !== null && old('bundle_quantity', $metadata?->bundle_quantity) !== '') maxlength="255" value="{{ old('bundle_type', $metadata?->bundle_type) }}"></label>
<label>Bundle quantity<input data-optional name="bundle_quantity" inputmode="numeric" value="{{ old('bundle_quantity', $metadata?->bundle_quantity) }}"></label>
<label>Notes<textarea data-optional name="notes" maxlength="5000">{{ old('notes', $metadata?->notes) }}</textarea></label>
</div>
</div>
