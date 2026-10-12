@extends('layout')
@section('content-sections')
<nav class="app-section-nav" aria-label="Style guide">
    <a href="/design/phase-a/overview" @if(request()->is('design/phase-a/overview')) aria-current="page" @endif>Overview</a>
    <a href="/design/phase-a/presentation" @if(request()->is('design/phase-a/presentation')) aria-current="page" @endif>Presentation</a>
    <a href="/design/phase-a/forms" @if(request()->is('design/phase-a/forms')) aria-current="page" @endif>Forms</a>
    <a href="/design/phase-a/notifications" @if(request()->is('design/phase-a/notifications')) aria-current="page" @endif>Notifications</a>
    <a href="/design/phase-a/orphaned" @if(request()->is('design/phase-a/orphaned')) aria-current="page" @endif>Orphaned</a>
    <a href="/design/phase-a/examples" @if(request()->is('design/phase-a/examples')) aria-current="page" @endif>Example uses</a>
    @if(app()->environment('local'))
    <a href="/design/phase-a/wrapper" @if(request()->is('design/phase-a/wrapper')) aria-current="page" @endif>Application frame</a>
    @endif
</nav>
<p class="app-field-help">@yield('intro') Sample controls use fictional data and do not save records. The surrounding app navigation works normally.</p>
@yield('guide-content')
<script src="/style-guide.js?v={{ substr(hash_file('sha256', public_path('style-guide.js')), 0, 12) }}" defer></script>
@endsection
