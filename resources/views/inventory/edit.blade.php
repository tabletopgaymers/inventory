@extends('layout')
@section('title', 'Edit Inventory — '.$item->name)
@section('content')
<p>New = Set + Adjust. Set is required; blank Adjust means zero. Only Save on the next page records changes.</p>
<form method="post" action="/inventory/items/{{ $item->id }}/preview" data-stock-editor data-dirty-warning 
@if($draft['reviewed'])data-pending-draft
@endif>
@csrf<input type="hidden" name="_deliberate" value="1"><input type="hidden" name="operation" value="{{ $token }}">
<div class="table-wrap"><table class="stock-editor"><thead><tr><th>Location</th><th>Set</th><th>Adjust</th><th>Rationale (optional)</th><th class="number">Current</th><th class="number">New</th></tr></thead><tbody>

@foreach($locations as $location)
<tr data-stock-row><th>{{ $location->name }}</th>
<td><label class="sr-only" for="set-{{ $location->id }}">Set {{ $location->name }}</label><input id="set-{{ $location->id }}" name="rows[{{ $location->id }}][set]" value="{{ old('rows.'.$location->id.'.set', $draft['rows'][$location->id]['set'] ?? $location->quantity) }}" data-set type="number" step="1" min="-1000000000" max="1000000000" required></td>
<td><label class="sr-only" for="adjust-{{ $location->id }}">Adjust {{ $location->name }}</label><input id="adjust-{{ $location->id }}" name="rows[{{ $location->id }}][adjust]" value="{{ old('rows.'.$location->id.'.adjust', $draft['rows'][$location->id]['adjust'] ?? '0') }}" data-adjust type="number" step="1" min="-1000000000" max="1000000000"></td>
<td><label class="sr-only" for="rationale-{{ $location->id }}">Rationale {{ $location->name }}</label><input id="rationale-{{ $location->id }}" name="rows[{{ $location->id }}][rationale]" value="{{ old('rows.'.$location->id.'.rationale', $draft['rows'][$location->id]['rationale'] ?? '') }}" maxlength="1000"></td>
<td class="number">{{ number_format($location->quantity) }}</td><td class="number"><span data-new aria-live="polite"></span></td></tr>

@endforeach
</tbody></table></div>
<div class="stock-total"><h3>Total Available</h3><span aria-label="Current total">{{ number_format($locations->sum('quantity')) }}</span><span data-new-total aria-label="New total" aria-live="polite"></span></div>
<div class="actions"><a class="discard" href="/inventory/items/{{ $item->id }}" data-discard>Cancel</a><button class="primary">Continue</button></div>
</form><script src="/inventory.js" defer></script>

@endsection
