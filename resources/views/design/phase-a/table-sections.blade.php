@extends('design.phase-a.layout')
@section('title', 'Optional table headers and footers')
@section('intro', 'Optional sections supported by the shared table-panel component.')
@section('guide-content')
<p>Plain table panels remain the default. This optional layout combines a small table-specific header or footer with the table, under one outer border. It can replace a small adjacent panel whose only purpose is to describe or act on that table.</p>
<div class="app-panel-stack">
<div class="app-content">
    <p>The catalog groups items by category and collection. Use consistent names so records are easy to find.</p>
</div>
<x-app.table-region id="guide-table-preview" label="Sample Ribbon collections">
    <x-slot:header><x-app.heading-row variant="table" class="app-table-panel-toolbar">
        <div class="app-table-panel-copy">
            <h2 id="guide-table-section-title">Ribbon collections</h2>
            <p class="app-field-help">All retained collections in this category.</p>
        </div>
        <div class="app-actions"><x-app.action variant="new"  href="#guide-table-preview">＋ New</x-app.action></div>
    </x-app.heading-row></x-slot:header>

        <table><thead><tr><th>Collection</th><th>Description</th><th>SKU prefix</th><th>Status</th></tr></thead><tbody>
            <tr><td><a href="#guide-table-preview">Pronoun</a></td><td>Pronoun identification ribbons</td><td>RPR</td><td><x-app.status-badge status="Active" /></td></tr>
            <tr><td><a href="#guide-table-preview">Pride flag</a></td><td>Pride flag ribbons</td><td>RPF</td><td><x-app.status-badge status="Active" /></td></tr>
            <tr><td><a href="#guide-table-preview">Legacy designs</a></td><td>Earlier ribbon designs</td><td>RLG</td><td><x-app.status-badge status="Inactive" /></td></tr>
        </tbody></table>

    <x-slot:footer><x-app.heading-row variant="table" class="app-table-panel-toolbar">
        <p class="app-field-help app-table-panel-copy">3 collections · 2 active · 1 inactive</p>
        <div class="app-actions"><x-app.action type="button">Download CSV</x-app.action></div>
    </x-app.heading-row></x-slot:footer>
</x-app.table-region>
<div class="app-content">
    <p>Inactive collections remain available for reference. Their items and earlier history are retained.</p>
</div>
</div>
<x-app.guide-divider />
<div class="app-content">
    <h2>When to use this layout</h2>
    <ul>
        <li>Use a header, a footer, both or neither. Do not add empty sections to make tables look alike.</li>
        <li>Header and footer slots accept any appropriate content. A heading, description or action button is optional; none is required by the component.</li>
        <li>Keep context brief. Put table-specific creation actions in the header and exports, counts or pagination in the footer.</li>
        <li>Keep larger instructions, search/filter controls and record-wide Edit/Save actions separate.</li>
        <li>Plain and sectioned tables use the same outer frame, with the shared 8px panel radius (<code>--app-radius</code>). The table's internal edges and column-header band stay square.</li>
        <li>Every table has one <code>app-table-panel-body</code> scrolling region, even when no header or footer is supplied. Do not override scrolling or radius in domain styles.</li>
        <li>On narrow screens, text and actions wrap into separate rows. Only the table scrolls horizontally; its header and footer stay within the panel width.</li>
    </ul>
    <p class="app-field-help">This is a style-guide mockup with fictional records and inactive sample actions. The header and footer use shared component styles; sample content remains local to this guide.</p>
    <p><a href="/style-guide/presentation#table-panel">Compare with the standard table panel</a>.</p>
</div>
@endsection
