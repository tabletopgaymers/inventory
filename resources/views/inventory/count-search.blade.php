@extends('layout')
@section('title', 'Location Counts')
@section('content')
<p>Choose one location and criteria. Saved searches contain criteria only; each opening uses current inventory.</p>
@if($saved)<nav aria-label="Personal saved searches">@foreach($saved as $key => $entry)<a href="/inventory/location-counts?saved={{ urlencode($key) }}">{{ $entry['name'] }}</a> @endforeach</nav>@endif
@if($compatibilityNotice)<p class="warning" role="status">{{ $compatibilityNotice }}</p>@endif
<form method="post" action="/inventory/location-counts" data-count-search>@csrf
<div class="app-count-criteria"><label>Location<select name="location_id" required><option value="">Choose a location</option>@foreach($locations as $location)<option value="{{ $location->id }}" data-central="{{ $location->is_central ? '1' : '0' }}" @selected(old('location_id', $criteria['location_id'] ?? null) == $location->id)>{{ $location->name }}@if($location->state !== 'active') ({{ $location->state }})@endif</option>@endforeach</select></label>
<label>Search<input name="search" value="{{ old('search', $criteria['search'] ?? '') }}" maxlength="255"></label>
<div class="actions"><button name="action" value="search" class="primary" aria-busy="false">Search<span class="app-search-spinner" aria-hidden="true"></span></button><span class="sr-only" role="status" data-search-status></span><button name="action" value="show_all">Show All</button><button name="action" value="reset" formnovalidate>Reset</button></div></div>
<details><summary>Collection filters</summary><div class="collection-checklist">@foreach($collections as $collection)<label class="app-choice"><input type="checkbox" name="collections[]" value="{{ $collection->id }}" @checked(in_array($collection->id, old('collections', $errors->any() ? [] : ($criteria['collections'] ?? []))))>{{ $collection->category_name }} : {{ $collection->name }}</label>@endforeach</div><label class="app-choice"><input type="hidden" name="zero_stock_location" value="{{ old('location_id', $criteria['location_id'] ?? '') }}"><input type="hidden" name="include_active_zero" value="0"><input type="checkbox" name="include_active_zero" value="1" @checked(old('include_active_zero', $criteria['include_active_zero'] ?? false))>Include active items with zero stock</label><button type="button" data-clear-count-filters>Clear filters</button></details>

<label>Personal search name (optional for Search; required to save criteria)<input name="name" maxlength="100" value="{{ old('name') }}"></label><label class="app-choice"><input type="checkbox" name="overwrite" value="1">Overwrite my existing search with this name</label><button name="action" value="save">Save personal criteria</button>
</form>
@if($rows !== null)
<div class="actions"><a href="/inventory/location-counts/{{ $token }}/worksheet" target="_blank" rel="noopener">Print Inventory Worksheet</a>@if($canCount)<a class="button" href="/inventory/location-counts/{{ $token }}/enter">Reconcile Inventory</a>@endif</div>
<x-app.table-region label="Count search" class="table-wrap"><table><thead><tr><th>Name</th><th class="number">Recorded at selected location</th></tr></thead><tbody>@php($group = null)@forelse($rows as $row)@if($group !== $row['category'].' : '.$row['collection'])@php($group = $row['category'].' : '.$row['collection'])<tr><th colspan="2">{{ $group }}</th></tr>@endif<tr><td>{{ $row['name'] }}</td><td class="number">{{ number_format($row['values']['storage:'.$criteria['location_id']]) }}</td></tr>@empty<tr><td colspan="2">No matching items. Change your criteria and select Search.</td></tr>@endforelse</tbody></table></x-app.table-region>
@else<p>Choose a location and select Search or Show All.</p>@endif
<script src="/location-counts.js" defer></script>
@endsection
