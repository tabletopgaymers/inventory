@extends('design.phase-a.layout')
@section('title', 'Application frame')
@section('intro', 'The current shared application frame, environment status bars and a sample two-panel user page.')
@section('guide-content')
@include('design.phase-a.user-example')
<x-app.guide-divider />
<div class="app-content" id="profile-example">
    <h2>Shared frame and panels</h2>
    <p>Desktop navigation stays at the top of the viewport while the page scrolls. If the menu is taller than the screen, it scrolls independently so all links remain reachable. The header sits above the main area to its right; its environment status is left aligned, opposite the account controls. Header, menu and footer come from the shared application layout. Information and action panels share padding; buttons retain their own internal padding.</p>
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
<x-app.guide-divider />
<section class="app-content" id="preparation-footer" aria-labelledby="preparation-footer-title">
    <h2 id="preparation-footer-title">Stage preparation footer</h2>
    <p>Stage shows its Preparation ID in the center when the deployed release supplies one. Click the center to copy only the ID for Check Production or Deploy Production; click the version on the right to copy all version details including the ID. Both briefly show “Copied to clipboard”.</p>
    <p>This fictional example uses the real shared footer. Other environments, or Stage releases without a valid ID, omit the center element.</p>
    <x-app.footer environment="stage" :metadata="['branch' => 'feature/interface-polish', 'revision' => '2ef80c13439b8e39eebe102869925733aaca408c', 'modified' => false, 'preparation_id' => 'f17f5d97137896cf5ec817e522ec9996']" class="guide-footer-preview" />
</section>
@endsection
