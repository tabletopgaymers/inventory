@extends('layout')
@section('title', 'Adjust Unit Cost · '.$item->name)
@section('content')
<p>Current Unit Cost: {{ \App\Support\StockNumbers::displayCost($item->unit_cost) }}. Explicit zero means unknown. This changes cost only and preserves all quantities and earlier history.</p><form method="post" data-unsaved>@csrf<input type="hidden" name="operation" value="{{ old('operation', $token) }}"><label>Unit Cost<input name="unit_cost" required inputmode="decimal" value="{{ old('unit_cost', $item->unit_cost) }}"></label><label>Rationale (optional)<input name="rationale" maxlength="1000" value="{{ old('rationale') }}"></label><div class="actions"><a class="discard" href="/inventory/items/{{ $item->id }}">Cancel</a><button class="primary">Save Cost</button></div></form><script src="/catalog.js" defer></script>
@endsection
