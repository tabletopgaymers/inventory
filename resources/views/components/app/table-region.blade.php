{{--
Use this around a table that needs the standard panel frame and keyboard-focusable horizontal scrolling. Supply a `label` for the scroll region’s accessible name. The table remains responsible for its own columns, row groups, and content.

Enable `nowrap`, `quantityFields`, or `separators` for the corresponding shared table styles. Set `numeric` to `all` or `after-first` to apply numeric alignment across those columns.

## Slots

- Default holds the table or other scrollable content.
- `header` and `footer` are optional. They sit outside the scrolling body, inside the same panel frame.

## Example

See [Inventory results](resources/views/inventory/browse.blade.php) for a table with panel actions and a footer.
--}}@props(['label', 'nowrap' => false, 'numeric' => null, 'quantityFields' => false, 'separators' => false])
@php($variants = ['workflow-table--nowrap' => $nowrap, 'workflow-table--numeric' => $numeric === 'all', 'workflow-table--numeric-after-first' => $numeric === 'after-first', 'workflow-table--quantities' => $quantityFields, 'workflow-table--separators' => $separators])
<div {{ $attributes->class(array_merge(['app-table-panel', 'app-table-region', 'app-list-table-panel'], $variants)) }}>
    @isset($header)<div class="app-table-panel-header">{{ $header }}</div>@endisset
    <div class="app-table-panel-body" role="region" aria-label="{{ $label }}" tabindex="0">{{ $slot }}</div>
    @isset($footer)<div class="app-table-panel-footer">{{ $footer }}</div>@endisset
</div>
