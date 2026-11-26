@extends('layout')
@section('title', $location->name)
@section('title-status')<x-app.status-badge :status="ucfirst($location->state)" />@endsection
@section('section-navigation')
<nav class="app-section-nav" aria-label="Storage locations">
    @foreach($locations as $destination)
        <a href="{{ \App\Support\CatalogRecords::viewUrl('storage_locations', $destination->id) }}" @if($destination->id === $location->id) aria-current="page" @endif>{{ $destination->name }}</a>
    @endforeach
</nav>
@endsection
@section('page-actions')
@if($canManage)<x-app.action  href="/catalog/storage_locations/{{ $location->id }}/edit">Edit</x-app.action>@endif
@endsection
@section('content-sections')
<div class="app-location-report app-print-report app-panel-stack">
<div class="app-columns app-columns-two">
<div class="app-content">
    <x-app.detail-list variant="content" ><dt>Description</dt><dd>{{ $location->description ?: '—' }}</dd><dt>Mailing address</dt><dd class="plain-text">{{ $location->mailing_address ?: '—' }}</dd></x-app.detail-list>
</div>
<form method="get" action="/inventory/locations/{{ $location->id }}" class="app-content app-screen-only" data-location-view-filter>
    @if($browse !== null)<input type="hidden" name="browse" value="{{ $browse }}">@endif
    <label class="app-choice"><input type="checkbox" name="include_zero_stock" value="1" @checked($includeZeroStock)> Include zero-stock items</label>
    @if($hasIncoming)
    <input type="hidden" name="show_incoming" value="0">
    <label class="app-choice"><input type="checkbox" name="show_incoming" value="1" @checked($showIncoming)> Show Ordered and In Transit</label>
    @endif
</form>
</div>
<x-app.table-region label="Location inventory">
<x-slot:header>
    <x-app.heading-row variant="table" class="app-screen-only">
        <h2>Inventory</h2>
        <div class="app-inline-group">
            @if($rows !== [])
                <x-app.action :href="$createListUrl">Create list</x-app.action>
            @endif
            <form method="post" action="{{ route('reconcile.search', absolute: false) }}">
                @csrf
                <input type="hidden" name="location_id" value="{{ $location->id }}">
                <input type="hidden" name="zero_stock_location" value="{{ $location->id }}">
                <input type="hidden" name="include_active_zero" value="{{ (int) $includeZeroStock }}">
                <x-app.action type="submit" name="action" value="search">Prepare reconcile</x-app.action>
            </form>
        </div>
    </x-app.heading-row>
</x-slot:header>
<x-app.items-table :rows="$rows" :columns="$columns" empty-message="No items at this location." />
<x-slot:footer>
    <p>Event, In Transit and Ordered balances are separate. Expected includes current counts at this location, In Transit, and Ordered balances.</p>
</x-slot:footer>
</x-app.table-region>
</div>
@vite('resources/js/inventory.js')
@endsection
