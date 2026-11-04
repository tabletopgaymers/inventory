<div class="app-content" id="guide-user">
<x-workflow.heading><h2>Sample Administrator</h2><a class="button" href="#profile-example">Edit</a></x-workflow.heading>
    <div class="app-columns app-columns-two app-columns-trim">
    <div>
    <x-app.detail-list variant="content" >
        <dt>First name</dt><dd>Sample</dd>
        <dt>Last name</dt><dd>Administrator</dd>
        <dt>Elevated roles</dt><dd>admin, manager, procurement</dd>
        <dt>Microsoft account</dt><dd>sample@tabletopgaymers.org</dd>
        <dt>TG email</dt><dd>sample@tabletopgaymers.org</dd>
        <dt>TG email confirmation</dt><dd><strong class="app-confirmation-status">Confirmed</strong></dd>
    </x-app.detail-list>
    </div>
    <div>
    <x-app.detail-list variant="content" >
        <dt>Personal email</dt><dd>sample@example.test</dd>
        <dt>Mobile number</dt><dd>—</dd>
        <dt>Mailing address</dt><dd class="app-user-address">Sample Administrator
Illustrative address</dd>
        <dt>Time zone</dt><dd>America/Chicago</dd>
        <dt>Display density</dt><dd>Standard</dd>
    </x-app.detail-list>
    </div>
    </div>
</div>
<div class="app-content">
    <div class="actions app-user-actions">
        <div class="inline"><button type="button" id="guide-padding-button" data-guide-open="guide-role-dialog" data-guide-role-label="Remove admin" data-guide-role-message="Remove the admin role from Sample Administrator?">Remove admin</button></div>
        <div class="inline"><button type="button" data-guide-open="guide-role-dialog" data-guide-role-label="Remove manager" data-guide-role-message="Remove the manager role from Sample Administrator?">Remove manager</button></div>
        <div class="inline"><button type="button" data-guide-open="guide-role-dialog" data-guide-role-label="Remove procurement" data-guide-role-message="Remove the procurement role from Sample Administrator?">Remove procurement</button></div>
        <div class="inline app-user-disable-action"><button type="button" class="discard" data-guide-open="guide-disable-dialog" aria-haspopup="dialog">Disable account</button></div>
    </div>
    <p id="guide-disable-result" class="notice" role="status" hidden></p>
</div>
