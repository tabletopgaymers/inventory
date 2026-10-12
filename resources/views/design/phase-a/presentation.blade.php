@extends('design.phase-a.layout')
@section('title', 'Presentation')
@section('intro', 'Typography, spacing, headings and information surfaces.')
@section('guide-content')
<nav class="app-section-nav" aria-label="On this page">
    <a href="#spacing">Spacing</a><a href="#titles">Titles</a><a href="#basic-panel">Basic panel</a><a href="#table-panel">Table panel</a><a href="#columns">Responsive columns</a><a href="#statuses">Status indicators</a>
</nav>
<section id="spacing" aria-labelledby="spacing-title">
<div class="app-content">
    <h2 id="spacing-title">Spacing and display density</h2>
    <p>Users choose Compact, Standard or Spacious in their profile. Standard is the default. The shared density settings control panel padding, line height, field spacing and table-row spacing throughout the application.</p>
    <ul>
        <li>Use the shared font family and base font size; do not override them on individual pages or components.</li>
        <li>Use density variables for vertical spacing instead of independent padding or margin values.</li>
        <li>Keep consistent panel padding and align action groups with related information. Buttons retain their internal padding.</li>
        <li>Use the shared heading hierarchy; do not enlarge ordinary text to create headings.</li>
        <li>Let content grow with the page. Avoid internal vertical scrolling for table results.</li>
        <li>Display density belongs to the viewer; print worksheets retain their established spacing.</li>
    </ul>
    <h3>Settings</h3>
    <p class="app-field-help">This table reads the loaded stylesheet for all three presets. Relative values remain in rem; rendered measurements use pixels at the current viewport and zoom. Your saved density is unchanged.</p>
</div>
@include('design.phase-a.density-settings')
</section>
<x-app.guide-divider />
<section id="titles" aria-labelledby="titles-title">
    <h2 id="titles-title">Titles</h2>
    <p>A page title identifies the page or record. A simple title needs no status or actions.</p>
    <div class="app-page-heading"><div class="app-title-row"><h3>Inventory</h3></div></div>
    <h3>Titles with status and actions</h3>
    <p>Place record status immediately after the title and key actions on the right. Show only actions available to the user; keep the primary action last. Section examples below use lower-level headings to preserve the page hierarchy.</p>
    <div class="app-page-heading app-heading-with-actions"><div class="app-title-row"><h3>Pronoun ribbons</h3><x-app.status-badge status="Active" /></div><div class="app-actions"><a class="button" href="/design/phase-a/forms">Edit</a></div></div>
    <div class="app-page-heading app-heading-with-actions"><div class="app-title-row"><h3>Edit collection - Pronoun ribbons</h3><x-app.status-badge status="Active" /></div><div class="app-actions"><button type="button">Cancel</button><button type="button" class="primary">Save</button></div></div>
</section>
<x-app.guide-divider />
<section id="basic-panel" aria-labelledby="basic-panel-title">
    <h2 id="basic-panel-title">Basic panel</h2>
    <div class="app-content">
        <h3>Preparing a collection</h3>
        <p>Give the collection a clear name and description. Its category determines where it appears in the catalog.</p>
        <p>Choose a SKU prefix before adding items. <a href="/design/phase-a/forms">See the form-field examples</a> for input and validation guidance.</p>
    </div>
</section>
<x-app.guide-divider />
<section id="table-panel" aria-labelledby="table-panel-title">
    <h2 id="table-panel-title">Table panel</h2>
    <p>The table is the entire panel, without outer padding. Put search and filters in their own padded panel above it. Put related actions above the table and helper text below it, directly on the page.</p>
    <ul>
        <li>Use linked record names for navigation. Keep action buttons and disclosure controls outside table rows.</li>
        <li>Keep each cell to a single line of information; put secondary details on the record view. Allow natural wrapping on narrow screens rather than clipping content.</li>
        <li>Column headers use bold text (700) on darker lavender (#e5dfef). Group headers use semibold text (600) on pale lavender (#f4f2f8).</li>
        <li>Use the normal dark text color for group headings; purple indicates a link.</li>
        <li>Indent record names beneath group headers with the shared <code>app-grouped-item</code> style. Its padding comes from <code>--app-grouped-item-padding</code>, so changes apply globally.</li>
    </ul>
    <div class="app-content"><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,16rem),1fr));gap:1rem 2rem"><label>Search<input type="search" placeholder="Find an item"></label><label>Location<select><option>All storage</option><option>Central</option><option>Sample storage</option></select></label></div></div>
    <div class="app-actions"><button type="button" class="app-new-action">+ New</button></div>
    @include('design.phase-a.table-sample', ['tablePanel' => true, 'tableLabel' => 'Grouped inventory table'])
    <p class="app-field-help">Fictional quantities. All retained items are shown, including inactive and archived records.</p>
    <div class="app-actions"><button type="button">Download sample</button></div>
</section>
<x-app.guide-divider />
<section id="columns" aria-labelledby="columns-title">
    <h2 id="columns-title">Responsive columns</h2>
    <p>A panel can arrange related information in two or more columns when there is room, then stack them as its available width narrows. The toggle changes only this example.</p>
    <div class="app-actions"><button type="button" data-guide-width-toggle aria-controls="guide-column-panel" aria-pressed="false">Show narrow panel</button></div>
    <div class="app-content" id="guide-column-panel" style="width:100%">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,16rem),1fr));gap:1rem 2rem">
            <section><h3>Collection</h3><p>Pronoun ribbons</p><p>Category: Ribbon</p><a href="#table-panel">View the sample table</a></section>
            <section><h3>Inventory</h3><p>370 available</p><p>300 at Central</p><p>Review quantities before requesting a relocation.</p></section>
        </div>
    </div>
</section>
<x-app.guide-divider />
<section id="statuses" aria-labelledby="statuses-title">
    <h2 id="statuses-title">Status indicators</h2>
    <div class="app-content">
        <p>Status badges combine a short text label with a color. Use the established workflow states; color supplements the label and never replaces it.</p>
        <div style="display:flex;flex-wrap:wrap;gap:.5rem">@foreach(['Draft','Request','Requested','Active','Ordered','Shipped','Receiving','Received','Finalized','Complete','Cancelled','Inactive','Archived'] as $status)<x-app.status-badge :status="$status" />@endforeach</div>
        <p>Place status beside a record title and in a dedicated table column. A workflow's editing step does not create a new business status.</p>
        <div class="app-title-row"><h3>Sample collection</h3><x-app.status-badge status="Active" /></div>
    </div>
    <x-app.table-region label="Status column example" class="app-list-table-panel"><table><thead><tr><th>Collection</th><th>Status</th></tr></thead><tbody><tr><td>Pronoun ribbons</td><td><x-app.status-badge status="Active" /></td></tr><tr><td>Retired ribbons</td><td><x-app.status-badge status="Archived" /></td></tr></tbody></table></x-app.table-region>
</section>
@endsection
