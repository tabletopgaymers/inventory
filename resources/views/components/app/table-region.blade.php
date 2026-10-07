@props(['label'])
<div {{ $attributes->class(['app-table-region', 'table-wrap']) }} role="region" aria-label="{{ $label }}" tabindex="0">{{ $slot }}</div>
