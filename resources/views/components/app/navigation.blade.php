@props(['label', 'presentation' => 'desktop'])
@php($destinations = \App\Support\AppNavigation::destinations(auth()->user()))
<nav class="{{ $presentation === 'section' ? 'app-section-nav' : 'app-navigation' }}" aria-label="{{ $label }}">
    @foreach($destinations as $destination)
        @if($presentation === 'section')
            @if($destination['active'])
                @foreach($destination['children'] as $child)
                    <a href="{{ $child['href'] }}" @if($child['current'])aria-current="page"@endif>{{ $child['label'] }}</a>
                @endforeach
            @endif
        @elseif($presentation === 'mobile' && $destination['children'])
            <details class="app-navigation-group" @if($destination['active'])open @endif>
                <summary>{{ $destination['label'] }}</summary>
                <a href="{{ $destination['href'] }}" @if(request()->is(ltrim($destination['href'], '/')))aria-current="page"@endif>{{ $destination['label'] }} overview</a>
                @foreach($destination['children'] as $child)
                    @if($child['href'] !== $destination['href'])
                        <a href="{{ $child['href'] }}" @if($child['current'])aria-current="page"@endif>{{ $child['label'] }}</a>
                    @endif
                @endforeach
            </details>
        @else
            <a href="{{ $destination['href'] }}" @class(['app-navigation-current' => $destination['active']]) @if(request()->is(ltrim($destination['href'], '/')))aria-current="page"@endif>{{ $destination['label'] }}</a>
        @endif
    @endforeach
</nav>
