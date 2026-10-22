<!doctype html>
<html lang="en"><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Packing sheet · {{ $row->title }}</title>
    <style>
        body { font-family: system-ui, sans-serif; color: #000; margin: 1.5rem; }
        main { max-width: 65rem; margin: auto; }
        h1 { font-size: 1.4rem; }
        .packing-title { display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
        .packing-header { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 2fr); gap: 1rem; margin-bottom: 1rem; }
        .packing-details { display: grid; grid-template-columns: max-content minmax(0, 1fr); align-content: start; gap: 0 .5rem; margin: 0; line-height: 1.5; }
        .packing-details dt { font-weight: bold; }
        .packing-details dd { margin: 0; overflow-wrap: anywhere; }
        .packing-notes { margin: 0; min-width: 0; border: 1pt solid #000; padding: .35rem .5rem; }
        .packing-notes p { margin: 0; white-space: pre-wrap; overflow-wrap: anywhere; }
        table { width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 0; }
        .packing-collection { break-inside: auto; }
        .packing-collection > td { padding: 0; }
        col.quantity-column { width: 15%; }
        th, td { padding: .35rem .5rem; text-align: left; }
        .quantity { width: 15%; border-left: 1pt solid #000; border-bottom: 1pt solid #000; height: 2rem; text-align: right; }
        .quantity:last-child { border-right: 1pt solid #000; }
        .group-row th { background: #eee; border-left: 1pt solid #000; border-right: 1pt solid #000; border-bottom: 1pt solid #000; }
        .packing-material > thead th { border-top: 1pt solid #000; border-bottom: 1pt solid #000; }
        .packing-material > thead th:first-child { border-left: 1pt solid #000; }
        .packing-material > thead th:last-child { border-right: 1pt solid #000; }
        .packing-material > tbody > tr:not(.packing-collection) > td { border: 1pt solid #000; border-top: 0; }
        .quantity-heading { text-align: right; }
        .item { padding-left: 1rem; border-left: 1pt solid #000; border-bottom: 1pt solid #000; }
        thead { display: table-header-group; break-inside: avoid; }
        tr { break-inside: avoid; }
        button { padding: .15rem .5rem; font: inherit; font-size: .8rem; }
        @media print { @page { margin: .5in; } body { margin: 0; } button { display: none; } }
    </style>
</head><body><main>
    <div class="packing-title"><h1>{{ $row->title }}</h1><button type="button" data-print>Print</button></div>
    <div class="packing-header">
        <dl class="packing-details">
            <dt>Source:</dt><dd>{{ $sourceName }}</dd>
            <dt>Destination:</dt><dd>{{ $destinationName }}</dd>
            <dt>Requester:</dt><dd>{{ $requesterName }}</dd>
            <dt>Requested:</dt><dd>{{ $requestedDate ?? '—' }}</dd>
        </dl>
        <fieldset class="packing-notes" aria-label="Notes"><p>{{ $row->details }}</p></fieldset>
    </div>
    <table class="packing-material">
    <colgroup><col><col class="quantity-column"><col class="quantity-column"></colgroup>
    <thead><tr><th scope="col">Item</th><th scope="col" class="quantity-heading">Requested</th><th scope="col" class="quantity-heading">Fulfilled</th></tr></thead>
    <tbody>
    @forelse(collect($items)->groupBy(fn ($item) => $item['category'].' : '.$item['collection']) as $group => $groupItems)
        <tr class="packing-collection"><td colspan="3"><table>
        <colgroup><col><col class="quantity-column"><col class="quantity-column"></colgroup>
        <thead><tr class="group-row"><th colspan="3">{{ $group }}</th></tr></thead><tbody>
        @foreach($groupItems as $item)
            @php($line = $lines->get($item['id']))
            <tr><td class="item">{{ $item['name'] }}</td><td class="quantity">{{ $line->quantity === null ? '' : number_format($line->quantity) }}</td><td class="quantity">{{ $line->fulfillment_quantity === null ? '' : number_format($line->fulfillment_quantity) }}</td></tr>
        @endforeach
        </tbody></table></td></tr>
    @empty
        <tr><td colspan="3">No material added yet.</td></tr>
    @endforelse
    </tbody></table>
</main><script src="/relocation-print.js" defer></script></body></html>
