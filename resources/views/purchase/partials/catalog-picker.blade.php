<x-app.table-region label="Catalog search results" :nowrap="true" :quantity-fields="true" class="purchase-material">
<x-slot:header><x-workflow.heading><h2>Add material</h2><button type="button" data-add-other>Add Other item</button></x-workflow.heading>
<x-app.inventory-search :definition="$searchDefinition" :endpoint="'/purchase/work/'.$token.'/search'" instance="purchase-material-search" :script="false" />
<p class="warning" role="status" data-purchase-search-feedback hidden></p></x-slot:header>
<table><colgroup><col class="table-action-column"><col><col class="table-quantity-column"><col class="table-quantity-column"></colgroup><thead><tr><th><span class="sr-only">Select</span></th><th>Item</th><th class="table-heading-center">Est. unit price</th><th class="table-heading-center">{{ $quantityLabel ?? 'Request' }}</th></tr></thead><tbody data-purchase-search-results><tr><td colspan="4">Use Search or Show all to find items.</td></tr></tbody></table>
<x-slot:footer><div class="app-table-panel-toolbar app-material-add-actions"><div class="app-actions"><button type="button" class="primary" data-add-selected disabled>Add selected</button></div></div></x-slot:footer>
</x-app.table-region>
