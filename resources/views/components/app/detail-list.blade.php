@props(['variant' => 'content'])
<dl {{ $attributes->class(['app-details', 'app-details-'.$variant]) }}>{{ $slot }}</dl>
