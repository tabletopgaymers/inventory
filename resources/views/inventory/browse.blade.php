@extends('layout')
@section('title', 'Inventory')
@section('content-sections')
<div class="app-content">
<form method="post" action="/inventory/search" id="inventory-search">@csrf
<input type="hidden" name="context" value="{{ $token }}"><input type="hidden" name="scroll" value="{{ $scroll }}"><input type="hidden" name="destination" value="">
<x-app.inventory-search :definition="$searchDefinition" endpoint="/inventory/search" instance="inventory-main-search" :script="false" :form-mode="true" :submitted="$submitted" :persistence="['key' => 'inventory', 'endpoint' => '/inventory/preferences', 'context' => $token, 'scroll' => $scroll]" />
</form>
@if(collect($allColumns)->contains(fn ($column) => $column['unavailable'] ?? false))<p class="warning" role="status">A saved location selection is unavailable. Your saved criteria are retained; remove the unavailable selection explicitly before searching. Existing results retain their submitted columns.</p>@endif

</div>
<x-app.table-region label="Inventory results" :nowrap="true" class="inventory-results app-list-table-panel" id="inventory-results">
<x-slot:header>
    <x-app.heading-row variant="table">
        <h2>Results</h2>
        @if(!empty($result['rows']))<x-app.action href="/inventory/results/{{ $token }}/csv">Download CSV</x-app.action>@else<x-app.action :disabled="true">Download CSV</x-app.action>@endif
    </x-app.heading-row>
    <p>Available includes every storage location. Event, In Transit and Ordered balances are separate read-only sources; future transaction links are unavailable.</p>
</x-slot:header>
<table><colgroup><col><col class="table-quantity-column">@foreach($result['columns'] ?? [] as $column)<col class="table-quantity-column">@endforeach</colgroup><thead><tr><th>Name</th><th class="table-numeric">Available</th>@foreach($result['columns'] ?? [] as $key=>$column)<th class="table-numeric">@if($column['kind']==='storage')<a data-browse-link href="/inventory/locations/{{ $column['id'] }}">{{ $column['name'] }}</a>@else{{ $column['name'] }}@endif</th>@endforeach</tr></thead><tbody>
@php($group = null)
@forelse($result['rows'] ?? [] as $row)
@if($group !== $row['category'].':'.$row['collection'])@php($group = $row['category'].':'.$row['collection'])<tr class="collection-heading"><th colspan="{{ 2+count($result['columns']) }}">{{ $row['category'] }} : {{ $row['collection'] }}</th></tr>@endif
<tr><td class="app-grouped-item"><a data-browse-link href="/inventory/items/{{ $row['id'] }}">{{ $row['name'] }}</a></td><td class="number">{{ $row['available'] ? number_format($row['available']) : '—' }}</td>@foreach($result['columns'] as $key=>$column)<td class="number">{{ $row['values'][$key] ? number_format($row['values'][$key]) : '—' }}</td>@endforeach</tr>
@empty<tr><td colspan="{{ 2+count($result['columns'] ?? []) }}">{{ $result === null ? 'Enter search text or leave it blank, then select Search.' : 'No items match. Change your search or filters, then select Search.' }}</td></tr>@endforelse
</tbody></table></x-app.table-region>
@vite('resources/js/inventory-browse.js')
@endsection
