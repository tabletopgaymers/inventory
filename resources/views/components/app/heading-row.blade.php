@props(['variant' => 'section'])
<div {{ $attributes->class(['app-heading-row', 'app-heading-row-'.$variant]) }}>{{ $slot }}</div>
