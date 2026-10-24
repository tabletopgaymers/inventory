@extends('design.phase-a.layout')
@section('title', 'Vite demo')
@section('intro', 'An isolated asset-build demonstration. Existing pages continue using their public assets.')
@section('guide-content')
@vite(['resources/css/vite-demo.css', 'resources/js/vite-demo.js'])
<section class="app-content" data-vite-demo>
    <h2>Vite asset check</h2>
    <p class="vite-demo-swatch">A purple border and pale purple background confirm the demo CSS loaded.</p>
    <p data-vite-status role="status">Waiting for Vite JavaScript.</p>
    <button type="button">Increment counter</button>
    <p>Count: <output data-vite-count aria-live="polite">0</output></p>
    <p>No data is saved. Reloading resets the counter.</p>
    <h3>Locally hosted Font Awesome Free</h3>
    <p><i class="fa-solid fa-box" aria-hidden="true"></i> Material &nbsp;
       <i class="fa-solid fa-truck" aria-hidden="true"></i> Shipping &nbsp;
       <i class="fa-solid fa-check" aria-hidden="true"></i> Complete</p>
    <p>These Free solid icons and their font are bundled locally through Vite. No CDN or Font Awesome account is used.</p>
    <p><a href="/licenses/fontawesome-free.txt">Font Awesome Free license and notices</a></p>
    <h3>Development and build</h3>
    <p>Run <code>npm run dev</code> in the application directory for live asset updates. Stop it when finished.</p>
    <p>Run <code>npm run build</code> to generate hashed assets in <code>public/build</code>. With the development server stopped, this page uses those built assets.</p>
</section>
@endsection
