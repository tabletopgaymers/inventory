@props(['title', 'guest' => false])
@php($signedIn = ! $guest && auth()->check())
<!doctype html>
<html lang="en" data-theme="tg-app">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · TG Inventory</title>
    <link rel="stylesheet" href="/app.css"><script src="/app-shell.js" defer></script>
</head>
<body class="app-shell {{ $signedIn ? 'app-authenticated' : 'app-guest' }}">
<a class="app-skip" href="#app-main">Skip to content</a>
<header class="app-header">
    <div class="app-brand"><a href="/">TG Inventory</a><span>Tabletop Gaymers</span></div>
    <span class="app-environment badge badge-outline">{{ app()->environment('local') ? 'Local development' : 'Development' }}</span>
    @if($signedIn)
    <div class="app-header-actions">
        <x-app.action id="app-nav-open" aria-haspopup="dialog" aria-controls="app-nav-dialog" aria-expanded="false" hidden>☰ Menu</x-app.action>
        <details class="app-account" id="app-account">
            <summary class="app-account-trigger" aria-label="Account for {{ auth()->user()->displayName() }}"><span class="app-account-name">{{ auth()->user()->displayName() }}</span> <span aria-hidden="true">▾</span></summary>
            <div class="app-account-panel">
                <p class="app-account-label">{{ auth()->user()->displayName() }}</p>
                <nav aria-label="Account">
                    <a href="/profile" @if(request()->is('profile'))aria-current="page"@endif>My profile</a>
                    <form method="post" action="/logout">@csrf<x-app.action variant="danger" type="submit">Sign out</x-app.action></form>
                </nav>
            </div>
        </details>
    </div>
    @endif
</header>
<div class="app-body">
    @if($signedIn)<aside class="app-sidebar"><x-app.navigation label="Main navigation" /></aside>@endif
    <div class="app-workspace">
        <main id="app-main" class="app-main" tabindex="-1">
            @if($signedIn && request()->is('inventory', 'inventory/*', 'catalog', 'catalog/*'))
                <x-app.navigation :label="request()->is('catalog', 'catalog/*') ? 'Catalog sections' : 'Inventory sections'" presentation="section" />
            @endif
            {{ $slot }}
        </main>
        <footer class="app-footer"><span>TG Inventory · {{ app()->environment('local') ? 'Local development' : 'Development' }}</span><span>{{ config('baseline.revision') ?: 'Unrecorded working copy' }}</span></footer>
    </div>
</div>
@if($signedIn)
<dialog id="app-nav-dialog" class="app-nav-dialog" aria-labelledby="app-nav-title">
    <div class="app-drawer-heading"><h2 id="app-nav-title">Menu</h2><x-app.action id="app-nav-close" autofocus>Close menu</x-app.action></div>
    <x-app.navigation label="Mobile navigation" presentation="mobile" />
</dialog>
@endif
</body>
</html>
