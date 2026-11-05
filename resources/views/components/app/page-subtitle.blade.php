@props(['subtitle' => null])
@if($subtitle !== null)
<p {{ $attributes->class(['app-page-subtitle']) }} data-page-subtitle @if(trim((string) $subtitle) === '') hidden @endif>{{ $subtitle }}</p>
@endif
