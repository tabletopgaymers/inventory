@extends('layout')
@section('title', 'Reconcile')
@section('content-sections')
<form method="post" action="/inventory/location-counts" class="app-columns app-columns-main-sidebar" data-count-search>@csrf
<section class="app-content app-record-content">
{{-- Personal searches are temporarily hidden while Lists are introduced (UW-320).
@if($saved)<nav aria-label="Personal saved searches">@foreach($saved as $key => $entry)<a href="/inventory/location-counts?saved={{ urlencode($key) }}">{{ $entry['name'] }}</a> @endforeach</nav>@endif
@if($compatibilityNotice)<p class="warning" role="status">{{ $compatibilityNotice }}</p>@endif
--}}
@php
    $submittedSearch = $rows === null ? null : [
        'search' => $criteria['search'], 'collections' => $criteria['collections'], 'columns' => [], 'include_inactive' => false,
        'extras' => ['location_id' => (string) $criteria['location_id'], 'zero_stock_location' => (string) $criteria['location_id'], 'include_active_zero' => (bool) ($criteria['include_active_zero'] ?? false)],
    ];
@endphp
<x-app.inventory-search :definition="$searchDefinition" endpoint="/inventory/location-counts" instance="reconcile-search" :script="false" :form-mode="true" filter-label="Collection filters" :submitted="$submittedSearch" :persistence="['key' => 'reconcile', 'restore' => $criteria === null && !$errors->any()]">
    <x-slot:leading>
        <select name="location_id" data-is-extra required aria-label="Location"><option value="">Select Location</option>@foreach($locations as $location)<option value="{{ $location->id }}" data-central="{{ $location->is_central ? '1' : '0' }}" @selected(old('location_id', $criteria['location_id'] ?? null) == $location->id)>{{ $location->name }}@if($location->state !== 'active') ({{ $location->state }})@endif</option>@endforeach</select>
    </x-slot:leading>
    <x-slot:extraFilters>
        <input type="hidden" name="zero_stock_location" data-is-extra value="{{ old('location_id', $criteria['location_id'] ?? '') }}">
        <input type="hidden" name="include_active_zero" value="0">
        <label class="app-choice"><input type="checkbox" name="include_active_zero" value="1" data-is-extra @checked(old('include_active_zero', $criteria['include_active_zero'] ?? false))>Include active items with zero stock</label>
    </x-slot:extraFilters>
</x-app.inventory-search>

</section>
<section class="app-content app-record-content app-columns-fields">
{{-- Retained until the Lists replacement is complete (UW-320).
<label>Personal search name (optional for Search; required to save criteria)<input name="name" maxlength="100" value="{{ old('name') }}"></label><label class="app-choice"><input type="checkbox" name="overwrite" value="1">Overwrite my existing search with this name</label><x-app.action type="submit" name="action" value="save">Save personal criteria</x-app.action>
--}}
<x-app.field label="List" for="reconcile-list">
    <select id="reconcile-list">
        <option value="">Select a list</option>
        @foreach($lists as $list)<option value="{{ $list->id }}">{{ $list->name }}</option>@endforeach
    </select>
</x-app.field>
<div class="actions">
    <x-app.action type="button">Merge</x-app.action>
    <x-app.action type="button">Replace</x-app.action>
</div>
</section>
</form>
@if($rows !== null)
<x-app.table-region label="Count search" class="app-list-table-panel">
<x-slot:header>
    <x-app.heading-row variant="table">
        <h2>Results</h2>
        <div class="app-inline-group">
            <x-app.action href="/inventory/location-counts/{{ $token }}/worksheet" target="_blank" rel="noopener">Print worksheet</x-app.action>
            @if($canCount)<x-app.action href="/inventory/location-counts/{{ $token }}/enter">Reconcile inventory</x-app.action>@endif
        </div>
    </x-app.heading-row>
</x-slot:header>
<table><thead><tr><th>Name</th><th class="number">Recorded at selected location</th></tr></thead><tbody>@php($group = null)@forelse($rows as $row)@if($group !== $row['category'].' : '.$row['collection'])@php($group = $row['category'].' : '.$row['collection'])<tr class="collection-heading"><th colspan="2">{{ $group }}</th></tr>@endif<tr><td class="app-grouped-item">{{ $row['name'] }}</td><td class="number">{{ number_format($row['values']['storage:'.$criteria['location_id']]) }}</td></tr>@empty<tr><td colspan="2">No matching items. Change your criteria and select Search.</td></tr>@endforelse</tbody></table></x-app.table-region>
@endif
@vite('resources/js/location-counts.js')
@endsection
