@extends('design.phase-a.layout')
@section('title', 'Example uses')
@section('intro', 'Current patterns composed into sample screens.')
@section('guide-content')
<nav class="app-section-nav" aria-label="On this page"><a href="#users">Users</a><a href="#search">Inventory</a><a href="#item">Item details</a><a href="#requests">Requests</a><a href="#counts">Counts & print</a><a href="#supporting">Catalog & events</a><a href="#wrapper">Application frame</a></nav>
<x-app.table-region id="users" class="app-list-table-panel" label="Sample user directory"><x-slot:header><h2>Informative user directory</h2><p>List information here; put permitted actions on the linked record page. Basic is omitted from Elevated roles.</p></x-slot:header><table><thead><tr><th>Name</th><th>Email</th><th>Elevated roles</th></tr></thead><tbody>
        <tr><td><a href="#guide-user">Sample Administrator</a></td><td>sample@example.test</td><td>admin, manager, procurement</td></tr>
        <tr><td><a href="#profile-example">Sample Volunteer</a></td><td>volunteer@example.test</td><td></td></tr>
    </tbody></table></x-app.table-region>
<x-app.guide-divider />
@include('design.phase-a.user-example')
<x-app.guide-divider />
<div class="app-content" id="profile-example">
<x-workflow.heading><h2>Profile editing</h2><div class="app-actions"><x-app.action variant="danger" type="button" >Discard</x-app.action><x-app.action variant="primary" type="button" >Save</x-app.action></div></x-workflow.heading>
    <div class="app-columns app-columns-two app-columns-trim">
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
    <x-workflow.heading><h2>Inventory search</h2><x-app.action variant="new"  href="#supporting">＋ New</x-app.action></x-workflow.heading>
    <fieldset class="app-entry-fieldset" disabled>
    <x-app.inventory-search :definition="['filters'=>[], 'hiddenFilters'=>[], 'defaults'=>['search'=>'','collections'=>[],'columns'=>[]], 'columnGroups'=>['locations'=>[]]]" endpoint="/style-guide/inventory-search/results?profile=full" instance="example-inventory-search" :script="false" />
    </fieldset>
    <p class="app-field-help">Controls and results here are fictional and inactive. <a href="/style-guide/inventory-search">Try the working search demonstration</a>.</p>
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
    <div class="app-title-row"><h2>They/Them</h2><x-app.status-badge status="Active" /></div>
    <p>Ribbon : Pronoun</p>
    <x-app.detail-list variant="fixed" ><dt>Variety</dt><dd>Sample ribbon</dd><dt>Purpose</dt><dd>Convention kit</dd><dt>Notes</dt><dd>Illustrative item metadata.</dd></x-app.detail-list>
    <h3>Recorded activity</h3></div>
<x-app.table-region class="app-list-table-panel" label="Sample recorded activity"><table><thead><tr><th>Recorded</th><th>Action</th><th class="number">Units</th></tr></thead><tbody><tr><td>11 Oct 2026</td><td>Sample receipt</td><td class="number">250</td></tr></tbody></table></x-app.table-region>
<div class="app-content app-record-content">
    <p class="app-field-help">Actual history retains its chronology and actor attribution.</p>
</div>
<x-app.guide-divider />
<div class="app-content" id="requests">
    <x-workflow.heading><div class="app-title-row"><h2>Sample purchase</h2><x-app.status-badge status="Draft" /></div><x-workflow.save-progress disabled /></x-workflow.heading>
    <p>Workflow navigation sits outside the content panels. Save progress preserves edits; submission and final confirmation are separate actions. These sample controls do not save.</p>
</div>
<x-workflow.steps label="Sample purchase steps">
    @foreach(['Basic info','Material','Review','Procurement','Order','Order charges','Order review'] as $label)<x-app.action  href="#requests" :aria-current="$label === 'Material' ? 'page' : null">{{ $label }}</x-app.action>@endforeach
