@props(['label'])
@if(isset($header) || isset($footer))
<div {{ $attributes->class(['app-table-panel-sections', 'app-list-table-panel']) }}>
    @isset($header)<div class="app-table-panel-header">{{ $header }}</div>@endisset
    <div class="app-table-panel-body" role="region" aria-label="{{ $label }}" tabindex="0">{{ $slot }}</div>
    @isset($footer)<div class="app-table-panel-footer">{{ $footer }}</div>@endisset
</div>
@else
<div {{ $attributes->class(['app-table-region', 'table-wrap']) }} role="region" aria-label="{{ $label }}" tabindex="0">{{ $slot }}</div>
@endif
