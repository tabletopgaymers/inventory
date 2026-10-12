@extends('design.phase-a.layout')
@section('title', 'Example uses')
@section('intro', 'Current patterns composed into sample screens.')
@section('guide-content')
<nav class="app-section-nav" aria-label="On this page"><a href="#users">Users</a><a href="#search">Inventory</a><a href="#item">Item details</a><a href="#requests">Requests</a><a href="#counts">Counts & print</a><a href="#supporting">Catalog & events</a><a href="#wrapper">Application frame</a></nav>
<div class="app-content" id="users">
    <h2>Informative user directory</h2>
    <p>List information here; put permitted actions on the linked record page. Basic is omitted from Elevated roles.</p>
    </div>
<x-app.table-region class="app-list-table-panel" label="Sample user directory"><table><thead><tr><th>Name</th><th>Email</th><th>Elevated roles</th></tr></thead><tbody>
        <tr><td><a href="#guide-user">Sample Administrator</a></td><td>sample@example.test</td><td>admin, manager, procurement</td></tr>
        <tr><td><a href="#profile-example">Sample Volunteer</a></td><td>volunteer@example.test</td><td></td></tr>
    </tbody></table></x-app.table-region>
<div class="app-content app-record-content">
</div>
<x-app.guide-divider />
@include('design.phase-a.user-example')
<x-app.guide-divider />
<div class="section-heading" id="profile-example"><h2>Profile editing</h2><div class="app-actions"><button type="button" class="discard">Discard</button><button type="button" class="primary">Save</button></div></div>
<div class="app-content">
    <div class="app-profile-columns">
    <div>
    <label>First name<input required value="Sample"></label><label>Last name<input required value="Administrator"></label>
    <label>Elevated roles<input readonly value="admin, manager, procurement"></label>
    <label>Microsoft account<input readonly value="sample@tabletopgaymers.org"></label>
    <label>TG email<input data-optional type="email" value="sample@tabletopgaymers.org" placeholder="youremail@tabletopgaymers.org"></label>
    <div class="app-profile-confirmation">TG email confirmation</div>
    <label class="app-choice"><input type="checkbox"><span>Confirm my TG email address. Current status: <strong class="app-confirmation-status">Confirmed</strong></span></label>
    <p class="app-field-help">Changing TG email clears its previous confirmation. Each user confirms their own address.</p>
    </div>
    <div>
    <label>Personal email<input data-optional type="email" placeholder="youremail@yourprovider.com"></label>
    <label>Mobile number<input data-optional placeholder="555-555-1234"></label>
    <label>Mailing address<textarea data-optional rows="4" placeholder="Provide your mailing address, including your name, in a format appropriate to your location."></textarea></label>
    <label>Time zone<select><option>America/Chicago</option><option>America/New_York</option></select></label>
    <label>Display density<select><option>Compact</option><option selected>Standard</option><option>Spacious</option></select></label>
    </div>
    </div>
</div>
<x-app.guide-divider />
<div class="app-content" id="search">
    <div class="section-heading"><h2>Inventory search</h2><a class="button app-new-action" href="#supporting">+New</a></div>
    <div class="search-actions"><label>Search inventory<input type="search" placeholder="Item or collection"></label><button type="button">Search</button><button type="button">Show all</button><button type="button">Reset</button></div>
    </div>
<x-app.table-region class="app-list-table-panel" label="Sample inventory results"><table><thead><tr><th>Item</th><th class="number">Units</th><th class="number">Unit cost</th></tr></thead><tbody>
        <tr class="collection-heading"><th colspan="3">Ribbon : Pronoun</th></tr>
        <tr><td class="app-grouped-item"><a href="#item">They/Them</a></td><td class="number">250</td><td class="number">$0.125</td></tr>
        <tr><td class="app-grouped-item">Any/All</td><td class="number">0</td><td class="number">n/a</td></tr>
    </tbody></table></x-app.table-region>
<div class="app-content app-record-content">
    <p class="app-field-help">Sample values only. Results use their own table panel and grow with the page.</p>
</div>
<x-app.guide-divider />
<div class="app-content" id="item">
    <div class="app-title-row"><h2>They/Them</h2><span class="status-badge active">Active</span></div>
    <p>Ribbon : Pronoun</p>
    <dl class="item-metadata"><dt>Variety</dt><dd>Sample ribbon</dd><dt>Purpose</dt><dd>Convention kit</dd><dt>Notes</dt><dd>Illustrative item metadata.</dd></dl>
    <h3>Recorded activity</h3></div>
