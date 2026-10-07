@extends('layout')
@section('title', $row->name.' — Final report')
@section('title-status')<span class="status-badge request-status received">{{ $data['corrected'] ? 'Finalized · Corrected report' : 'Finalized' }}</span>@endsection
@section('content')
<link rel="stylesheet" href="/event-report.css">
<p>Finalized {{ \App\Support\DisplayDates::timestamp($data['finalized_at']) }}.</p>
@if($data['corrected'])<p class="warning">Corrected report totals. Original stock posting is unchanged; correction logs are on the event.</p>@endif
<div class="actions event-report-actions"><a href="/events/{{ $row->id }}">Back to event and correction log</a><a href="?all={{ $all ? 0 : 1 }}">{{ $all ? 'Hide zero-distribution items' : 'Include all event items' }}</a><a href="?format=csv&amp;all={{ $all ? 1 : 0 }}">Download CSV</a><button type="button" data-print>Print report</button></div>
<x-app.table-region label="Final event distribution report" class="table-wrap"><table><thead><tr><th>Category</th><th>Collection</th><th>Item</th><th>SKU</th><th class="number">Distributed</th></tr></thead><tbody>
@forelse($report as $line)<tr><td>{{ $line['category'] }}</td><td>{{ $line['collection'] }}</td><th>{{ $line['item'] }}</th><td>{{ $line['sku'] }}</td><td class="number">{{ number_format($line['distributed']) }}</td></tr>@empty<tr><td colspan="5">No nonzero distribution. Choose Include all event items to show every row.</td></tr>@endforelse
</tbody></table></x-app.table-region><script src="/requests.js" defer></script>
@endsection
