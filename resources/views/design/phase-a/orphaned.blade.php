@extends('design.phase-a.layout')
@section('title', 'Orphaned examples')
@section('intro', 'Temporary holding page for examples outside the three main component pages.')
@section('guide-content')
<div class="app-content"><h2>Record details</h2><x-app.detail-list variant="content" ><dt>Name</dt><dd>Sample Administrator</dd><dt>MS account</dt><dd>sample@example.test</dd><dt>Elevated roles</dt><dd>admin, manager, procurement</dd><dt>Time zone</dt><dd>America/Chicago</dd></x-app.detail-list><p>Use a definition list for labeled values. See the complete <a href="/style-guide/examples#users">user example</a>.</p></div>
<x-app.guide-divider />
<div class="app-content"><h2>Permanent notes</h2><p>Sample note: checked the convention kit.</p><p class="app-field-help">Sample author · 11 Oct 2026. Permanent notes retain attribution and cannot be edited or deleted.</p></div>
<x-app.guide-divider />
<div class="app-content"><h2>Empty and loading states</h2><p>No items match these filters. Try another collection or location.</p><p aria-busy="true">Loading sample results… <span class="app-search-spinner" aria-hidden="true"></span></p></div>
<x-app.guide-divider />
<div class="app-content"><h2>Authentication feedback</h2><p class="error">This account has been disabled. Contact your administrator.</p><p class="error">Sign-in could not be completed. Please start again.</p><p>Disabled accounts have their specific message. Other failed sign-ins keep the generic restart message.</p></div>
<x-app.guide-divider />
<div class="app-content"><h2>Role action group</h2><div class="actions app-user-actions"><div class="inline"><x-app.action type="button" data-guide-open="guide-role-dialog" data-guide-role-label="Grant admin" data-guide-role-message="Grant the admin role to Sample Administrator?">Grant admin</x-app.action></div><div class="inline"><x-app.action type="button" data-guide-open="guide-role-dialog" data-guide-role-label="Remove manager" data-guide-role-message="Remove the manager role from Sample Administrator?">Remove manager</x-app.action></div><div class="inline"><x-app.action type="button" data-guide-open="guide-role-dialog" data-guide-role-label="Grant procurement" data-guide-role-message="Grant the procurement role to Sample Administrator?">Grant procurement</x-app.action></div><div class="inline app-user-disable-action"><x-app.action variant="danger" type="button"  data-guide-open="guide-disable-dialog">Disable account</x-app.action></div></div><p>Related role actions align left; the destructive account action aligns right. Confirmation behavior is documented under <a href="/style-guide/notifications#confirmations">Notifications</a>.</p><p id="guide-disable-result" class="notice" role="status" hidden></p></div>
@endsection
