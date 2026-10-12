@extends('design.phase-a.layout')
@section('title', 'General elements')
@section('intro', 'Shared application components and their current visual states.')
@section('guide-content')
<nav class="app-section-nav" aria-label="On this page">
    <a href="#foundation">Panels & spacing</a><a href="#headers">Headings & navigation</a><a href="#actions">Buttons & status</a><a href="#fields">Fields & validation</a><a href="#data">Tables & records</a><a href="#feedback">Notices & modal</a>
</nav>
<div class="app-content" id="foundation">
    <h2>Panels and spacing</h2>
    <p>Each panel uses the same shared padding. Keep outside control margins out of action panels; preserve padding inside every button. Align the first button's outer edge with the information above.</p>
    <p class="app-field-help">The comparison reads the loaded stylesheet for every density preset. Relative CSS values remain in rem; rendered component measurements use pixels at the current viewport and zoom. Your saved density is unchanged.</p>
</div>
<x-app.table-region label="Display density spacing comparison" class="app-list-table-panel">
<table><thead><tr><th>Setting</th><th>Compact</th><th>Standard</th><th>Spacious</th></tr></thead><tbody>
@foreach([
    'panel' => 'Panel padding (current viewport)',
    '--app-density-panel-y' => 'Panel vertical padding — desktop',
    '--app-density-panel-mobile-y' => 'Panel vertical padding — narrow screen',
    'body' => 'Body font size / line height',
    '--app-density-paragraph-y' => 'Paragraph vertical margin',
    '--app-density-field-y' => 'Field vertical margin',
    '--app-density-input-y' => 'Input/select vertical padding',
    '--app-density-table-y' => 'Table-cell vertical padding',
    '--app-density-catalog-y' => 'Catalog-cell vertical padding',
    '--app-density-detail-gap' => 'Detail-list vertical gap',
    'button' => 'Button padding',
    'primary' => 'Primary color',
    'page' => 'Page background',
    'surface' => 'Panel background',
] as $key => $label)
<tr><th scope="row">{{ $label }}</th>@foreach(['compact', 'standard', 'spacious'] as $density)<td data-guide-density="{{ $density }}" data-guide-measure="{{ $key }}">Loading styles…</td>@endforeach</tr>
@endforeach
</tbody></table>
</x-app.table-region>
<div class="app-content app-record-content">
</div>
<div class="app-content">
    <div class="actions app-user-actions">
        <div class="inline"><button type="button" id="guide-padding-button" data-guide-open="guide-role-dialog" data-guide-role-label="Grant admin" data-guide-role-message="Grant the admin role to Sample Administrator?">Grant admin</button></div>
        <div class="inline"><button type="button" data-guide-open="guide-role-dialog" data-guide-role-label="Remove manager" data-guide-role-message="Remove the manager role from Sample Administrator?">Remove manager</button></div>
        <div class="inline"><button type="button" data-guide-open="guide-role-dialog" data-guide-role-label="Grant procurement" data-guide-role-message="Grant the procurement role to Sample Administrator?">Grant procurement</button></div>
        <div class="inline app-user-disable-action"><button type="button" class="discard" data-guide-open="guide-disable-dialog" aria-haspopup="dialog" aria-controls="guide-disable-dialog">Disable account</button></div>
    </div>
</div>
<x-app.guide-divider />
<div class="app-content" id="headers">
    <h2>Headings and navigation</h2>
    <div class="section-heading"><div class="app-title-row"><h3>Sample request</h3><span class="status-badge draft">Draft</span></div><a href="#fields" class="button">Edit</a></div>
    <p>A record heading identifies the person or request. Add only relevant permitted actions; the user heading uses Edit for Admin or the owner, without an All users link.</p>
    <p>Use navigation for destinations and prior reachable steps. The shared app frame above demonstrates the current header, account menu and environment box.</p>
    <h3>Environment color specimens</h3>
    <p class="app-field-help">These are examples, not a change to your active environment.</p>
    </div>
