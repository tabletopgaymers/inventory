<!doctype html>
<html lang="en" data-theme="tg-guide">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Td guide</title>
    <link rel="stylesheet" href="/design/phase-a/evaluation.css">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="guide-header">
    <div class="guide-width flex flex-wrap items-center justify-between gap-4 py-5">
        <a href="/design/phase-a/overview" class="text-xl font-semibold tracking-tight">TG Inventory <span class="font-normal text-base-content/65">/ Design</span></a>
        <span class="badge badge-outline">Design proposal · static examples</span>
    </div>
    <nav class="guide-width flex flex-wrap gap-2 pb-4" aria-label="Style guide">
        <a href="/design/phase-a/overview" class="btn btn-sm {{ request()->is('design/phase-a/overview') ? 'btn-primary' : 'btn-ghost' }}" @if(request()->is('design/phase-a/overview')) aria-current="page" @endif>Overview</a>
        <a href="/design/phase-a/elements" class="btn btn-sm {{ request()->is('design/phase-a/elements') ? 'btn-primary' : 'btn-ghost' }}" @if(request()->is('design/phase-a/elements')) aria-current="page" @endif>General elements</a>
        <a href="/design/phase-a/examples" class="btn btn-sm {{ request()->is('design/phase-a/examples') ? 'btn-primary' : 'btn-ghost' }}" @if(request()->is('design/phase-a/examples')) aria-current="page" @endif>Example uses</a>
    </nav>
</header>
<main id="main" class="guide-width space-y-8 py-10" tabindex="-1">
    <div class="max-w-3xl space-y-3">
        <p class="eyebrow">Tailwind + daisyUI</p>
        <h1 class="text-4xl font-semibold tracking-tight">@yield('title')</h1>
        <p class="text-lg text-base-content/75">@yield('intro')</p>
    </div>
    @yield('content')
</main>
<footer class="guide-width border-t border-base-300 py-6 text-sm text-base-content/70">
    Palette Chameleon 09 · Design Phase A · Proposed design, awaiting user review. Td use here does not authorize application-wide adoption.
</footer>
</body>
</html>