</x-workflow.steps>
<x-app.table-region class="app-list-table-panel" label="Sample requested purchase material">
<x-slot:header><x-workflow.heading><h2>Requested material</h2><x-app.action variant="primary" type="button"  :disabled="true">Next ▷</x-app.action></x-workflow.heading></x-slot:header>
<table><thead><tr><th>Item</th><th class="table-numeric">Qty</th><th class="table-numeric">Est. unit price</th><th class="table-numeric">Est. total</th></tr></thead><tbody><tr class="group-row"><th colspan="4">Other items</th></tr><tr><td class="app-grouped-item">Sample display stand</td><td class="table-numeric">2</td><td class="table-numeric">1.25</td><td class="table-numeric">2.50</td></tr></tbody></table>
<x-slot:footer><p>Catalog and Other items remain on the request. Procurement records actual order costs separately; receiving requires a final confirmation.</p></x-slot:footer>
</x-app.table-region>
<div class="app-content"><h2>Sample relocation</h2><p>The same navigation component presents the relocation stages. Stage availability and quantity editing remain workflow rules.</p></div>
<x-workflow.steps label="Sample relocation steps">
    @foreach(['Basic info','Material','Review','Fulfillment','Tracking','Ship','Reconcile','Confirmation'] as $label)<x-app.action  href="#requests" :aria-current="$label === 'Material' ? 'page' : null">{{ $label }}</x-app.action>@endforeach
</x-workflow.steps>
<x-app.guide-divider />
<x-app.table-region id="counts" class="app-list-table-panel" label="Sample count worksheet"><x-slot:header><h2>Counts and printable information</h2></x-slot:header><table><thead><tr><th>Item</th><th class="number">Expected</th><th>Counted units</th></tr></thead><tbody><tr class="collection-heading"><th colspan="3">Ribbon : Pronoun</th></tr><tr><td class="app-grouped-item">They/Them</td><td class="number">250</td><td><input inputmode="numeric" aria-label="Counted units for They/Them · Pronoun · SKU SAMPLE-THEY" value="0"></td></tr></tbody></table></x-app.table-region>
<div class="app-content app-record-content">
    <p>Blank is incomplete; zero is an explicit value. Actual count/receipt review and confirmation keep their existing effects and guards.</p>
    <p class="app-field-help">Print uses white surfaces and hides application navigation/actions. This guide does not submit counts or start printing automatically.</p>
</div>
<x-app.guide-divider />
<div class="app-content" id="supporting">
    <h2>Catalog and event variants</h2>
    <x-workflow.heading><h3>Catalog · Collections</h3><x-app.action variant="new"  href="#supporting">＋ New</x-app.action></x-workflow.heading>
    </div>
<x-app.table-region class="app-list-table-panel" label="Sample catalog collection"><table><thead><tr><th>Collection</th><th>Category</th><th>Status</th></tr></thead><tbody><tr><td>Pronoun</td><td>Ribbon</td><td><x-app.status-badge status="Active" /></td></tr></tbody></table></x-app.table-region>
<div class="app-content app-record-content">
    <h3>Sample event</h3><p><x-app.status-badge status="Active" /> Convention kit</p><label>Event name<input value="Sample convention"></label><label>Notes<textarea data-optional rows="2">Check the sample kit.</textarea></label>
    <p class="app-field-help">Editable fields, feedback and explicit workflow verbs share the application styles. Sample inputs do not save a catalog item or event.</p>
</div>
<x-app.guide-divider />
<div class="app-content" id="wrapper">
    <h2>Current application frame</h2><p>This page already uses the actual header, account menu, navigation, environment box and footer. Desktop navigation begins at 50rem (800px at the default font size); smaller widths use Menu.</p>
    @if(request()->getHost() === 'tg-inventory-app.test')<p><a href="/style-guide/wrapper">Open the focused application-frame example</a>.</p>@endif
</div>
@endsection