<x-app.table-region class="app-list-table-panel" label="Environment color examples">
        <table><thead><tr><th>Environment</th><th>Label and background</th></tr></thead><tbody>
        <tr><td>Local</td><td><div class="app-environment-box app-environment-local"><span>LOCAL</span><span>DEVELOPMENT</span></div></td></tr>
        <tr><td>Remote development</td><td><div class="app-environment-box app-environment-development"><span>REMOTE</span><span>ENVIRONMENT</span></div></td></tr>
        <tr><td>Testing</td><td><div class="app-environment-box app-environment-testing"><span>TESTING</span><span>ENVIRONMENT</span></div></td></tr>
        <tr><td>Staging</td><td><div class="app-environment-box app-environment-staging"><span>STAGE</span><span>ENVIRONMENT</span></div></td></tr>
        </tbody></table>
    </x-app.table-region>
<div class="app-content app-record-content">
</div>
<x-app.guide-divider />
<div class="app-content" id="actions">
    <h2>Buttons and status</h2>
    <div class="actions app-user-actions">
        <button type="button" class="primary">Save</button><a class="button" href="#fields">Edit</a><a class="button app-new-action" href="#data">+New</a><button type="button" class="discard">Discard</button><button type="button" disabled>Unavailable</button>
        <button type="button" class="primary" disabled>Unavailable primary action</button><button type="button" class="discard" disabled>Unavailable destructive action</button><x-app.action variant="primary" :disabled="true">Unavailable component action</x-app.action>
    </div>
    <p class="app-field-help">Disabled fields and buttons use a light grey background with black text at full opacity. Save progress buttons keep their existing state colors and disabled appearance.</p>
    <h3>Save progress states</h3>
    <div class="actions app-user-actions">
        <button type="button" class="primary app-save-progress">Save progress</button>
        <button type="button" class="primary app-save-progress app-save-dirty">Save progress — unsaved</button>
        <button type="button" class="primary app-save-progress" aria-busy="true">Saving… <span class="app-search-spinner" aria-hidden="true"></span></button>
        <button type="button" class="primary app-save-progress" disabled>Save progress — unavailable</button>
        <button type="button" class="primary app-save-progress app-save-dirty" disabled>Save progress — unsaved and unavailable</button>
    </div>
    <p class="app-field-help">Displayed states only; no save is running. Actual Save progress stays on the current step, and submission/confirmation remains a separate action.</p>
    <h3>Business status</h3>
    <p>@foreach(['Draft','Request','Requested','Active','Ordered','Shipped','Receiving','Received','Finalized','Complete','Cancelled','Inactive','Archived'] as $status)<span class="status-badge {{ strtolower($status) }}">{{ $status }}</span> @endforeach</p>
    <p>Text always accompanies color. These labels belong to their existing workflows; editing steps do not create new business states.</p>
</div>
<x-app.guide-divider />
<div class="app-content" id="fields">
    <h2>Fields and validation</h2>
    <p>Email confirmation status: <strong class="app-confirmation-status">Confirmed</strong> or <strong class="app-confirmation-status app-confirmation-unconfirmed">Not confirmed</strong>. Profile editing and viewing use bold status text, with unconfirmed status also red.</p>
    <label>Title<input name="guide_title" value="Sample supply request" required></label>
    <label>Details (optional)<textarea name="guide_details" rows="2">New display stands, quantity 2.</textarea></label>
    <label>Storage location<select name="guide_location"><option>Central</option><option>Sample storage</option></select></label>
    <label>Requested units — blank<input name="guide_blank_quantity" inputmode="numeric" aria-describedby="guide-blank-help"></label>
    <p id="guide-blank-help" class="app-field-help">No quantity entered; blank is not zero.</p>
    <label>Requested units — explicit zero<input name="guide_zero_quantity" inputmode="numeric" value="0"></label>
    <label>MS account<input value="sample@example.test" readonly></label>
    <label>Recorded date<input type="date" value="2026-10-11"></label>
    <label>Unit cost (USD)<input value="0.125" readonly></label>
    <label>Unavailable input<input value="Unavailable" disabled></label>
    <label>Unavailable selection<select disabled><option>Sample storage</option></select></label>
    <label>Unavailable details<textarea rows="2" disabled>Saved details cannot be edited here.</textarea></label>
    <fieldset disabled><legend>Unavailable field group</legend><label>Grouped input<input value="Unavailable through its group"></label><label class="app-choice"><input type="checkbox" checked> Unavailable checkbox</label><label class="app-choice"><input type="radio" checked> Unavailable radio</label></fieldset>
    <div class="error"><a href="#guide-quantity">Quantity: enter a whole number.</a></div>
    <label>Quantity — error example<input id="guide-quantity" value="two" aria-invalid="true" aria-describedby="guide-quantity-error"></label>
    <p class="app-field-error" id="guide-quantity-error">Enter a whole quantity.</p>
    <fieldset><legend>Collections</legend><label class="app-choice"><input type="checkbox" checked> Ribbon : Pronoun</label><label class="app-choice"><input type="checkbox"> Pin : Sample collection</label></fieldset>
    <fieldset><legend>Price entry</legend><label class="app-choice"><input type="radio" name="guide_price_basis" checked> Unit price</label><label class="app-choice"><input type="radio" name="guide_price_basis"> Line total</label></fieldset>
    <p class="app-field-help">Labels stay visible. Error text names the affected field, links to it and preserves entered work. These sample fields have no submission.</p>
