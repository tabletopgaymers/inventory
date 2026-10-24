@extends('design.phase-a.layout')
@section('title', 'Style guide')
@section('intro', 'A reference for the current application design.')
@section('guide-content')
<div class="app-content">
    <h2>Current application styles</h2>
    <p>This guide uses the same layout, stylesheet and shared components as the application. Changes to shared typography, panels, controls and navigation appear here too.</p>
    <p>It replaces the earlier standalone design proposal. Examples describe current decisions; they do not add roles, lifecycle states or business actions.</p>
</div>
<x-app.guide-divider />
<div class="app-content">
    <h2>Design decisions</h2>
    <ul>
        <li><strong>Sentence case:</strong> capitalize the first word of labels, table headers, buttons, links and headings. Keep proper names and acronyms capitalized: Microsoft account, TG email, SKU and IRS FMV. Write First name, Display density, Elevated roles, Grant admin and Disable account. Role names are ordinary words. Preserve the original case of names, user-entered content and stored identifiers.</li>
        <li><strong>Panels:</strong> white surfaces with shared padding for information and form controls. Tables form their own panels without outer padding; related helper text and actions can sit directly on the page.</li>
        <li><strong>Index/list tables:</strong> Catalog, Inventory browse, Users, Events, Purchases and Relocations use the table itself as the panel, without outer padding. Search/filter controls occupy separate padded panels. Column headers use bold (700) text on #e5dfef; grouped sub-headings use semibold (600), near-black text on #f4f2f8. Results grow with the page rather than scrolling inside a fixed-height region.</li>
        <li><strong>Display density:</strong> My profile saves Compact, Standard (the default) or Spacious for the viewer across devices. These adjust text line height, panel padding, field and paragraph spacing, input padding, table rows and detail-list gaps. Compact retains the previous appearance. Font sizes, horizontal spacing, buttons and navigation retain their sizing. Printed content keeps consistent compact spacing.</li>
        <li><strong>Action panels:</strong> controls align left with no extra row or form margins. Buttons keep normal internal padding. Destructive account actions align right.</li>
        <li><strong>Tables:</strong> informative lists with linked names; record pages own their actions. Keep action buttons, disclosure controls and secondary detail lines outside rows. Search and filters belong above the table in a separate panel.</li>
        <li><strong>User details:</strong> bold labels and aligned values in a borderless definition list. Contact confirmation, Microsoft account, elevated roles and time zone are distinct.</li>
        <li><strong>Buttons:</strong> purple Save, green +New, neutral Edit and a clearly separated destructive action. Disabled controls and saved/unsaved states are explicit.</li>
        <li><strong>Disabled controls:</strong> form fields and buttons use a light grey background with black text at full opacity. Save progress buttons retain their existing state styling.</li>
        <li><strong>Notifications:</strong> non-error banners have a top-right ✕ in the message's text color, with space reserved beside multiline text. Dismissal hides the current notice; new messages may appear again. Error banners remain visible.</li>
        <li><strong>Confirmation dialogs:</strong> role grants/removals and account disablement use modal confirmation dialogs with Cancel, the named action and focus returned to the trigger. Grant confirmations are purple; removal/disable confirmations are red. Users may remove their own manager/procurement roles, but another admin must remove their admin role or disable their account.</li>
        <li><strong>Navigation:</strong> Inventory, Relocations, Events, Purchases, Catalog, Users. Desktop begins at 800px; smaller screens use Menu. Local and development environments show a Style guide link at the bottom of the navigation, opening this overview.</li>
        <li><strong>Environment:</strong> local magenta, remote development red, testing orange and staging green. Production and unmapped environments show no box.</li>
        <li><strong>Requests:</strong> editing progress stays separate from business status. Save progress is beside the heading and preserves the current step.</li>
    </ul>
</div>
<x-app.guide-divider />
<div class="app-content">
    <h2>Explore the guide</h2>
    <p><a href="/style-guide/presentation">Presentation</a> covers display density, titles, information panels, table panels, responsive columns and status indicators.</p>
    <p><a href="/style-guide/forms">Forms</a> covers input types, numeric values, buttons, save-progress states and validation errors.</p>
    <p><a href="/style-guide/tab-form">Tab form</a> demonstrates five sections of one form with values retained in browser storage.</p>
    <p><a href="/style-guide/workflow">Workflow components</a> demonstrates the real shared tabs, panels, table options, quantity navigation, unsaved-change handling and standalone print foundation.</p>
    <p><a href="/style-guide/vite-demo">Vite demo</a> demonstrates built assets and locally hosted Font Awesome Free.</p>
    <p><a href="/style-guide/notifications">Notifications</a> covers notices, brief modal confirmations, high-impact actions and unsaved changes warnings.</p>
    <p><a href="/style-guide/orphaned">Orphaned examples</a> temporarily retains environment indicators, record details, permanent notes, loading states, account feedback, role actions and the legacy table layout.</p>
    <p><a href="/style-guide/examples">Example uses</a> combines these into user, inventory, request, count, catalog and event examples.</p>
    @if(request()->getHost() === 'tg-inventory-app.test')
    <p><a href="/style-guide/wrapper">Application frame</a> shows the current shared header, navigation and a two-panel user example.</p>
    @endif
    <p>Sample data is illustrative. Account permissions and business rules still govern the actual application.</p>
</div>
@endsection
