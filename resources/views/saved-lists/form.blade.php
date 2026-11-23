@extends('layout')
@section('title', $list ? 'Edit list' : 'New list')
@section('page-actions')
<x-app.action href="{{ $reconcileContext !== null ? '/inventory/location-counts?context='.$reconcileContext : ($list ? '/catalog/lists/'.$list->id : '/catalog/lists') }}">Discard</x-app.action>
<x-app.action type="submit" variant="primary" form="saved-list-form" data-list-save disabled>Save</x-app.action>
@endsection
@section('content-sections')
@vite(['resources/css/item-list-picker.css', 'resources/js/saved-lists.js'])
@php
    $saveUrl = $list ? '/catalog/lists/'.$list->id.'/edit' : '/catalog/lists/create';
    if ($reconcileContext !== null) {
        $saveUrl .= '?reconcile='.$reconcileContext;
    }
@endphp
<form id="saved-list-form" class="app-panel-stack" method="post" action="{{ $saveUrl }}">
@csrf
<input type="hidden" name="item_ids_json" value="{{ old('item_ids_json', json_encode($itemIds)) }}">
<input type="hidden" name="picker_ready" value="0">
@if($list)<input type="hidden" name="version" value="{{ old('version', $list->getRawOriginal('updated_at')) }}">@endif
<x-app.collapsible-panel title="List information" :open="!$list">
<div class="app-columns app-columns-two app-columns-fields">
    <div>
        <x-app.field label="Name" for="list-name" name="name"><input id="list-name" name="name" autocomplete="off" data-1p-ignore @if(!$list) autofocus @endif required maxlength="255" value="{{ old('name', $name) }}"></x-app.field>
        <x-app.field label="Location (optional)" for="list-location" name="storage_location_id" help="The default location when using this list. It can be used at other locations.">
            <select id="list-location" name="storage_location_id"><option value="">No default location</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected(old('storage_location_id', $locationId) == $location->id)>{{ $location->name }}</option>@endforeach</select>
        </x-app.field>
        <x-app.field label="Status" for="list-status" name="status"><select id="list-status" name="status">@foreach(\App\Models\SavedList::STATUSES as $value => $label)<option value="{{ $value }}" @selected(old('status', $list?->status ?? 'active') === $value)>{{ $label }}</option>@endforeach</select></x-app.field>
        @if(auth()->user()->hasRole('admin'))
        <input type="hidden" name="shared" value="0">
        <label class="app-choice"><input type="checkbox" name="shared" value="1" @checked(old('shared', $list?->shared ?? false))> Shared</label>
        @endif
    </div>
    <div>
        <x-app.field label="Description (optional)" for="list-description" name="description"><textarea id="list-description" name="description" rows="4" maxlength="5000">{{ old('description', $list?->description) }}</textarea></x-app.field>
        <x-app.detail-list>
            <dt>Owner</dt><dd>{{ $owner->displayName() }}</dd>
            <dt>Updated</dt><dd>{{ $list ? \App\Support\DisplayDates::date($list->updated_at) : 'Set when created' }}</dd>
        </x-app.detail-list>
    </div>
</div>
</x-app.collapsible-panel>
<x-app.item-list-picker endpoint="/catalog/lists/data" :show-location-selector="false" :show-inventory="false" />
<noscript><p class="error">JavaScript is required to select and save list items.</p></noscript>
</form>
@endsection