<x-app.table-region class="app-list-table-panel" label="Sample recorded activity"><table><thead><tr><th>Recorded</th><th>Action</th><th class="number">Units</th></tr></thead><tbody><tr><td>11 Oct 2026</td><td>Sample receipt</td><td class="number">250</td></tr></tbody></table></x-app.table-region>
<div class="app-content app-record-content">
    <p class="app-field-help">Actual history retains its chronology and actor attribution.</p>
</div>
<x-app.guide-divider />
<div class="app-content" id="requests">
    <nav class="app-relocation-progress" aria-label="Sample purchase progress"><ol class="app-relocation-steps"><li><a href="#requests">Request</a></li><li><strong aria-current="step">ITEMS</strong></li><li>Review</li></ol></nav>
    <div class="section-heading"><div class="app-title-row"><h2>Sample purchase</h2><span class="status-badge draft">Draft</span></div><button type="button" class="primary app-save-progress">Save progress</button></div>
    <label>If these are new items, describe them with their quantities here.<textarea rows="2">New display stands, quantity 2.</textarea></label>
    <p>Request → Items → plain Review. Title is required; description-only requests need no catalog lines. Requester and preparation use the same requested items.</p>
    <p class="app-field-help">Review has plain values with Cancel request / Submit request at the bottom, without Notes or preparation/invoice actions. Saving does not submit or post stock.</p>
    <div class="actions"><button type="button">Back: Request</button><button type="button" class="primary">Next: Review</button></div>
    <h3>Relocation progress</h3><nav aria-label="Sample relocation progress"><ol class="app-relocation-steps"><li><a href="#requests">Locations</a></li><li><strong aria-current="step">ITEMS</strong></li><li>Review</li></ol></nav>
    <p>Show the active step in bold capitals, prior reachable steps as links and future steps as plain text. Submitted workflows add their existing fulfillment stages.</p>
    <h3>Purchase order and receipt previews</h3>
    </div>
<x-app.table-region class="app-list-table-panel" label="Sample purchase pricing"><table><thead><tr><th>Item</th><th class="number">Units</th><th class="number">Calculated unit cost</th></tr></thead><tbody><tr><td>Sample display stand</td><td class="number">2</td><td class="number">$1.250000</td></tr></tbody></table></x-app.table-region>
<div class="app-content app-record-content">
    <p class="app-field-help">Purchasing keeps its calculation precision. Preparation estimates, ordered expectations and final actual receipt stay separate; review and confirmation are explicit. These sample values perform no calculation or posting.</p>
</div>
<x-app.guide-divider />
<div class="app-content" id="counts">
    <h2>Counts and printable information</h2>
    </div>
<x-app.table-region class="app-list-table-panel" label="Sample count worksheet"><table><thead><tr><th>Item</th><th class="number">Expected</th><th>Counted units</th></tr></thead><tbody><tr class="collection-heading"><th colspan="3">Ribbon : Pronoun</th></tr><tr><td class="app-grouped-item">They/Them</td><td class="number">250</td><td><input inputmode="numeric" aria-label="Counted units for They/Them · Pronoun · SKU SAMPLE-THEY" value="0"></td></tr></tbody></table></x-app.table-region>
<div class="app-content app-record-content">
    <p>Blank is incomplete; zero is an explicit value. Actual count/receipt review and confirmation keep their existing effects and guards.</p>
    <p class="app-field-help">Print uses white surfaces and hides application navigation/actions. This guide does not submit counts or start printing automatically.</p>
</div>
<x-app.guide-divider />
<div class="app-content" id="supporting">
    <h2>Catalog and event variants</h2>
    <div class="section-heading"><h3>Catalog · Collections</h3><a class="button app-new-action" href="#supporting">+New</a></div>
    </div>
<x-app.table-region class="app-list-table-panel" label="Sample catalog collection"><table><thead><tr><th>Collection</th><th>Category</th><th>Status</th></tr></thead><tbody><tr><td>Pronoun</td><td>Ribbon</td><td><span class="status-badge active">Active</span></td></tr></tbody></table></x-app.table-region>
<div class="app-content app-record-content">
    <h3>Sample event</h3><p><span class="status-badge active">Active</span> Convention kit</p><label>Event name<input value="Sample convention"></label><label>Notes<textarea data-optional rows="2">Check the sample kit.</textarea></label>
    <p class="app-field-help">Editable fields, feedback and explicit workflow verbs share the application styles. Sample inputs do not save a catalog item or event.</p>
</div>
<x-app.guide-divider />
<div class="app-content" id="wrapper">
    <h2>Current application frame</h2><p>This page already uses the actual header, account menu, navigation, environment box and footer. Desktop navigation begins at 800px; smaller widths use Menu.</p>
    @if(request()->getHost() === 'tg-inventory-app.test')<p><a href="/style-guide/wrapper">Open the focused application-frame example</a>.</p>@endif
</div>
@include('design.phase-a.disable-preview')
@endsection
