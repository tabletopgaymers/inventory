@extends('design.phase-a.layout')
@section('title', 'Orphaned examples')
@section('intro', 'Temporary holding page for examples outside the three main component pages.')
@section('guide-content')
<div class="app-content"><p>These examples are retained for review. They are not new standards; the legacy table is historical and is not the layout for new pages.</p></div>
<x-app.guide-divider />
<section id="table-panel" aria-labelledby="legacy-title">
<h2 id="legacy-title">Legacy table inside a panel</h2>
<div class="app-content"><p>The earlier padded panel surrounded the table, text and actions. The current standard is shown under <a href="/design/phase-a/presentation#table-panel">Table panel</a>.</p>
@include('design.phase-a.table-sample', ['tablePanel' => false, 'tableLabel' => 'Legacy padded-panel table'])
<p class="app-field-help">Fictional quantities; this example does not change inventory.</p><div class="app-actions"><button type="button">Download sample</button></div></div>
</section>
<x-app.guide-divider />
<section aria-labelledby="environment-title"><h2 id="environment-title">Environment indicators</h2><p>These are label and color specimens. They do not change the active environment.</p>
<x-app.table-region label="Environment indicators" class="app-list-table-panel"><table><thead><tr><th>Environment</th><th>Indicator</th></tr></thead><tbody>
<tr><td>Local</td><td><div class="app-environment-box app-environment-local"><span>LOCAL</span><span>DEVELOPMENT</span></div></td></tr>
<tr><td>Remote development</td><td><div class="app-environment-box app-environment-development"><span>REMOTE</span><span>ENVIRONMENT</span></div></td></tr>
<tr><td>Testing</td><td><div class="app-environment-box app-environment-testing"><span>TESTING</span><span>ENVIRONMENT</span></div></td></tr>
<tr><td>Staging</td><td><div class="app-environment-box app-environment-staging"><span>STAGE</span><span>ENVIRONMENT</span></div></td></tr>
</tbody></table></x-app.table-region></section>
<x-app.guide-divider />
<div class="app-content"><h2>Record details</h2><dl class="app-user-details"><dt>Name</dt><dd>Sample Administrator</dd><dt>TG email</dt><dd>sample@tabletopgaymers.org (confirmed)</dd><dt>MS account</dt><dd>sample@example.test</dd><dt>Elevated roles</dt><dd>admin, manager, procurement</dd><dt>Time zone</dt><dd>America/Chicago</dd></dl><p>Use a definition list for labeled values. See the complete <a href="/design/phase-a/examples#users">user example</a>.</p></div>
<x-app.guide-divider />
<div class="app-content"><h2>Permanent notes</h2><p>Sample note: checked the convention kit.</p><p class="app-field-help">Sample author · 11 Oct 2026. Permanent notes retain attribution and cannot be edited or deleted.</p></div>
<x-app.guide-divider />
<div class="app-content"><h2>Empty and loading states</h2><p>No items match these filters. Try another collection or location.</p><p aria-busy="true">Loading sample results… <span class="app-search-spinner" aria-hidden="true"></span></p></div>
<x-app.guide-divider />
<div class="app-content"><h2>Account confirmation and authentication feedback</h2><p>Email confirmation: <strong class="app-confirmation-status">Confirmed</strong> or <strong class="app-confirmation-status app-confirmation-unconfirmed">Not confirmed</strong>. Profile pages use bold text, with unconfirmed status also red.</p><p class="error">This account has been disabled. Contact your administrator.</p><p class="error">Sign-in could not be completed. Please start again.</p><p>Disabled accounts have their specific message. Other failed sign-ins keep the generic restart message.</p></div>
<x-app.guide-divider />
<div class="app-content"><h2>Role action group</h2><div class="actions app-user-actions"><div class="inline"><button type="button" data-guide-open="guide-role-dialog" data-guide-role-label="Grant admin" data-guide-role-message="Grant the admin role to Sample Administrator?">Grant admin</button></div><div class="inline"><button type="button" data-guide-open="guide-role-dialog" data-guide-role-label="Remove manager" data-guide-role-message="Remove the manager role from Sample Administrator?">Remove manager</button></div><div class="inline"><button type="button" data-guide-open="guide-role-dialog" data-guide-role-label="Grant procurement" data-guide-role-message="Grant the procurement role to Sample Administrator?">Grant procurement</button></div><div class="inline app-user-disable-action"><button type="button" class="discard" data-guide-open="guide-disable-dialog">Disable account</button></div></div><p>Related role actions align left; the destructive account action aligns right. Confirmation behavior is documented under <a href="/design/phase-a/notifications#confirmations">Notifications</a>.</p><p id="guide-disable-result" class="notice" role="status" hidden></p></div>
@include('design.phase-a.disable-preview')
@endsection
