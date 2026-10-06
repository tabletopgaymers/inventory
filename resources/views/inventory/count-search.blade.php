@extends('layout')
@section('title', 'Location Counts')
@section('content')
<p>Choose one storage location and criteria. Saved searches contain criteria only; each opening uses current inventory.</p>
@if($saved)<nav aria-label="Personal saved searches">@foreach($saved as $key => $entry)<a href="/inventory/location-counts?saved={{ urlencode($key) }}">{{ $entry['name'] }}</a> @endforeach</nav>@endif
<form method="post" action="/inventory/location-counts">@csrf
<label>Storage location<select name="location_id"><option value="">Choose a storage location</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected(old('location_id', $criteria['location_id'] ?? null) == $location->id)>{{ $location->name }} ({{ $location->state }})</option>@endforeach</select></label>
<label>Search inventory<input name="search" value="{{ old('search', $criteria['search'] ?? '') }}" maxlength="255"></label>
<details><summary>Collection filters</summary><div class="collection-checklist">@foreach($collections as $collection)<label class="checkbox"><input type="checkbox" name="collections[]" value="{{ $collection->id }}" @checked(in_array($collection->id, old('collections', $errors->any() ? [] : ($criteria['collections'] ?? []))))>{{ $collection->category_name }} : {{ $collection->name }}</label>@endforeach</div><label class="checkbox"><input type="checkbox" name="include_inactive" value="1" @checked(old('include_inactive', $criteria['include_inactive'] ?? false))>Include inactive zero-stock items</label><button type="button" data-clear-count-filters>Clear filters</button></details>
<div class="actions"><button name="action" value="search" class="primary">Search</button><button name="action" value="show_all">Show All</button><button name="action" value="reset" formnovalidate>Reset</button></div>
<label>Personal search name<input name="name" maxlength="100" value="{{ old('name') }}"></label><label class="checkbox"><input type="checkbox" name="overwrite" value="1">Overwrite my existing search with this name</label><button name="action" value="save">Save personal criteria</button>
</form>
@if($rows !== null)
<div class="actions"><a href="/inventory/location-counts/{{ $token }}/worksheet" target="_blank" rel="noopener">Print Inventory Worksheet</a>@if($canCount)<a class="button" href="/inventory/location-counts/{{ $token }}/enter">Reconcile Inventory</a>@endif</div>
<div class="table-wrap"><table><thead><tr><th>Name</th><th class="number">Recorded at selected location</th></tr></thead><tbody>@php($group = null)@forelse($rows as $row)@if($group !== $row['category'].' : '.$row['collection'])@php($group = $row['category'].' : '.$row['collection'])<tr><th colspan="2">{{ $group }}</th></tr>@endif<tr><td>{{ $row['name'] }}</td><td class="number">{{ number_format($row['values']['storage:'.$criteria['location_id']]) }}</td></tr>@empty<tr><td colspan="2">No matching items. Change your criteria and select Search.</td></tr>@endforelse</tbody></table></div>
@else<p>Choose a storage location and select Search or Show All.</p>@endif
<script src="/location-counts.js" defer></script>
@endsection
