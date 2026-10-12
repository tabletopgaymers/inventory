<x-app.table-region :label="$tableLabel" :class="$tablePanel ? 'app-list-table-panel' : ''">
@if($tableSections ?? false)
    <x-slot:header>
        <div class="app-table-panel-toolbar">
            <p class="app-table-panel-copy" style="margin:0">Available inventory by category and collection.</p>
            <div class="app-actions"><button type="button" class="app-new-action">+ New</button></div>
        </div>
    </x-slot:header>
@endif
<table><thead><tr><th>Item</th><th class="number">Available</th><th class="number">Central</th></tr></thead><tbody>
    <tr class="collection-heading"><th colspan="3">Ribbon : Pronoun</th></tr>
    <tr><td class="app-grouped-item"><a href="#table-panel">They/Them</a></td><td class="number">250</td><td class="number">200</td></tr>
    <tr><td class="app-grouped-item"><a href="#table-panel">Any/All</a></td><td class="number">120</td><td class="number">100</td></tr>
    <tr class="collection-heading"><th colspan="3">Button : Standard</th></tr>
    <tr><td class="app-grouped-item"><a href="#table-panel">Ally</a></td><td class="number">75</td><td class="number">50</td></tr>
</tbody></table>
@if($tableSections ?? false)
    <x-slot:footer>
        <div class="app-table-panel-toolbar">
            <p class="app-field-help app-table-panel-copy" style="margin:0">3 items · Fictional quantities for this example.</p>
            <div class="app-actions"><button type="button">Download CSV</button></div>
        </div>
    </x-slot:footer>
@endif
</x-app.table-region>
