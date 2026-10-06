@extends('layout')
@section('title', 'Cost Adjustment · '.$entry->item_name)
@section('content')
<p>{{ $entry->item_sku }} · Recorded by {{ $entry->actor_name }}</p><p>{{ \Carbon\CarbonImmutable::parse($entry->posted_at, 'UTC')->setTimezone('America/Chicago')->format('M j, Y H:i:s T').' (America/Chicago)' }}</p><dl><dt>Previous Unit Cost</dt><dd>{{ \App\Support\StockNumbers::displayCost($entry->before_cost) }}</dd><dt>New Unit Cost</dt><dd>{{ \App\Support\StockNumbers::displayCost($entry->after_cost) }}</dd><dt>Quantity change</dt><dd>—</dd><dt>Rationale</dt><dd>{{ $entry->rationale ?? '—' }}</dd></dl><p><a href="/inventory/items/{{ $entry->item_id }}#history">Item History</a></p>
@endsection
