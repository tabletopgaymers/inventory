{{--
Use for the shared secondary line under a page title. A null subtitle omits the element. Blank text keeps a hidden element available for a later update.

## Slots

No caller slots.

## Example

[Page heading](resources/views/components/app/page-heading.blade.php) and [Presentation](/style-guide/presentation#titles).
--}}@props(['subtitle' => null])
@if($subtitle !== null)
<p {{ $attributes->class(['app-page-subtitle']) }} data-page-subtitle @if(trim((string) $subtitle) === '') hidden @endif>{{ $subtitle }}</p>
@endif
