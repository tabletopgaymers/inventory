@extends('layout')
@section('title', 'Inventory')
@section('content')
<form method="post" action="/inventory/search" id="inventory-search">@csrf
<input type="hidden" name="context" value="{{ $token }}"><input type="hidden" name="scroll" value="{{ $scroll }}"><input type="hidden" name="destination" value="">
<div class="search-actions"><label>Search inventory<input name="search" maxlength="255" value="{{ old('search', $pending['search']) }}"></label><button name="action" value="search" class="primary" aria-busy="false">Search<span class="app-search-spinner" aria-hidden="true"></span></button><span class="sr-only" role="status" data-search-status></span><button name="action" value="show_all">Show All</button><button name="action" value="reset">Reset</button></div>
<details id="inventory-filters"><summary>Filters <span id="filter-summary"></span></summary>
<fieldset><legend>Collections</legend><div class="collection-checklist">@foreach($collections as $collection)<label class="app-choice"><input type="checkbox" name="collections[]" value="{{ $collection->id }}" @checked(in_array($collection->id, old('collections', $pending['collections'])))>{{ $collection->category_name }} : {{ $collection->name }}</label>@endforeach</div></fieldset>
<div class="location-filters"><fieldset><legend>Locations</legend>@foreach($allColumns as $key=>$column)@if($column['kind']==='storage')<label class="app-choice"><input type="checkbox" name="columns[]" value="{{ $key }}" @checked(in_array($key, old('columns', $pending['columns'])))>{{ $column['name'] }}@if($column['unavailable'] ?? false) (unavailable — remove this saved selection before searching)@endif @if($column['state'] !== 'active') ({{ $column['state'] }})@endif</label>@endif @endforeach</fieldset>
<fieldset><legend>Events, In Transit and Ordered</legend>@foreach($allColumns as $key=>$column)@if($column['kind']!=='storage')<label class="app-choice"><input type="checkbox" name="columns[]" value="{{ $key }}" @checked(in_array($key, old('columns', $pending['columns'])))>{{ $column['name'] }}</label>@endif @endforeach</fieldset></div>
<label class="app-choice"><input type="checkbox" name="include_inactive" value="1" @checked(old('include_inactive', $pending['include_inactive']))>Include inactive zero-stock items</label><button type="button" id="clear-filters">Clear filters</button>
</details></form>
@if(collect($allColumns)->contains(fn ($column) => $column['unavailable'] ?? false))<p class="warning" role="status">A saved location selection is unavailable. Your saved criteria are retained; remove the unavailable selection explicitly before searching. Existing results retain their submitted columns.</p>@endif
<p id="pending-notice" class="warning" hidden>Search settings changed — select Search to update results.</p><p id="preference-error" class="error" role="alert" hidden></p>
<p>Available includes every storage location. Event, In Transit and Ordered balances are separate read-only sources; future transaction links are unavailable.</p>
<x-app.table-region label="Inventory results" class="table-wrap inventory-results" id="inventory-results"><table><thead><tr><th>Name</th><th class="number">Available</th>@foreach($result['columns'] ?? [] as $key=>$column)<th class="number">@if($column['kind']==='storage')<a data-browse-link href="/inventory/locations/{{ $column['id'] }}">{{ $column['name'] }}</a>@else{{ $column['name'] }}@endif</th>@endforeach</tr></thead><tbody>
@php($group = null)
@forelse($result['rows'] ?? [] as $row)
@if($group !== $row['category'].':'.$row['collection'])@php($group = $row['category'].':'.$row['collection'])<tr class="collection-heading"><th colspan="{{ 2+count($result['columns']) }}">{{ $row['category'] }} : {{ $row['collection'] }}</th></tr>@endif
<tr><th><a data-browse-link href="/inventory/items/{{ $row['id'] }}">{{ $row['name'] }}</a></th><td class="number">{{ $row['available'] ? number_format($row['available']) : '—' }}</td>@foreach($result['columns'] as $key=>$column)<td class="number">{{ $row['values'][$key] ? number_format($row['values'][$key]) : '—' }}</td>@endforeach</tr>
@empty<tr><td colspan="{{ 2+count($result['columns'] ?? []) }}">{{ $result === null ? 'Search inventory or choose Show All.' : 'No items match. Change your search or filters, then select Search, or choose Show All.' }}</td></tr>@endforelse
</tbody></table></x-app.table-region>
<div class="actions download-bar">@if(!empty($result['rows']))<a class="button" href="/inventory/results/{{ $token }}/csv">Download CSV</a>@else<button disabled>Download CSV</button>@endif</div>
<script type="application/json" id="browse-state">@json(['submitted'=>$submitted,'scroll'=>$scroll])</script><script src="/inventory-browse.js" defer></script>
@endsection
