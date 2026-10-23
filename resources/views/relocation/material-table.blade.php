            <x-app.table-region label="Requested material" :nowrap="true" :quantity-fields="true" class="app-list-table-panel">
                <x-slot:header>
                    <x-workflow.heading><h2 data-material-table-title>Requested material</h2><button type="button" class="primary" data-material-next>Next &gt;</button><button type="submit" name="action" value="print" data-print-url="/relocation/work/{{ $token }}/print" data-fulfillment-print hidden>Print</button><button type="button" class="primary" data-reconcile-next hidden>Next &gt;</button></x-workflow.heading>
                    <p data-material-empty @if(!empty($work['lines'])) hidden @endif>No material added yet.</p>
                    <p data-reconcile-progress hidden></p>
                </x-slot:header>
                <table class="relocation-material-table">
                    <colgroup><col class="table-action-column"><col><col class="table-quantity-column" data-material-only><col class="table-quantity-column"><col class="table-quantity-column" data-material-only><col class="table-quantity-column" data-fulfillment-only hidden><col class="table-quantity-column" data-fulfillment-only hidden></colgroup>
                    <thead><tr><th><span class="sr-only">Remove</span></th><th>Item</th><th class="table-heading-center table-border-left table-border-right" data-material-only data-material-location="source_location_id">Source</th><th class="table-heading-center">Requested</th><th class="table-heading-center table-border-left" data-material-only data-material-location="destination_location_id">Destination</th><th class="table-heading-center" data-fulfillment-only hidden>Fulfilled</th><th class="table-heading-center" data-fulfillment-only hidden>Received</th></tr></thead>
                    <tbody data-material-lines>
                        @php($group = null)
                        @foreach($materialItems as $item)
                        @php($itemId = $item['id'])
                        @if($group !== $item['category'].' : '.$item['collection'])
                        @php($group = $item['category'].' : '.$item['collection'])
                        <tr class="group-row" data-material-group><th></th><th colspan="4">{{ $group }}</th></tr>
                        @endif
                        <tr data-material-item="{{ $itemId }}" data-added-stage="{{ $addedStages[$itemId] ?? 'requested' }}" data-item-group="{{ $group }}" data-item-name="{{ $item['name'] }}" data-item-sku="{{ $item['sku'] }}" data-source-balance="{{ $item['source'] }}" data-destination-balance="{{ $item['destination'] }}">
                            <td><button type="button" class="material-remove" data-material-remove aria-label="Remove {{ $item['name'] }}" title="Remove {{ $item['name'] }}" @disabled(!$editable)>✕</button></td>
                            <td class="app-grouped-item" data-material-label>{{ $item['name'] }}</td>
                            <td class="table-numeric table-border-left table-border-right" data-material-only data-material-projection="source">—</td>
                            <td><label><span class="sr-only">Requested quantity for {{ $item['name'] }} · {{ $item['sku'] }}</span><input id="relocation-quantity-{{ $itemId }}" name="lines[{{ $itemId }}]" value="{{ $work['lines'][$itemId] }}" inputmode="numeric" @readonly(!$editable)></label></td>
                            <td class="table-numeric table-border-left" data-material-only data-material-projection="destination">—</td>
                            <td data-fulfillment-only hidden><label><span class="sr-only">Fulfilled quantity for {{ $item['name'] }}</span><input data-material-fulfilled value="{{ $fulfilled[$itemId] ?? '' }}" aria-label="Fulfilled quantity for {{ $item['name'] }}" disabled></label></td>
                            <td data-fulfillment-only hidden><input data-material-received value="{{ $received[$itemId] ?? '' }}" aria-label="Received quantity for {{ $item['name'] }}" inputmode="numeric" disabled></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-app.table-region>
        <template data-material-template>
            <tr><td><button type="button" class="material-remove" data-material-remove aria-label="Remove item" title="Remove item">✕</button></td><td class="app-grouped-item" data-material-label></td><td class="table-numeric table-border-left table-border-right" data-material-only data-material-projection="source">—</td><td><label><span class="sr-only" data-material-quantity-label></span><input inputmode="numeric"></label></td><td class="table-numeric table-border-left" data-material-only data-material-projection="destination">—</td><td data-fulfillment-only hidden><label><span class="sr-only" data-fulfilled-label></span><input data-material-fulfilled disabled></label></td><td data-fulfillment-only hidden><input data-material-received inputmode="numeric" disabled></td></tr>
        </template>
        <template data-material-group-template><tr class="group-row" data-material-group><th></th><th colspan="4"></th></tr></template>
