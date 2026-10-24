@extends('design.phase-a.layout')
@section('title', 'Workflow components')
@section('intro', 'Shared components used by Purchase and Relocation. These examples do not save data.')
@section('guide-content')
<div class="app-content">
    <h2>In-page tabs</h2>
    <p>Use x-workflow.tabs with a unique prefix, labels, initial active key and accessible label. Pair each key with x-workflow.tab-panel. The same page remains loaded; the URL remembers the selected section.</p>
    <p>Left/Right arrows, Home and End select and focus tabs; Tab keeps its normal behavior. Panels use the caller's padding class. The shared heading places its title and actions on one row, wrapping on small screens.</p>
</div>
@foreach(['tab'=>'Primary specimen','second-tab'=>'Independent specimen'] as $parameter=>$specimen)
@php($active = in_array(request($parameter), ['basic','material','review']) ? request($parameter) : 'basic')
<div data-workflow-demo="{{ $parameter }}">
    <x-workflow.tabs :prefix="$parameter" :label="$specimen" :active="$active" :tabs="['basic'=>'Basic info','material'=>'Material','review'=>'Review']" />
    @foreach(['basic'=>'Basic info','material'=>'Material','review'=>'Review'] as $key=>$title)
    <x-workflow.tab-panel class="app-content" :id="$parameter.'-panel-'.$key" :labelledby="$parameter.'-tab-'.$key" :active="$active === $key">
        <x-workflow.heading><h2>{{ $specimen }}: {{ $title }}</h2><button type="button" disabled>Sample action</button></x-workflow.heading>
        <p>This is {{ $title }}. Its disabled action demonstrates caller-controlled availability.</p>
    </x-workflow.tab-panel>
    @endforeach
</div>
@endforeach
<div class="app-content"><h2>Linked steps</h2><p>Use x-workflow.steps for pages that navigate to server routes. Supply ordinary links and aria-current="page" on the current one. They share tab styling but retain link semantics and native keyboard behavior.</p></div>
<x-workflow.steps label="Linked step specimen">
    <a class="button" href="/style-guide/workflow" aria-current="page">Workflow components</a>
    <a class="button" href="/style-guide/forms">Forms</a>
</x-workflow.steps>
<div class="app-content"><h2>Agent usage</h2><p>Import mountTabs from resources/js/workflow/tabs.js in the domain Vite entry. Pass the form/root, initialKey and an onChange callback for domain refreshes. Use queryParam when independent widgets share a page. activate(key) supports Next and error links; destroy() removes listeners. Repeated mounting returns the existing instance. Never use tab styling to infer permission, persistence or validation rules.</p></div>
<div class="app-content">
    <h2>Tables and quantity navigation</h2>
    <p>x-app.table-region retains its header/content/footer slots and adds opt-in nowrap, quantity-fields, separators and numeric (all or after-first) options. Other callers retain their existing defaults. Standard density and grouped-item indentation come from the app theme.</p>
    <p>Use table-action-column (1%), table-quantity-column (5%), table-numeric, table-heading-center and table-border-left/right on caller-defined columns/cells. A variance sub-column uses table-variance table-joined to keep its separator inside the group invisible. table-row-error shares the error notification colors. Domain-specific widths remain caller choices.</p>
    <p>In editable quantity fields only, Enter/Down moves to the next editable visible field, Up moves back, and the value is selected. Read-only/disabled/hidden fields are skipped; no wrapping or submit occurs at the ends. Tab, modified keys and other fields retain native behavior.</p>
    <p>Import mountQuantityNavigation from resources/js/workflow/quantity-navigation.js. Supply root, resolveFields(input) returning the current intended column/group, and optional isBusy(). The module never parses values or sets editability. Delegation finds new rows at keydown time; repeated mounting returns the existing instance and destroy() removes its listener.</p>
