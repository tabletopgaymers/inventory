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
    <div class="app-page-heading app-heading-with-actions"><div class="app-title-row"><h3>Pronoun ribbons</h3><x-app.status-badge status="Active" /></div><div class="app-actions"><a class="button" href="/style-guide/forms">Edit</a></div></div>
    <div class="app-page-heading app-heading-with-actions"><div class="app-title-row"><h3>Edit collection - Pronoun ribbons</h3><x-app.status-badge status="Active" /></div><div class="app-actions"><button type="button">Cancel</button><button type="button" class="primary">Save</button></div></div>
</section>
<x-app.guide-divider />
<section id="basic-panel" aria-labelledby="basic-panel-title">
    <h2 id="basic-panel-title">Basic panel</h2>
    <div class="app-content">
        <h3>Preparing a collection</h3>
        <p>Give the collection a clear name and description. Its category determines where it appears in the catalog.</p>
        <p>Choose a SKU prefix before adding items. <a href="/style-guide/forms">See the form-field examples</a> for input and validation guidance.</p>
    </div>
</section>
<x-app.guide-divider />
<section id="table-panel" aria-labelledby="table-panel-title">
    <h2 id="table-panel-title">Table panel</h2>
    <p>A table panel presents tabular information without outer padding. Search, filters and surrounding page actions are separate components. Optional header and footer slots can contain brief context or actions specifically associated with the table.</p>
    <h3>Plain table panel</h3>
    @include('design.phase-a.table-sample', ['tablePanel' => true, 'tableLabel' => 'Plain grouped inventory table'])
    <h3>Table panel with optional header and footer</h3>
    <p>Use a header, a footer, both or neither. Their content is unrestricted: headings, descriptions and buttons are examples, not requirements. Omitted slots create no empty sections. Header and footer padding aligns with table cells; the table keeps square internal corners within the rounded outer panel.</p>
    @include('design.phase-a.table-sample', ['tablePanel' => true, 'tableLabel' => 'Grouped inventory table with optional sections', 'tableSections' => true])
    <h3>Table conventions</h3>
    <ul>
        <li>Use linked record names for navigation. Keep action buttons and disclosure controls outside table rows.</li>
        <li>Keep each cell to a single line of information; put secondary details on the record view. Allow natural wrapping on narrow screens rather than clipping content.</li>
        <li>Column headers use bold text (700) on darker lavender (#e5dfef). Group headers use semibold text (600) on pale lavender (#f4f2f8).</li>
        <li>Use the normal dark text color for group headings; purple indicates a link.</li>
        <li>Indent record names beneath group headers with the shared <code>app-grouped-item</code> style. Its padding comes from <code>--app-grouped-item-padding</code>, so changes apply globally.</li>
    </ul>
    <div class="app-content" id="compact-nonwrapping-tables">
        <h4>For consideration: compact, nonwrapping tables</h4>
        <p>Jeff liked this pattern while refining Relocation on October 14, 2026. It is implemented in the singular Relocation Material and Review tables and is a candidate for reuse, not a change to every table or the wrapping convention above. When asked for this pattern, use the following details together.</p>
        <ul>
            <li>Keep all column titles, group subheaders, item names and values on one line. Apply <code>white-space: nowrap</code> to the table's <code>th</code> and <code>td</code> through a table class; individual cells do not need inline styles. Keep the table inside the shared table-panel component so narrow screens scroll horizontally rather than wrap, clip or shrink the text.</li>
            <li>Let the Item column take the remaining space without assigning it a width. Use compact percentage widths for the other columns. Percentages are layout targets; the browser may expand columns to fit their nonwrapping content.</li>
            <li>For editable requested material, use columns in this order: delete, Item, Requested, Fulfilled, Received. Set the delete column to 1% and each quantity column to 5%, preferably with a <code>colgroup</code>. Quantity inputs are about 10 characters wide (<code>10ch</code>), not stretched to fill the row. Fulfilled and Received are disabled at the request stage and are skipped when tabbing.</li>
            <li>The delete action is a small bold red ✕ in the first column, with an item-specific accessible label. Category : Collection subheaders reserve an empty first cell for that column and span the remaining four columns. Item names beneath them use the existing grouped-item indentation; do not display the SKU beside the name.</li>
            <li>For the read-only Review table, use Item, a three-column Source group, Requested, and a three-column Destination group. Each location's actual name is a centered heading spanning its three columns; omit “stock.” Requested is also centered. Each projection group has current quantity right-aligned, → centered, and projected quantity right-aligned. Target 7% for each numeric projection column, 2% for each arrow column, and 8% for Requested; tighten only horizontal projection padding so the values stay together. Group subheaders span all eight columns.</li>
            <li>Source projection is current minus requested; destination projection is current plus requested. These are hypothetical previews and do not reserve or move inventory. Format numbers with thousands separators. Negative values retain the minus sign and use the shared danger red. Invalid requested quantities highlight the affected row using the error message's text/background colors.</li>
            <li>Use shared table-panel header/footer slots for headings and associated actions. Preserve Display Density for vertical row spacing, input padding and panel padding; compact column widths and horizontal spacing do not require smaller text or fixed row heights.</li>
        </ul>
        <p>Implementation references: <code>resources/views/relocation/material-table.blade.php</code> defines the shared Material/Fulfillment table; <code>resources/views/relocation/work.blade.php</code> defines the Review table; <code>resources/css/workflow/tables.css</code> and <code>resources/css/relocation.css</code> own shared table rules and domain widths; <code>resources/js/relocation.js</code> controls editability, grouped rows and projections. These are in the application checkout. This proposal includes the small row delete action as a specific exception to the general table-action convention above.</p>
    </div>
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
