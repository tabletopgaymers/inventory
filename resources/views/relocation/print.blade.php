<!doctype html>
<html lang="en"><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Packing sheet · {{ $row->title }}</title>
    <style>
        body { font-family: system-ui, sans-serif; color: #000; margin: 1.5rem; }
        main { max-width: 65rem; margin: auto; }
        h1 { font-size: 1.4rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: .35rem .5rem; text-align: left; }
        .quantity { width: 15%; border: 1px solid #777; height: 2rem; text-align: right; }
        .group-row th { background: #eee; border-bottom: 1px solid #aaa; }
        .item { padding-left: 1rem; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; }
        button { padding: .4rem 1rem; margin-bottom: 1rem; font: inherit; }
        @media print { @page { margin: .5in; } body { margin: 0; } button { display: none; } }
    </style>
</head><body><main>
    <h1>{{ $row->title }}</h1>
    <p><strong>Source:</strong> {{ $sourceName }} → <strong>Destination:</strong> {{ $destinationName }}</p>
    <p><strong>Requester:</strong> {{ $requesterName }}</p>
    <button type="button" data-print>Print</button>
    <table><thead><tr><th>Item</th><th>Requested</th><th>Fulfilled</th></tr></thead><tbody>
    @php($group = null)
    @forelse($items as $item)
        @if($group !== $item['category'].' : '.$item['collection'])
            @php($group = $item['category'].' : '.$item['collection'])
            <tr class="group-row"><th colspan="3">{{ $group }}</th></tr>
        @endif
        @php($line = $lines->get($item['id']))
        <tr><td class="item">{{ $item['name'] }}</td><td class="quantity">{{ $line->quantity === null ? '' : number_format($line->quantity) }}</td><td class="quantity">{{ $line->fulfillment_quantity === null ? '' : number_format($line->fulfillment_quantity) }}</td></tr>
    @empty
        <tr><td colspan="3">No material added yet.</td></tr>
    @endforelse
    </tbody></table>
</main><script src="/relocation-print.js" defer></script></body></html>