</div>
<form data-quantity-demo>
<x-app.table-region label="Quantity navigation specimen" :nowrap="true" :quantity-fields="true" :separators="true" numeric="after-first">
    <x-slot:header><x-workflow.heading><h2>Quantity specimen</h2><button type="button" data-demo-add-row>Add sample row</button></x-workflow.heading></x-slot:header>
    <table><colgroup><col><col class="table-quantity-column"><col class="table-quantity-column"><col class="table-action-column"></colgroup>
        <thead><tr><th>Item</th><th class="table-heading-center">Requested</th><th class="table-heading-center" colspan="2">Fulfilled</th></tr></thead>
        <tbody>
            <tr class="group-row"><th colspan="4">Sample category : Sample collection</th></tr>
            <tr><td class="app-grouped-item">First item</td><td><input data-demo-quantity aria-label="First quantity" value="10"></td><td class="table-variance table-joined"><span class="table-variance-negative">[ - 2 ]</span></td><td>8</td></tr>
            <tr><td class="app-grouped-item">Read-only item</td><td><input data-demo-quantity aria-label="Read-only quantity" value="20" readonly></td><td class="table-variance table-joined"></td><td>20</td></tr>
            <tr><td class="app-grouped-item">Disabled item</td><td><input data-demo-quantity aria-label="Disabled quantity" value="30" disabled></td><td class="table-variance table-joined"></td><td>30</td></tr>
            <tr hidden><td class="app-grouped-item">Hidden item</td><td><input data-demo-quantity aria-label="Hidden quantity" value="40"></td><td></td><td></td></tr>
            <tr class="table-row-error"><td class="app-grouped-item">Last editable item (error specimen)</td><td><input data-demo-quantity aria-label="Last quantity" value=""></td><td class="table-variance table-joined"></td><td>0</td></tr>
        </tbody>
    </table>
    <x-slot:footer><p>Nothing saves. Add a row to demonstrate navigation finding new fields without remounting.</p></x-slot:footer>
</x-app.table-region>
<fieldset disabled><label>Disabled fieldset specimen<input data-demo-quantity value="50"></label></fieldset>
<template><tr data-demo-dynamic><td class="app-grouped-item"></td><td><input data-demo-quantity></td><td class="table-variance table-joined"></td><td>0</td></tr></template>
</form>
<div class="app-content">
    <h2>Unsaved changes</h2>
    <p>Type in this field, then follow any normal site link. The shared shell opens one confirmation modal; Keep editing or Escape preserves the field and restores focus. Leave without saving navigates once. Browser reload/close uses the native browser warning. Reset demo clears its dirty state. Nothing is saved.</p>
    <form data-unsaved-demo><label>Unsaved demo text<input></label><button type="reset">Reset demo</button> <a href="/style-guide/forms">Leave for Forms</a></form>
    <p>Import mountUnsavedChanges from resources/js/workflow/unsaved-changes.js; supply root, isDirty(), optional isBusy() and message. The application shell is required. The caller owns dirty state and clears only its successfully saved section. confirm(callback, options) uses the shell modal for deliberate actions; allowDeparture() suppresses the unload warning for an accepted server submission; isLeaving() prevents recursive confirmations, and destroy() unregisters listeners/guard. Optional text/title/actionLabel/origin retain the caller's action wording and focus target.</p>
    <p>The shell's optional guard.onLeave callback prevents a second native warning after an approved link departure. Existing guards without it retain their behavior. Field qualifiers, error/notice styling and Ctrl/Cmd+S continue using the existing shared shell/shortcut assets; domain validation messages and save responses remain caller-owned.</p>
</div>
<div class="app-content">
    <h2>Standalone print documents</h2>
    <p><a href="/style-guide/print" target="_blank" rel="noopener">Open printable specimen</a>. This uses the real x-print.layout and x-print.header, including an empty Notes box and enough fictional rows to inspect pagination.</p>
    <p>x-print.layout accepts title and optional document-title; its default slot owns document content. It loads only resources/css/print.css and resources/js/print.js, never the application framework or density styles. x-print.header accepts an ordered label/value map, notes and optional notes-label. Metadata uses one-third width, the always-present outlined notes box two-thirds; Notes has an accessible label and no visible heading.</p>
    <p>Caller-defined tables use print-material, print-collection, print-group-row, print-item, print-quantity-column, print-quantity and print-quantity-heading. Preserve the outer column-header thead and nested collection theads to retain the existing page-repeat behavior and single border edges. Widths/labels/data and empty-state rows remain caller-owned. Standalone print uses fixed spacing, black 1pt borders and half-inch print margins. print-screen-only hides specimen instructions when printing.</p>
    <p>Relocation still saves progress before opening its packing view in a new tab. That persistence behavior belongs to its workflow script; the shared Print button only invokes browser printing. No save, navigation or auto-print policy is embedded in the shared print layout.</p>
</div>
@vite('resources/js/workflow-demo.js')
@endsection
