@extends('design.phase-a.layout')
@section('title', 'Application frame')
@section('intro', 'The current shared application frame, environment status bars and a sample two-panel user page.')
@section('guide-content')
@include('design.phase-a.user-example')
<x-app.guide-divider />
<div class="app-content" id="profile-example">
    <h2>Shared frame and panels</h2>
    <p>The navigation runs from the top of the page. The header sits above the main area to its right; its environment status is left aligned, opposite the account controls. Header, menu and footer come from the shared application layout. Information and action panels share padding; buttons retain their own internal padding.</p>
    <p>Role controls align left and Disable account aligns right. The preview modal changes no account.</p>
    <p><a href="/style-guide/examples#users">Return to complete examples</a>.</p>
</div>
<x-app.guide-divider />
<section class="app-content" id="environment-indicators" aria-labelledby="environment-title">
    <h2 id="environment-title">Environment indicators</h2>
    <p>The environment colors the full top bar. Its label is white, uppercase and vertically centered with the profile control. Production has no environment label and uses the normal white bar.</p>
    <p>These previews use the same header component as the application, with a fictional profile control. They do not change the active environment.</p>
    <div class="app-panel-stack">
        @foreach(['local' => 'Local development', 'development' => 'Development', 'testing' => 'Testing', 'stage' => 'Stage testing', 'production' => 'Production'] as $environment => $label)
            <div>
                <h3>{{ $label }}</h3>
                <x-app.header :environment="$environment" class="guide-environment-header">
                    <div class="app-header-actions">
                        <span class="app-account-trigger">Sample User <span aria-hidden="true">▾</span></span>
                    </div>
                </x-app.header>
            </div>
        @endforeach
    </div>
</section>
@endsection
