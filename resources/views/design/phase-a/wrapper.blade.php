<!doctype html>
<html lang="en" data-theme="tg-guide">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventory · Wrapper design candidate</title>
    <link rel="stylesheet" href="/design/phase-a/evaluation.css">
    <link rel="stylesheet" href="/design/phase-a/wrapper.css">
    <script src="/design/phase-a/wrapper.js" defer></script>
</head>
<body class="wrapper-demo">
<a class="skip-link" href="#main">Skip to content</a>
<header class="wrapper-header">
    <div class="wrapper-brand"><a href="/design/phase-a/overview">TG Inventory</a><span>Tabletop Gaymers</span></div>
    <span class="badge badge-outline wrapper-preview">Local design candidate</span>
    <div class="wrapper-header-actions">
        <button id="navigation-open" class="btn btn-outline btn-sm" type="button" aria-haspopup="dialog" aria-controls="mobile-navigation" aria-expanded="false" hidden>☰ <span>Menu</span></button>
        <details class="wrapper-account" id="account-disclosure">
            <summary class="btn btn-outline btn-sm">JD <span class="wrapper-account-name">· Jasmine Davidson</span> ▾</summary>
            <div class="wrapper-account-panel">
                <p class="font-semibold">Jasmine Davidson</p><p class="text-sm text-base-content/70">Illustrative account</p>
                <div class="mt-4 grid gap-2"><button class="btn btn-ghost justify-start" type="button" disabled>My profile</button><button class="btn btn-ghost justify-start" type="button" disabled>Sign out</button></div>
                <p class="mt-3 text-sm text-base-content/70">Account actions are display examples.</p>
            </div>
        </details>
    </div>
</header>
@php($destinations = ['Inventory', 'Catalog', 'Purchases', 'Relocations', 'Events', 'Users'])
<div class="wrapper-body">
    <aside class="wrapper-sidebar" id="desktop-navigation">
        <p class="eyebrow mb-4">Workspace</p>
        <nav aria-label="Primary navigation" class="wrapper-navigation">
            @foreach($destinations as $destination)
                <a href="#main" class="btn {{ $loop->first ? 'btn-primary' : 'btn-ghost' }} justify-start" @if($loop->first) aria-current="page" @endif>{{ $destination }}</a>
            @endforeach
        </nav>
        <p class="mt-6 text-sm text-base-content/70">Navigation destinations are illustrative. They keep you in this demo.</p>
    </aside>
    <div class="wrapper-workspace">
        <main id="main" tabindex="-1">
            <nav aria-label="Breadcrumb" class="breadcrumbs text-sm"><ul><li>Inventory</li><li aria-current="page">Browse</li></ul></nav>
            <div class="wrapper-page-heading"><div><h1 class="text-3xl font-semibold tracking-tight">Inventory</h1><p class="mt-2 text-base-content/75">Find supplies by collection or storage location.</p></div><span class="badge badge-outline">Static sample</span></div>
            <nav aria-label="Inventory sections" class="wrapper-sections"><a href="#working-area" class="btn btn-primary btn-sm" aria-current="page">Browse</a><a href="#working-area" class="btn btn-ghost btn-sm">Location counts</a></nav>
            <section id="working-area" class="specimen mt-6" aria-labelledby="working-title">
                <p class="eyebrow">Main working area</p><h2 id="working-title" class="mt-3">A clear space for the task at hand</h2>
                <p class="mt-3 max-w-3xl text-base-content/75">The frame stays consistent as people browse inventory, prepare requests or review records. Page-specific actions and content live here.</p>
                <div class="wrapper-summary mt-6"><div><p class="text-sm text-base-content/70">Collection</p><p class="font-semibold">Ribbon · Pronoun</p></div><div><p class="text-sm text-base-content/70">Location</p><p class="font-semibold">Central</p></div><div><p class="text-sm text-base-content/70">Illustrative quantity</p><p class="font-semibold">2,550 units</p></div></div>
                <div class="mt-6 rounded-lg border border-dashed border-base-300 p-6"><h3>Page content goes here</h3><p class="mt-2 text-base-content/70">Search fields, grouped inventory tables and record details will use the shared component patterns.</p></div>
            </section>
            <p class="mt-5 text-sm text-base-content/70">Design proposal only. Menu interactions work; business actions and navigation destinations are illustrative.</p>
        </main>
        <footer class="wrapper-footer"><span>TG Inventory · Local development</span><a class="link link-primary" href="/design/phase-a/examples#wrapper">Return to guide examples</a></footer>
    </div>
</div>
<dialog id="mobile-navigation" class="wrapper-drawer" aria-labelledby="navigation-title">
    <div class="wrapper-drawer-heading"><h2 id="navigation-title">Workspace</h2><button id="navigation-close" class="btn btn-outline btn-sm" type="button" autofocus>Close menu</button></div>
    <nav aria-label="Primary navigation" class="wrapper-navigation mt-6">
        @foreach($destinations as $destination)
            <a href="#main" class="btn {{ $loop->first ? 'btn-primary' : 'btn-ghost' }} justify-start" @if($loop->first) aria-current="page" @endif>{{ $destination }}</a>
        @endforeach
    </nav>
    <p class="mt-6 text-sm text-base-content/70">Illustrative destinations. Selecting one closes the menu and returns to this page.</p>
</dialog>
</body>
</html>
