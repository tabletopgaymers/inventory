@props(['title', 'guest' => false])
@php
    $signedIn = ! $guest && auth()->check();
    $displayDensity = $signedIn ? (auth()->user()->display_density ?? 'standard') : 'standard';
@endphp
<!doctype html>
<html lang="en" data-theme="tg-app">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <x-app.document-title :title="$title.' · TG Inventory'" />
    <x-app.favicon />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-shell {{ $signedIn ? 'app-authenticated' : 'app-guest' }}" data-density="{{ array_key_exists($displayDensity, \App\Models\User::DISPLAY_DENSITIES) ? $displayDensity : 'standard' }}">
<a class="app-skip" href="#app-main">Skip to content</a>
<div class="app-body">
    @if($signedIn)<aside class="app-sidebar"><x-app.navigation label="Main navigation" /></aside>@endif
    <div class="app-main-column">
        <x-app.header>
            @if($signedIn)
            <div class="app-header-actions">
                <x-app.action id="app-nav-open" aria-haspopup="dialog" aria-controls="app-nav-dialog" aria-expanded="false" hidden>☰ Menu</x-app.action>
                <details class="app-account" id="app-account">
                    <summary class="app-account-trigger" aria-label="Account for {{ auth()->user()->displayName() }}"><span class="app-account-name">{{ auth()->user()->displayName() }}</span> <span aria-hidden="true">▾</span></summary>
                    <div class="app-account-panel">
                        <p class="app-account-label">{{ auth()->user()->displayName() }}</p>
                        <nav aria-label="Account">
                            <a href="/users/{{ auth()->id() }}" @if(request()->is('users/'.auth()->id())) aria-current="page" @endif>My profile</a>
                            <form method="post" action="/logout">@csrf<x-app.action variant="danger" type="submit">Sign out</x-app.action></form>
                        </nav>
                    </div>
                </details>
            </div>
            @endif
        </x-app.header>
    <div class="app-workspace">
        <main id="app-main" class="app-main" tabindex="-1">
            @if($signedIn && request()->is('catalog', 'catalog/*'))
                <x-app.navigation label="Catalog sections" presentation="section" />
            @endif
            {{ $slot }}
        </main>
        <x-app.footer />
    </div>
    </div>
</div>
@if($signedIn)
<dialog id="app-nav-dialog" class="app-nav-dialog" aria-label="Menu">
    <div class="app-drawer-heading"><x-app.action id="app-nav-close" autofocus>Close menu</x-app.action></div>
    <x-app.navigation label="Mobile navigation" presentation="mobile" />
</dialog>
@endif
<x-app.confirmation-dialog />
<x-app.action-templates />
<x-app.status-badge-config />
</body>
</html>
