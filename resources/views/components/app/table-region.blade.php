@props(['label', 'nowrap' => false, 'numeric' => null, 'quantityFields' => false, 'separators' => false])
@php($variants = ['workflow-table--nowrap' => $nowrap, 'workflow-table--numeric' => $numeric === 'all', 'workflow-table--numeric-after-first' => $numeric === 'after-first', 'workflow-table--quantities' => $quantityFields, 'workflow-table--separators' => $separators])
@if(isset($header) || isset($footer))
<div {{ $attributes->class(array_merge(['app-table-panel-sections', 'app-list-table-panel'], $variants)) }}>
    @isset($header)<div class="app-table-panel-header">{{ $header }}</div>@endisset
    <div class="app-table-panel-body" role="region" aria-label="{{ $label }}" tabindex="0">{{ $slot }}</div>
    @isset($footer)<div class="app-table-panel-footer">{{ $footer }}</div>@endisset
</div>
@else
<div {{ $attributes->class(array_merge(['app-table-region', 'table-wrap'], $variants)) }} role="region" aria-label="{{ $label }}" tabindex="0">{{ $slot }}</div>
@endif
