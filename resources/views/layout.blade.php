<x-app.shell>
    <x-slot:title>@yield('title')</x-slot:title>
    <x-app.page-heading :class="$__env->hasSection('relocation-save') || $__env->hasSection('purchase-save') || $__env->hasSection('page-actions') ? 'app-heading-with-actions' : ''">
        <x-slot:title>@yield('title')</x-slot:title>
        <x-slot:status>@yield('title-status')</x-slot:status>
        @hasSection('relocation-save')
            <x-slot:actions>@yield('relocation-save')</x-slot:actions>
        @elseif($__env->hasSection('purchase-save'))
            <x-slot:actions>@yield('purchase-save')</x-slot:actions>
        @elseif($__env->hasSection('page-actions'))
            <x-slot:actions>@yield('page-actions')</x-slot:actions>
        @endif
    </x-app.page-heading>
    @if(session('success'))<p class="notice" role="status">{{ session('success') }}</p>@endif
    @if(session('error'))<p class="error" role="alert">{{ session('error') }}</p>@endif
    @if($errors->any())
        <div class="error" role="alert" aria-label="Validation errors">
            @hasSection('error-summary')
                @yield('error-summary')
            @else
                @foreach($errors->messages() as $key => $messages)
                    @foreach($messages as $error)<p data-error-key="{{ $key }}">{{ $error }}</p>@endforeach
                @endforeach
            @endif
        </div>
    @endif
    @auth
        @if(count(array_intersect(auth()->user()->roles(), ['admin', 'manager', 'procurement'])) && ! auth()->user()->validContact())
            <p class="warning">Add your organizational contact address and confirm it in <a href="/profile">My profile</a>. Your current roles are retained.</p>
        @endif
    @endauth
    @hasSection('content-sections')
        @yield('content-sections')
    @else
        <div class="app-content">@hasSection('relocation-progress')@yield('relocation-progress')@endif @hasSection('purchase-progress')@yield('purchase-progress')@endif
            @yield('content')
        </div>
    @endif
</x-app.shell>