</div>
<x-app.guide-divider />
<div class="app-content" id="data">
    <h2>Tables and records</h2>
    <p>Use tables for lists and comparisons. Group items beneath Category : Collection headers, indent item names and retain accessible collection/SKU context on controls.</p>
    </div>
<x-app.table-region class="app-list-table-panel" label="Grouped inventory example">
        <table><thead><tr><th>Item</th><th class="number">Central</th><th class="number">Sample storage</th></tr></thead><tbody>
        <tr class="collection-heading"><th colspan="3">Ribbon : Pronoun</th></tr>
        <tr><td class="app-grouped-item">They/Them</td><td class="number">250</td><td class="number">20</td></tr>
        <tr><td class="app-grouped-item">Any/All</td><td class="number">0</td><td class="number">80</td></tr>
        </tbody></table>
    </x-app.table-region>
<div class="app-content app-record-content">
    <h3>Borderless record details</h3>
    <dl class="app-user-details"><dt>Name</dt><dd>Sample Administrator</dd><dt>TG email</dt><dd>sample@tabletopgaymers.org (confirmed)</dd><dt>MS account</dt><dd>sample@example.test</dd><dt>Ext roles</dt><dd>admin, manager, procurement</dd><dt>Time zone</dt><dd>America/Chicago</dd></dl>
    <p>Use a definition list for field/value information, with bold labels and wrapping values. See the complete <a href="/design/phase-a/examples#users">two-panel user example</a>.</p>
    <h3>Permanent notes</h3><p>Sample note: checked the convention kit.</p><p class="app-field-help">Sample author · 11 Oct 2026. Actual permanent notes retain attribution and cannot be edited or deleted.</p>
    <h3>Empty and loading states</h3><p>No items match these filters. Try another collection or location.</p>
    <p aria-busy="true">Loading sample results… <span class="app-search-spinner" aria-hidden="true"></span></p>
</div>
<x-app.guide-divider />
<div class="app-content" id="feedback">
    <h2>Notices and modal confirmation</h2>
    <p class="notice">Changes saved. Sample success notice.<br>This multiline message reserves room beside the dismiss control, keeping text and links readable. <a href="#fields">View the field examples</a>.</p>
    <p class="warning">Working changes have not been saved. Sample warning notice.</p>
    <p class="error">This account has been disabled. Contact your administrator.</p>
    <p class="error">Sign-in could not be completed. Please start again.</p>
    <p class="app-field-help">Disabled accounts have their specific message. Other failed sign-ins keep the generic restart message.</p>
    <p class="app-field-help">Use ✕ to dismiss a non-error notification for this page. The control matches the message's text color; errors remain visible. A new message can appear again after dismissal.</p>
    <p>Disable account opens an in-page modal titled “Are you sure?”. Cancel is dark grey on white; confirmation is white on red. Cancel and Escape return focus to the trigger.</p>
    <button type="button" class="discard" data-guide-open="guide-disable-dialog" aria-haspopup="dialog" aria-controls="guide-disable-dialog">Preview disable account</button>
    <p id="guide-disable-result" class="notice" role="status" hidden></p>
</div>
@include('design.phase-a.disable-preview')
@endsection
