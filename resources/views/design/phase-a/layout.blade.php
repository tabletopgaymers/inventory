@extends('layout')
@section('content-sections')
<nav class="app-section-nav" aria-label="Style guide">
    <a href="/style-guide" @if(request()->is('style-guide')) aria-current="page" @endif>Overview</a>
    <a href="/style-guide/presentation" @if(request()->is('style-guide/presentation')) aria-current="page" @endif>Presentation</a>
    <a href="/style-guide/forms" @if(request()->is('style-guide/forms')) aria-current="page" @endif>Forms</a>
    <a href="/style-guide/inventory-search" @if(request()->is('style-guide/inventory-search')) aria-current="page" @endif>Search inventory</a>
    <a href="/style-guide/tab-form" @if(request()->is('style-guide/tab-form')) aria-current="page" @endif>Tab form</a>
    <a href="/style-guide/workflow" @if(request()->is('style-guide/workflow')) aria-current="page" @endif>Workflow components</a>
    <a href="/style-guide/notifications" @if(request()->is('style-guide/notifications')) aria-current="page" @endif>Notifications</a>
    <a href="/style-guide/orphaned" @if(request()->is('style-guide/orphaned')) aria-current="page" @endif>Orphaned</a>
    <a href="/style-guide/examples" @if(request()->is('style-guide/examples')) aria-current="page" @endif>Example uses</a>
    <a href="/style-guide/vite-demo" @if(request()->is('style-guide/vite-demo')) aria-current="page" @endif>Vite demo</a>
    @if(app()->environment('local'))
    <a href="/style-guide/wrapper" @if(request()->is('style-guide/wrapper')) aria-current="page" @endif>Application frame</a>
    @endif
</nav>
@hasSection('guide-notice')
<p class="app-field-help">@yield('guide-notice')</p>
@else
<p class="app-field-help">@yield('intro') Sample controls use fictional data and do not save records. The surrounding app navigation works normally.</p>
@endif
@yield('guide-content')
<script src="/style-guide.js?v={{ substr(hash_file('sha256', public_path('style-guide.js')), 0, 12) }}" defer></script>
@endsection
