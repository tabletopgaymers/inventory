@props(['definition', 'endpoint', 'instance' => null, 'script' => true, 'formMode' => false, 'persistence' => null, 'submitted' => null, 'filterLabel' => 'Filters'])
@php
    if ($persistence !== null && ! $instance && empty($persistence['key'])) throw new InvalidArgumentException('A persistent search requires a stable instance ID or persistence key.');
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
@if($script)
@once
@vite('resources/js/inventory-search-controls.js')
@endonce
@endif
<div {{ $attributes->class(['inventory-search-controls']) }} id="{{ $instance }}" data-inventory-search data-endpoint="{{ $endpoint }}">
<script type="application/json" data-is-config>{!! \Illuminate\Support\Js::encode(['formMode' => $formMode, 'submitted' => $submitted, 'persistence' => $persistence ? array_merge(['key' => $instance], $persistence, ['userId' => auth()->id()]) : null]) !!}</script>
<div class="search-actions">
{{ $leading ?? '' }}
<div class="app-clearable-field inventory-search-field">
    <input type="search" data-is-text aria-label="Search inventory" placeholder="search item, category, or collection name" @if($formMode) name="search" @endif maxlength="255" value="{{ $defaults['search'] }}">
    <x-app.action control="clear" class="app-clear-input" data-is-clear-text aria-label="Clear search" hidden>✕</x-app.action>
</div>
<x-app.action variant="primary" :type="$formMode ? 'submit' : 'button'" :name="$formMode ? 'action' : null" :value="$formMode ? 'search' : null" data-is-action="search" aria-busy="false">Search<span class="app-search-spinner" aria-hidden="true"></span></x-app.action>
</div>
@if($visible)
<details data-is-filters>
<summary class="inventory-search-filter-heading"><span>{{ $filterLabel }}<span data-is-filter-count></span></span> <x-app.action type="button" size="small" data-is-action="clear">Clear filters</x-app.action></summary>
<div class="inventory-search-filter-groups">
@if(in_array('collections', $visible, true))
<fieldset class="inventory-search-collections"><legend>Collections</legend><div class="inventory-search-choices">
@foreach($definition['collections'] as $collection)<label class="app-choice"><input type="checkbox" data-is-collection @if($formMode) name="collections[]" @endif value="{{ $collection['id'] }}" @checked(in_array($collection['id'], $defaults['collections']))>{{ $collection['category'] }} : {{ $collection['name'] }}</label>@endforeach
</div></fieldset>
@endif
@if(in_array('locations', $visible, true))
<fieldset class="inventory-search-locations"><legend>Locations</legend><div class="inventory-search-choices">
@foreach($locations as $key => $column)<label class="app-choice"><input type="checkbox" data-is-column @if($formMode) name="columns[]" @endif value="{{ $key }}" @checked(in_array($key, $defaults['columns']))>{{ $column['name'] }}@if($column['unavailable'] ?? false) (unavailable — remove this saved selection before searching)@elseif($column['state'] !== 'active') ({{ $column['state'] }})@endif</label>@endforeach
</div>
</fieldset>
@endif
@if(array_intersect(['events','transit','ordered'], $visible))
<fieldset class="inventory-search-sources"><legend>Events, In Transit and Ordered</legend>
@foreach($sources as $key => $column)<label class="app-choice"><input type="checkbox" data-is-column @if($formMode) name="columns[]" @endif value="{{ $key }}" @checked(in_array($key, $defaults['columns']))>{{ $column['name'] }}</label>@endforeach
</fieldset>
@endif
</div>
@if(in_array('inactive', $visible, true))<label class="app-choice"><input type="checkbox" data-is-inactive @if($formMode) name="include_inactive" @endif value="1" @checked($defaults['include_inactive'])>Include inactive zero-stock items</label>@endif
{{ $extraFilters ?? '' }}
</details>
@endif
<p class="app-field-help" data-is-pending hidden>Search settings changed — select Search to update results.</p>
<p data-is-feedback role="status" hidden></p>
<p class="error" data-is-persistence-feedback role="alert" hidden></p>
<noscript><p>JavaScript is required for this reusable search control.</p></noscript>
</div>
