@extends('design.phase-a.layout')
@section('title', 'Style guide')
@section('intro', 'A reference for the current application design.')
@section('guide-content')
<div class="app-content">
    <h2>Current application styles</h2>
    <p>This guide uses the same layout, stylesheet and shared components as the application. Changes to shared typography, panels, controls and navigation appear here too.</p>
    <p>It replaces the earlier standalone design proposal. Examples describe current decisions; they do not add roles, lifecycle states or business actions.</p>
</div>
<div class="app-content">
    <h2>Design decisions</h2>
    <ul>
        <li><strong>Panels:</strong> white surfaces with consistent padding, regardless of content. Related information and actions may occupy separate panels.</li>
        <li><strong>Action panels:</strong> controls align left with no extra row or form margins. Buttons keep normal internal padding. Destructive account actions align right.</li>
        <li><strong>Tables:</strong> informative lists with linked names; record pages own their actions. The Users table shows Name, Email and Extended Roles, excluding basic.</li>
        <li><strong>User details:</strong> bold labels and aligned values in a borderless definition list. Contact confirmation, Microsoft account, extended roles and time zone are distinct.</li>
        <li><strong>Buttons:</strong> purple Save, green +New, neutral Edit and a clearly separated destructive action. Disabled controls and saved/unsaved states are explicit.</li>
        <li><strong>Account confirmation:</strong> an in-page modal with safe Cancel, a red confirmation button and focus returned to the trigger.</li>
        <li><strong>Navigation:</strong> Inventory, Relocations, Events, Purchases, Catalog, Users. Desktop begins at 800px; smaller screens use Menu.</li>
        <li><strong>Environment:</strong> local magenta, remote development red, testing orange and staging green. Production and unmapped environments show no box.</li>
        <li><strong>Requests:</strong> editing progress stays separate from business status. Save Progress is beside the heading and preserves the current step.</li>
    </ul>
</div>
<div class="app-content">
    <h2>Explore the guide</h2>
    <p><a href="/design/phase-a/elements">General elements</a> shows panels, buttons, status badges, fields, tables, notices and a working modal preview.</p>
    <p><a href="/design/phase-a/examples">Example uses</a> combines these into user, inventory, request, count, catalog and event examples.</p>
    @if(request()->getHost() === 'tg-inventory-app.test')
    <p><a href="/design/phase-a/wrapper">Application frame</a> shows the current shared header, navigation and a two-panel user example.</p>
    @endif
    <p>Sample data is illustrative. Account permissions and business rules still govern the actual application.</p>
</div>
@endsection
