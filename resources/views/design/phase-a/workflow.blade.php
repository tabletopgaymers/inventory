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
@vite('resources/js/workflow-demo.js')
@endsection
