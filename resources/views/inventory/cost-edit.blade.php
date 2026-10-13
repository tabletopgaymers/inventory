@extends('layout')
@section('title', 'Adjust unit cost · '.$item->name)
@section('content')
<p>Current unit cost: {{ \App\Support\StockNumbers::displayCost($item->unit_cost) }}. Explicit zero means unknown. This changes cost only and preserves all quantities and earlier history.</p><form method="post" data-unsaved>@csrf<input type="hidden" name="operation" value="{{ old('operation', $token) }}"><label>Unit cost<input name="unit_cost" required inputmode="decimal" value="{{ old('unit_cost', $item->unit_cost) }}"></label><label>Rationale<input data-optional name="rationale" maxlength="1000" value="{{ old('rationale') }}"></label><div class="actions"><a class="discard" href="{{ $itemUrl }}">Cancel</a><button class="primary">Save cost</button></div></form><script src="/catalog.js?v=inline-decisions-1" defer></script>
@endsection
