@extends('layout')
@section('content-sections')
<nav class="app-section-nav" aria-label="Style guide">
    <a href="/design/phase-a/overview" @if(request()->is('design/phase-a/overview')) aria-current="page" @endif>Overview</a>
    <a href="/design/phase-a/elements" @if(request()->is('design/phase-a/elements')) aria-current="page" @endif>General elements</a>
    <a href="/design/phase-a/examples" @if(request()->is('design/phase-a/examples')) aria-current="page" @endif>Example uses</a>
    @if(request()->getHost() === 'tg-inventory-app.test')
    <a href="/design/phase-a/wrapper" @if(request()->is('design/phase-a/wrapper')) aria-current="page" @endif>Application frame</a>
    @endif
</nav>
<p class="app-field-help">@yield('intro') Sample controls use fictional data and do not save records. The surrounding app navigation works normally.</p>
@yield('guide-content')
<script src="/style-guide.js?v=current-design-2" defer></script>
@endsection
