@extends('layout')
@section('title', 'Inventory')
@section('content-sections')
<div class="app-columns app-columns-main-sidebar">
<section class="app-content app-record-content">
<h2>Search</h2>
<form method="post" action="/inventory/search" id="inventory-search">@csrf
<input type="hidden" name="context" value="{{ $token }}"><input type="hidden" name="scroll" value="{{ $scroll }}"><input type="hidden" name="destination" value="">
<fieldset class="app-entry-fieldset" @if($loadedList) disabled inert @endif>
<x-app.inventory-search :definition="$searchDefinition" endpoint="/inventory/search" instance="inventory-main-search" :script="false" :form-mode="true" :submitted="$submitted" :persistence="$loadedList ? null : ['key' => 'inventory', 'endpoint' => '/inventory/preferences', 'context' => $token, 'scroll' => $scroll]" />
</fieldset>
</form>
@if(!$loadedList && collect($allColumns)->contains(fn ($column) => $column['unavailable'] ?? false))<p class="warning" role="status">A saved location selection is unavailable. Your saved criteria are retained; remove the unavailable selection explicitly before searching. Existing results retain their submitted columns.</p>@endif
</section>
<x-app.saved-list-selector
    :lists="$lists"
    :loaded-list="$loadedList"
    load-action="/inventory/load-list"
    :reset-url="$loadedList ? ($previousSearch === null ? '/inventory' : '/inventory/results/'.$previousSearch) : null"
    select-id="inventory-list"
    class="app-columns-fields"
    data-inventory-list-load
    open
>
    <x-slot:loadFields>
        <input type="hidden" name="browse" value="{{ $token }}">
    </x-slot:loadFields>
</x-app.saved-list-selector>
</div>
@if($result !== null)
<x-app.table-region label="Inventory results" :nowrap="true" class="inventory-results app-list-table-panel" id="inventory-results" :data-inventory-list-results="$loadedList ? '' : null">
<x-slot:header>
    <x-app.heading-row variant="table">
        <h2>{{ $loadedList ? $loadedList['name'].' (list)' : 'Search Results' }}</h2>
        <div class="app-inline-group">
            @if($canEditList)
                <x-app.action href="/catalog/lists/{{ $loadedList['id'] }}/edit?browse={{ $token }}">Edit list</x-app.action>
            @elseif($loadedList || !empty($result['rows']))
                <x-app.action href="/catalog/lists/create?browse={{ $token }}">{{ $loadedList ? 'Copy list' : 'Create list' }}</x-app.action>
            @endif
            @if(!empty($result['rows']))<x-app.action href="/inventory/results/{{ $token }}/csv">Download CSV</x-app.action>@else<x-app.action :disabled="true">Download CSV</x-app.action>@endif
        </div>
    </x-app.heading-row>
</x-slot:header>
<table><colgroup><col><col class="table-quantity-column">@foreach($result['columns'] ?? [] as $column)<col class="table-quantity-column">@endforeach</colgroup><thead><tr><th>Name</th><th class="table-numeric">Total</th>@foreach($result['columns'] ?? [] as $key=>$column)<th class="table-numeric">@if($column['kind']==='storage')<a @unless($loadedList) data-browse-link @endunless href="/inventory/locations/{{ $column['id'] }}?browse={{ $token }}">{{ $column['name'] }}</a>@else{{ $column['name'] }}@endif</th>@endforeach</tr></thead><tbody>
@php($group = null)
@forelse($result['rows'] ?? [] as $row)
@if($group !== $row['category'].':'.$row['collection'])@php($group = $row['category'].':'.$row['collection'])<tr class="collection-heading"><th colspan="{{ 2+count($result['columns']) }}">{{ $row['category'] }} : {{ $row['collection'] }}</th></tr>@endif
<tr><td class="app-grouped-item"><a @unless($loadedList) data-browse-link @endunless href="/inventory/items/{{ $row['id'] }}?browse={{ $token }}">{{ $row['name'] }}</a></td><td class="number">{{ $row['available'] ? number_format($row['available']) : '—' }}</td>@foreach($result['columns'] as $key=>$column)<td class="number">{{ $row['values'][$key] ? number_format($row['values'][$key]) : '—' }}</td>@endforeach</tr>
@empty<tr><td colspan="{{ 2+count($result['columns']) }}">{{ $loadedList ? 'No items in this list.' : 'No items match. Change your search or filters, then select Search.' }}</td></tr>@endforelse
</tbody></table>
<x-slot:footer>
    <p>Total includes all storage locations. Event, In Transit and Ordered balances are separate.</p>
</x-slot:footer>
</x-app.table-region>
@endif
@vite('resources/js/inventory-browse.js')
@endsection
