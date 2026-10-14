            <x-app.table-region label="Requested material" class="app-list-table-panel">
                <x-slot:header>
                    <div class="relocation-review-heading"><h2 data-material-table-title>Requested material</h2><a class="button" href="/relocation/work/{{ $token }}/print" target="_blank" rel="noopener" data-fulfillment-print hidden>Print</a></div>
                    <p data-material-empty @if(!empty($work['lines'])) hidden @endif>No material added yet.</p>
                </x-slot:header>
                <table class="relocation-material-table">
                    <colgroup><col class="material-delete-column"><col><col class="material-quantity-column"><col class="material-quantity-column"><col class="material-quantity-column"></colgroup>
                    <thead><tr><th><span class="sr-only">Remove</span></th><th>Item</th><th class="material-quantity-heading">Requested</th><th class="material-quantity-heading">Fulfilled</th><th class="material-quantity-heading">Received</th></tr></thead>
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
                            <td><label><span class="sr-only">Requested quantity for {{ $item['name'] }} · {{ $item['sku'] }}</span><input id="relocation-quantity-{{ $itemId }}" name="lines[{{ $itemId }}]" value="{{ $work['lines'][$itemId] }}" inputmode="numeric" @readonly(!$editable)></label></td>
                            <td><label><span class="sr-only">Fulfilled quantity for {{ $item['name'] }}</span><input data-material-fulfilled value="{{ $fulfilled[$itemId] ?? '' }}" aria-label="Fulfilled quantity for {{ $item['name'] }}" disabled></label></td>
                            <td><input value="{{ $received[$itemId] ?? '' }}" aria-label="Received quantity for {{ $item['name'] }}" disabled></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-app.table-region>
        <template data-material-template>
            <tr><td><button type="button" class="material-remove" data-material-remove aria-label="Remove item" title="Remove item">✕</button></td><td class="app-grouped-item" data-material-label></td><td><label><span class="sr-only" data-material-quantity-label></span><input inputmode="numeric"></label></td><td><label><span class="sr-only" data-fulfilled-label></span><input data-material-fulfilled disabled></label></td><td><input data-material-received disabled></td></tr>
        </template>
        <template data-material-group-template><tr class="group-row" data-material-group><th></th><th colspan="4"></th></tr></template>
