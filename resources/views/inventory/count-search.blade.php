@extends('layout')
@section('title', 'Reconcile')
@section('content-sections')
<div @class(['app-columns app-columns-main-sidebar' => $canCount])>
<section class="app-content app-record-content">
<h2>Search</h2>
<form method="post" action="/inventory/location-counts" data-count-search>@csrf
<fieldset class="app-entry-fieldset" @if($loadedList) disabled inert @endif>
{{-- Personal searches are temporarily hidden while Lists are introduced (UW-320).
@if($saved)<nav aria-label="Personal saved searches">@foreach($saved as $key => $entry)<a href="/inventory/location-counts?saved={{ urlencode($key) }}">{{ $entry['name'] }}</a> @endforeach</nav>@endif
@if($compatibilityNotice)<p class="warning" role="status">{{ $compatibilityNotice }}</p>@endif
--}}
@php
    $submittedSearch = $rows === null || !$criteria ? null : [
        'search' => $criteria['search'], 'collections' => $criteria['collections'], 'columns' => [], 'include_inactive' => false,
        'extras' => ['location_id' => (string) $criteria['location_id'], 'zero_stock_location' => (string) $criteria['location_id'], 'include_active_zero' => (bool) ($criteria['include_active_zero'] ?? false)],
    ];
@endphp
<x-app.inventory-search :definition="$searchDefinition" endpoint="/inventory/location-counts" instance="reconcile-search" :script="false" :form-mode="true" filter-label="Collection filters" :submitted="$submittedSearch" :persistence="$loadedList ? null : ['key' => 'reconcile', 'restore' => $criteria === null && !$errors->any()]">
    <x-slot:leading>
        <select name="location_id" data-is-extra required aria-label="Location"><option value="">Select Location</option>@foreach($locations as $location)<option value="{{ $location->id }}" data-central="{{ $location->is_central ? '1' : '0' }}" @selected(old('location_id', $criteria['location_id'] ?? null) == $location->id)>{{ $location->name }}@if($location->state !== 'active') ({{ $location->state }})@endif</option>@endforeach</select>
    </x-slot:leading>
    <x-slot:extraFilters>
        <input type="hidden" name="zero_stock_location" data-is-extra value="{{ old('location_id', $criteria['location_id'] ?? '') }}">
        <input type="hidden" name="include_active_zero" value="0">
        <label class="app-choice"><input type="checkbox" name="include_active_zero" value="1" data-is-extra @checked(old('include_active_zero', $criteria['include_active_zero'] ?? false))>Include active items with zero stock</label>
    </x-slot:extraFilters>
</x-app.inventory-search>
</fieldset>
</form>
</section>
@if($canCount)
{{-- Retained until the Lists replacement is complete (UW-320).
<label>Personal search name (optional for Search; required to save criteria)<input name="name" maxlength="100" value="{{ old('name') }}"></label><label class="app-choice"><input type="checkbox" name="overwrite" value="1">Overwrite my existing search with this name</label><x-app.action type="submit" name="action" value="save">Save personal criteria</x-app.action>
--}}
<x-app.saved-list-selector
    :lists="$lists"
    :loaded-list="$loadedList"
    load-action="/inventory/location-counts/load-list"
    :reset-url="$loadedList ? ($lastSearch === null ? '/inventory/location-counts' : '/inventory/location-counts?context='.$lastSearch) : null"
    select-id="reconcile-list"
    class="app-columns-fields"
    open
>
@if($loadedList)
<form method="post" action="/inventory/location-counts/{{ $token }}/list-location" data-list-location>
    @csrf
    <x-app.field label="Count location" for="reconcile-list-location" name="list_location_id">
        <select id="reconcile-list-location" name="list_location_id" required>
            <option value="" disabled @selected($listLocation === null)>Select Location</option>
            @foreach($locations as $location)<option value="{{ $location->id }}" @selected(old('list_location_id', $listLocation) == $location->id)>{{ $location->name }}@if($location->state !== 'active') ({{ $location->state }})@endif</option>@endforeach
        </select>
    </x-app.field>
    <noscript><x-app.action type="submit">Apply location</x-app.action></noscript>
</form>
@endif
</x-app.saved-list-selector>
@endif
</div>
@if($rows !== null)
<x-app.table-region label="Count search" class="app-list-table-panel">
<x-slot:header>
    <x-app.heading-row variant="table">
        <h2>{{ $loadedList ? 'List - '.$loadedList['name'] : 'Results' }}</h2>
        <div class="app-inline-group">
            @php($canUseResults = !$loadedList || ($listLocation !== null && $rows !== []))
            @if($loadedList['canEdit'] ?? false)
                <x-app.action href="/catalog/lists/{{ $loadedList['id'] }}/edit?reconcile={{ $token }}">Edit</x-app.action>
            @else
                <x-app.action href="/catalog/lists/create?reconcile={{ $token }}">Create List</x-app.action>
            @endif
            <x-app.action href="/inventory/location-counts/{{ $token }}/worksheet" target="_blank" rel="noopener" :disabled="!$canUseResults">Print worksheet</x-app.action>
            @if($canCount)<x-app.action href="/inventory/location-counts/{{ $token }}/enter" :disabled="!$canUseResults">Reconcile inventory</x-app.action>@endif
        </div>
    </x-app.heading-row>
</x-slot:header>
@php($resultLocation = $loadedList ? $listLocation : $criteria['location_id'])
<table><thead><tr><th>Name</th><th class="number">Recorded at selected location</th></tr></thead><tbody>
@php($group = null)
@forelse($rows as $row)
    @if($group !== $row['category'].' : '.$row['collection'])
        @php($group = $row['category'].' : '.$row['collection'])
        <tr class="collection-heading"><th colspan="2">{{ $group }}</th></tr>
    @endif
    <tr><td class="app-grouped-item">{{ $row['name'] }}</td><td class="number">{{ $resultLocation === null ? '—' : number_format($row['values']['storage:'.$resultLocation] ?? 0) }}</td></tr>
@empty
    <tr><td colspan="2">{{ $loadedList ? 'No items in this list.' : 'No matching items. Change your criteria and select Search.' }}</td></tr>
@endforelse
</tbody></table></x-app.table-region>
@endif
@vite('resources/js/location-counts.js')
@endsection
