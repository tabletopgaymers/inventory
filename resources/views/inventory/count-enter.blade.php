@extends('layout')
@section('title', 'Count entry · '.$location->name)
@section('error-summary')
@foreach($errors->messages() as $key => $messages)
    @php($errorItem = str_starts_with($key, 'counts.') ? collect($rows)->firstWhere('id', (int)substr($key, 7)) : null)
    @foreach($messages as $message)
        <p>@if($errorItem)<a href="#count-{{ $errorItem['id'] }}">{{ $errorItem['name'] }} · {{ $errorItem['sku'] }}: {{ $message }}</a>@else{{ $message }}@endif</p>
    @endforeach
@endforeach
@endsection
@section('content')
<p>Enter actual individual counts. Blank skips an item. Zero explicitly clears its quantity. Counts do not change inventory until reviewed and saved.</p>
<form method="post" action="/inventory/location-counts/{{ $token }}/review" data-unsaved>@csrf
<x-app.table-region label="Count entry" class="table-wrap"><table><thead><tr><th>Item</th><th class="number">Recorded</th><th>Actual count (optional)</th></tr></thead><tbody>@php($group = null)@forelse($rows as $row)@if($group !== $row['category'].' : '.$row['collection'])@php($group = $row['category'].' : '.$row['collection'])<tr><th colspan="3">{{ $group }}</th></tr>@endif<tr><th><label for="count-{{ $row['id'] }}">{{ $row['name'] }} — actual count (optional)<small class="app-item-sku">{{ $row['sku'] }}</small></label></th><td class="number">{{ number_format($row['values']['storage:'.$location->id]) }}</td><td><input id="count-{{ $row['id'] }}" name="counts[{{ $row['id'] }}]" inputmode="numeric" data-count @if($errors->has('counts.'.$row['id']))aria-invalid="true" aria-describedby="count-{{ $row['id'] }}-error"@endif value="{{ old('counts.'.$row['id'], $draft['counts'][$row['id']] ?? '') }}">@error('counts.'.$row['id'])<p id="count-{{ $row['id'] }}-error" class="app-field-error">{{ $message }}</p>@enderror</td></tr>@empty<tr><td colspan="3">No items selected.</td></tr>@endforelse</tbody></table></x-app.table-region><div class="actions"><a class="discard" href="/inventory/location-counts?context={{ $token }}">Cancel</a><button class="primary">Next</button></div></form><script src="/catalog.js" defer></script><script src="/location-counts.js" defer></script>
@endsection
