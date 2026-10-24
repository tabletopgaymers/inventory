    <table class="relocation-material-table">
        <colgroup><col class="table-action-column"><col><col class="table-quantity-column"><col class="table-quantity-column"><col class="table-quantity-column"></colgroup>
        <thead><tr><th><span class="sr-only">Select</span></th><th>Item</th><th class="table-heading-center" data-stock-heading="source_location_id">{{ $sourceName }}</th><th class="table-heading-center" data-stock-heading="destination_location_id">{{ $destinationName }}</th><th class="table-heading-center" data-search-quantity-heading>Request</th></tr></thead>
        <tbody>
            @php($group = null)
            @forelse($results as $item)
                @if($group !== $item['category'].' : '.$item['collection'])
                @php($group = $item['category'].' : '.$item['collection'])
                <tr class="group-row" data-search-group><th><input type="checkbox" data-search-group-check aria-label="Select items in {{ $group }}"></th><th colspan="4">{{ $group }}</th></tr>
                @endif
                <tr data-search-item="{{ $item['id'] }}" data-item-name="{{ $item['name'] }}" data-item-sku="{{ $item['sku'] }}" data-item-group="{{ $group }}" data-item-label="{{ $item['name'] }} · {{ $item['sku'] }}" data-source-balance="{{ $item['source'] }}" data-destination-balance="{{ $item['destination'] }}">
                    <td class="app-grouped-item"><input type="checkbox" data-search-item-check aria-label="Select {{ $item['name'] }} · {{ $item['sku'] }}"></td>
                    <td class="app-grouped-item">{{ $item['name'] }}</td>
                    <td class="table-numeric" data-stock-count>{{ number_format($item['source']) }}</td><td class="table-numeric" data-stock-count>{{ number_format($item['destination']) }}</td>
                    <td><label><span class="sr-only">Request {{ $item['name'] }} · {{ $item['sku'] }}</span><input data-search-quantity aria-label="Request {{ $item['name'] }} · {{ $item['sku'] }}"></label></td>
                </tr>
            @empty
            <tr><td colspan="5">{{ ($searched ?? true) ? 'No matching active items to add.' : 'Use Search or Show all to find items.' }}</td></tr>
            @endforelse
        </tbody>
    </table>
