<x-print.layout :title="$row->title" :document-title="'Packing sheet · '.$row->title">
    <x-print.header :values="['Source'=>$sourceName,'Destination'=>$destinationName,'Requester'=>$requesterName,'Requested'=>$requestedDate ?? '—']" :notes="$row->details ?? ''" />
    <table class="print-material">
    <colgroup><col><col class="print-quantity-column"><col class="print-quantity-column"></colgroup>
    <thead><tr><th scope="col">Item</th><th scope="col" class="print-quantity-heading">Requested</th><th scope="col" class="print-quantity-heading">Fulfilled</th></tr></thead>
    <tbody>
    @forelse(collect($items)->groupBy(fn ($item) => $item['category'].' : '.$item['collection']) as $group => $groupItems)
        <tr class="print-collection"><td colspan="3"><table>
        <colgroup><col><col class="print-quantity-column"><col class="print-quantity-column"></colgroup>
        <thead><tr class="print-group-row"><th colspan="3">{{ $group }}</th></tr></thead><tbody>
        @foreach($groupItems as $item)
            @php($line = $lines->get($item['id']))
            <tr><td class="print-item">{{ $item['name'] }}</td><td class="print-quantity">{{ $line->quantity === null ? '' : number_format($line->quantity) }}</td><td class="print-quantity">{{ $line->fulfillment_quantity === null ? '' : number_format($line->fulfillment_quantity) }}</td></tr>
        @endforeach
        </tbody></table></td></tr>
    @empty
        <tr><td colspan="3">No material added yet.</td></tr>
    @endforelse
    </tbody></table>
</x-print.layout>
