@props(['definition', 'endpoint', 'instance' => null])
@php
    $instance = $instance ?: 'inventory-search-'.\Illuminate\Support\Str::uuid();
    $visible = array_diff($definition['filters'], $definition['hiddenFilters']);
    $defaults = $definition['defaults'];
    $locations = collect($definition['columnGroups']['locations'])->sort(fn ($a, $b) =>
        (($b['is_central'] ?? false) <=> ($a['is_central'] ?? false)) ?: strnatcasecmp($a['name'], $b['name'])
    );
    $sources = [];
    foreach (array_intersect(['events', 'transit', 'ordered'], $visible) as $group) {
        $sources = array_replace($sources, $definition['columnGroups'][$group]);
    }
    uasort($sources, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));
@endphp
@once
<link rel="stylesheet" href="/inventory-search-controls.css?v={{ substr(hash_file('sha256', public_path('inventory-search-controls.css')), 0, 12) }}">
<script src="/inventory-search-controls.js?v={{ substr(hash_file('sha256', public_path('inventory-search-controls.js')), 0, 12) }}" defer></script>
@endonce
<div {{ $attributes->class(['inventory-search-controls']) }} id="{{ $instance }}" data-inventory-search data-endpoint="{{ $endpoint }}">
<div class="search-actions">
<label>Search inventory<input type="search" data-is-text maxlength="255" value="{{ $defaults['search'] }}"></label>
<button type="button" class="primary" data-is-action="search" aria-busy="false">Search<span class="app-search-spinner" aria-hidden="true"></span></button>
<button type="button" data-is-action="all" aria-busy="false">Show all<span class="app-search-spinner" aria-hidden="true"></span></button>
<button type="button" data-is-action="reset">Reset</button>
</div>
@if($visible)
<details data-is-filters>
<summary>Filters<span data-is-filter-count></span></summary>
<div class="inventory-search-filter-groups">
@if(in_array('collections', $visible, true))
<fieldset class="inventory-search-collections"><legend>Collections</legend><div class="inventory-search-choices">
@foreach($definition['collections'] as $collection)<label class="app-choice"><input type="checkbox" data-is-collection value="{{ $collection['id'] }}" @checked(in_array($collection['id'], $defaults['collections']))>{{ $collection['category'] }} : {{ $collection['name'] }}</label>@endforeach
</div></fieldset>
@endif
@if(in_array('locations', $visible, true))
<fieldset class="inventory-search-locations"><legend>Locations</legend><div class="inventory-search-choices">
@foreach($locations as $key => $column)<label class="app-choice"><input type="checkbox" data-is-column value="{{ $key }}" @checked(in_array($key, $defaults['columns']))>{{ $column['name'] }}@if($column['state'] !== 'active') ({{ $column['state'] }})@endif</label>@endforeach
</div>
</fieldset>
@endif
@if(array_intersect(['events','transit','ordered'], $visible))
<fieldset class="inventory-search-sources"><legend>Events, In Transit and Ordered</legend>
@foreach($sources as $key => $column)<label class="app-choice"><input type="checkbox" data-is-column value="{{ $key }}" @checked(in_array($key, $defaults['columns']))>{{ $column['name'] }}</label>@endforeach
</fieldset>
@endif
</div>
@if(in_array('inactive', $visible, true))<label class="app-choice"><input type="checkbox" data-is-inactive @checked($defaults['include_inactive'])>Include inactive zero-stock items</label>@endif
<div class="inventory-search-filter-actions"><button type="button" data-is-action="clear">Clear filters</button></div>
</details>
@endif
<p class="app-field-help" data-is-pending hidden>Search settings changed — select Search to update results.</p>
<p data-is-feedback role="status" hidden></p>
<noscript><p>JavaScript is required for this reusable search control.</p></noscript>
</div>
