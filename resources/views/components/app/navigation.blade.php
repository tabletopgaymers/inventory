@props(['label', 'presentation' => 'desktop'])
@php
    $destinations = \App\Support\AppNavigation::destinations(auth()->user());
    $environmentBox = match (config('app.env')) {
        'local' => ['tone' => 'local', 'lines' => ['LOCAL', 'DEVELOPMENT']],
        'development' => ['tone' => 'development', 'lines' => ['REMOTE', 'ENVIRONMENT']],
        'testing' => ['tone' => 'testing', 'lines' => ['TESTING', 'ENVIRONMENT']],
        'staging' => ['tone' => 'staging', 'lines' => ['STAGE', 'ENVIRONMENT']],
        'production' => null,
        default => null,
    };
@endphp
<nav class="{{ $presentation === 'section' ? 'app-section-nav' : 'app-navigation' }}{{ $environmentBox && $presentation !== 'section' ? ' app-navigation-with-environment' : '' }}" aria-label="{{ $label }}">
    @if($environmentBox && $presentation !== 'section')
        <div class="app-environment-box app-environment-{{ $environmentBox['tone'] }}">@foreach($environmentBox['lines'] as $environmentLine)<span>{{ $environmentLine }}</span> @endforeach</div>
    @endif
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
