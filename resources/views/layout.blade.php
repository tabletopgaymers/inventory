<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>@yield('title') · TG Inventory</title><link rel="stylesheet" href="/app.css"></head>
<body><header><a href="/" class="brand">TG Inventory</a>@auth
<nav aria-label="Main navigation">
@foreach(['/users' => 'Users', '/catalog' => 'Catalog', '/inventory' => 'Inventory', '/purchases' => 'Purchases', '/relocations' => 'Relocations', '/events' => 'Events'] as $href => $label)
<a href="{{ $href }}" @if(request()->is(ltrim($href, '/'), ltrim($href, '/').'/*'))aria-current="page"@endif>{{ $label }}</a>
@endforeach
</nav>
<nav aria-label="Account"><a href="/profile" @if(request()->is('profile'))aria-current="page"@endif>My profile</a><form method="post" action="/logout">@csrf<button>Sign out</button></form></nav>@endauth</header>
<main><div class="page-heading"><h1>@yield('title')</h1>@yield('title-status')</div>
@if(session('success'))<p class="notice" role="status">{{ session('success') }}</p>@endif
@if(session('error'))<p class="error" role="alert">{{ session('error') }}</p>@endif
@if($errors->any())<div class="error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@auth
@if(count(array_intersect(auth()->user()->roles(), ['admin', 'manager', 'procurement'])) && ! auth()->user()->validContact())<p class="warning">Add your organizational contact address and confirm it in <a href="/profile">My profile</a>. Your current roles are retained.</p>@endif
@endauth
@yield('content')</main><footer>Development · {{ config('baseline.revision') ?: 'Unrecorded working copy' }}</footer></body></html>
