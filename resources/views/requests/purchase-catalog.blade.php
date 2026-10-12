<section id="requested-items" tabindex="-1">
@if($tablePanel ?? false)<div class="app-content">@endif
<h2>Catalog lines</h2>
<p>Request individual units to purchase. These requests do not reserve stock.</p>
@if(!$rows)<p>No catalog items added. Describe new items with quantities in Request details, or search existing catalog items below.</p>@endif
@if($tablePanel ?? false)</div>@endif
<x-app.table-region label="Purchase catalog lines" :class="($tablePanel ?? false) ? 'app-list-table-panel' : 'table-wrap'"><table class="stock-editor"><thead><tr><th>Item</th><th>Requested units</th>@if($planning)<th>Estimated line amount (USD) (optional)</th><th>Line note (optional)</th>@endif @if($catalogEditable)<th></th>@endif</tr></thead><tbody>
@php($group=null)
@foreach($rows as $item)
@if($group!==$item['category'].' : '.$item['collection'])@php($group=$item['category'].' : '.$item['collection'])<tr class="group-row"><th colspan="{{ 2 + ($planning ? 2 : 0) + ($catalogEditable ? 1 : 0) }}">{{ $group }}</th></tr>@endif
<tr data-item-id="{{ $item['id'] }}"><td class="app-grouped-item">{{ $item['name'] }}</td><td>@if($catalogEditable)<input name="lines[{{ $item['id'] }}][quantity]" inputmode="numeric" aria-label="Requested units for {{ $item['name'] }} · {{ $item['collection'] }} · SKU {{ $item['sku'] }}" value="{{ $draft['values']['lines'][$item['id']]['quantity'] ?? '' }}">@else{{ number_format((int)($draft['values']['lines'][$item['id']]['quantity'] ?? 0)) }}@endif</td>
@if($planning)<td>@if($catalogEditable)<input name="lines[{{ $item['id'] }}][estimate]" inputmode="decimal" aria-label="Estimate for {{ $item['name'] }} · SKU {{ $item['sku'] }}" value="{{ $draft['values']['lines'][$item['id']]['estimate'] ?? '' }}">@else{{ $draft['values']['lines'][$item['id']]['estimate'] ?? 'Not provided' }}@endif</td><td>@if($catalogEditable)<textarea name="lines[{{ $item['id'] }}][note]" maxlength="5000" aria-label="Line note for {{ $item['name'] }} · SKU {{ $item['sku'] }}">{{ $draft['values']['lines'][$item['id']]['note'] ?? '' }}</textarea>@else{{ $draft['values']['lines'][$item['id']]['note'] ?? 'Not provided' }}@endif</td>@endif
@if($catalogEditable)<td><button name="action" value="remove" data-purchase-remove="{{ $item['id'] }}" formnovalidate aria-label="Remove {{ $item['name'] }} · SKU {{ $item['sku'] }}">Remove</button></td>@endif</tr>
@endforeach
</tbody></table></x-app.table-region>
</section>
@if($catalogEditable)
@if($tablePanel ?? false)<div class="app-content">@endif<h2>Add items</h2><div class="search-actions"><label>Search inventory<input name="search" maxlength="255" value="{{ $draft['search_fields']['search'] ?? '' }}"></label><button name="action" value="search" formnovalidate>Search</button><button name="action" value="show_all" formnovalidate>Show all</button><button name="action" value="reset" formnovalidate>Reset</button></div>
<details><summary>Collection filters</summary><div class="collection-checklist">@foreach($collections as $collection)<label class="app-choice"><input type="checkbox" name="collections[]" value="{{ $collection->id }}" @checked(in_array($collection->id, $draft['search_fields']['collections'] ?? []))>{{ $collection->category_name }} : {{ $collection->name }}</label>@endforeach</div></details>@if($tablePanel ?? false)</div>@endif
@if($draft['criteria'] !== null)
@if($tablePanel ?? false)<div class="app-content">@endif<p>Added items are excluded. Enter whole individual units; blank entries are not selected and explicit zero stays zero.</p>
@if($tablePanel ?? false)</div>@endif
<x-app.table-region label="Search results to add" :class="($tablePanel ?? false) ? 'app-list-table-panel' : 'table-wrap'"><table class="stock-editor"><thead><tr><th>Item</th><th>Request units</th></tr></thead><tbody>@php($group=null)@forelse($results as $item)@if($group!==$item['category'].' : '.$item['collection'])@php($group=$item['category'].' : '.$item['collection'])<tr class="group-row"><th colspan="2">{{ $group }}</th></tr>@endif<tr><td class="app-grouped-item">{{ $item['name'] }}</td><td><input name="selection[{{ $item['id'] }}]" inputmode="numeric" aria-label="Add quantity for {{ $item['name'] }} · {{ $item['collection'] }} · SKU {{ $item['sku'] }} (optional)" value="{{ $draft['selection'][$item['id']] ?? '' }}"></td></tr>@empty<tr><td colspan="2">No matching active items to add.</td></tr>@endforelse</tbody></table></x-app.table-region>
@if($tablePanel ?? false)<div class="app-content">@endif<div class="actions"><button name="action" value="add" formnovalidate>Add to purchase <span data-selection-count></span></button></div>@if($tablePanel ?? false)</div>@endif
@endif
@endif
