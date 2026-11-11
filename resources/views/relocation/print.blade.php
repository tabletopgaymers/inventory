@php
    $quantityHeaders = $reconciliation ? ['Requested', 'Fulfilled', 'Received'] : ['Requested', 'Fulfilled'];
    $columnCount = count($quantityHeaders) + 2;
@endphp
<x-print.layout :title="$row->title" :document-title="($reconciliation ? 'Reconciliation sheet' : 'Packing sheet').' · '.$row->title">
    <x-print.header :values="['Source'=>$sourceName,'Destination'=>$destinationName,'Requester'=>$requesterName,'Requested'=>$requestedDate ?? '—']" :notes="$row->details ?? ''" />
    <table class="print-material">
    <colgroup><col><col class="print-sku-column">@foreach($quantityHeaders as $heading)<col class="print-quantity-column">@endforeach</colgroup>
    <thead><tr><th scope="col">Item</th><th scope="col" class="print-sku">SKU</th>@foreach($quantityHeaders as $heading)<th scope="col" class="print-quantity-heading">{{ $heading }}</th>@endforeach</tr></thead>
    <tbody>
    @forelse(collect($items)->groupBy(fn ($item) => $item['category'].' : '.$item['collection']) as $group => $groupItems)
        <tr class="print-collection"><td colspan="{{ $columnCount }}"><table>
        <colgroup><col><col class="print-sku-column">@foreach($quantityHeaders as $heading)<col class="print-quantity-column">@endforeach</colgroup>
        <thead><tr class="print-group-row"><th colspan="{{ $columnCount }}">{{ $group }}</th></tr></thead><tbody>
        @foreach($groupItems as $item)
            @php($line = $lines->get($item['id']))
            <tr><td class="print-item">{{ $item['name'] }}</td><td class="print-sku">{{ $item['sku'] }}</td><td class="print-quantity">{{ $line->quantity === null ? '' : number_format($line->quantity) }}</td><td class="print-quantity">{{ $line->fulfillment_quantity === null ? '' : number_format($line->fulfillment_quantity) }}</td>@if($reconciliation)<td class="print-quantity">{{ ($received[$item['id']] ?? null) === null ? '' : number_format($received[$item['id']]) }}</td>@endif</tr>
        @endforeach
        </tbody></table></td></tr>
    @empty
        <tr><td colspan="{{ $columnCount }}">No material added yet.</td></tr>
    @endforelse
    </tbody></table>
</x-print.layout>
