@props(['heading' => null])
<section {{ $attributes->class(['app-panel']) }}>
    @if($heading)<h2>{{ $heading }}</h2>@endif
    {{ $slot }}
</section>
