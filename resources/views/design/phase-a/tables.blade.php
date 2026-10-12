@extends('design.phase-a.layout')
@section('title', 'Tables')
@section('intro', 'Current table format first, followed by the legacy padded-panel comparison.')
@section('guide-content')
<section aria-labelledby="guide-table-outside">
    <h2 id="guide-table-outside">Current format: table as the panel</h2>
    <div class="app-content"><p>The table is the entire panel, without outer padding. Explanations and actions sit outside; search and filter controls belong in a separate padded panel.</p></div>
    @include('design.phase-a.table-sample', ['tablePanel' => true, 'tableLabel' => 'Table as its own panel'])
    <div class="app-content"><p class="app-field-help">Bold column headings on darker lavender; near-black semibold category/collection headings on faint lavender.</p>
    <div class="app-actions"><button type="button">Download sample</button></div>
</div>
</section>
<x-app.guide-divider />
<section aria-labelledby="guide-table-inside">
    <h2 id="guide-table-inside">Legacy format: table inside a panel</h2>
    <div class="app-content">
        <p>The padded panel surrounds the table, explanatory text and actions. This comparison shows the earlier padded-panel layout.</p>
        @include('design.phase-a.table-sample', ['tablePanel' => false, 'tableLabel' => 'Legacy table inside a padded panel'])
        <p class="app-field-help">Fictional quantities; this example does not change inventory.</p>
        <div class="app-actions"><button type="button">Download sample</button></div>
    </div>
</section>
@endsection
