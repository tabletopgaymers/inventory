{{--
Use for a single bordered content panel. Supply heading when the panel should render an h2.

## Slots

The default slot holds the panel content.

## Example

[Welcome view](resources/views/welcome.blade.php).
--}}@props(['heading' => null])
<section {{ $attributes->class(['app-panel']) }}>
    @if($heading)<h2>{{ $heading }}</h2>@endif
    {{ $slot }}
</section>
