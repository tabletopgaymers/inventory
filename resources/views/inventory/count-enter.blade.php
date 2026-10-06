@extends('layout')
@section('title', 'Count entry · '.$location->name)
@section('content')
<p>Enter actual individual counts. Blank skips an item. Zero explicitly clears its quantity. Counts do not change inventory until reviewed and saved.</p>
<form method="post" action="/inventory/location-counts/{{ $token }}/review" data-unsaved>@csrf
<div class="table-wrap"><table><thead><tr><th>Item</th><th class="number">Recorded</th><th>Actual count</th></tr></thead><tbody>@php($group = null)@forelse($rows as $row)@if($group !== $row['category'].' : '.$row['collection'])@php($group = $row['category'].' : '.$row['collection'])<tr><th colspan="3">{{ $group }}</th></tr>@endif<tr><th><label for="count-{{ $row['id'] }}">{{ $row['name'] }}</label></th><td class="number">{{ number_format($row['values']['storage:'.$location->id]) }}</td><td><input id="count-{{ $row['id'] }}" name="counts[{{ $row['id'] }}]" inputmode="numeric" data-count value="{{ old('counts.'.$row['id'], $draft['counts'][$row['id']] ?? '') }}"></td></tr>@empty<tr><td colspan="3">No items selected.</td></tr>@endforelse</tbody></table></div><div class="actions"><a class="discard" href="/inventory/location-counts?context={{ $token }}">Cancel</a><button class="primary">Next</button></div></form><script src="/catalog.js" defer></script><script src="/location-counts.js" defer></script>
@endsection
