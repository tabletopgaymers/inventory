@extends('layout')
@section('title', 'Adjust unit cost - '.$item->name)
@section('page-actions')
<x-app.action variant="discard" href="{{ $itemUrl }}">Cancel</x-app.action>
<button type="submit" class="primary app-save-progress" form="cost-form" data-catalog-save aria-busy="false"><span data-save-label>Save</span><span class="app-search-spinner" aria-hidden="true"></span></button>
@endsection
@section('content-sections')
<div data-catalog-save-feedback role="status" hidden></div>
<div class="app-content">
<form id="cost-form" method="post" data-catalog-progress data-record-base="{{ dirname($itemUrl).'/' }}" data-save-mode="edit" data-save-dirty="{{ $errors->any() || session('error') ? 'true' : 'false' }}">
@csrf
<input type="hidden" name="operation" value="{{ old('operation', $token) }}">
<div class="app-catalog-item-columns">
<div>
<label>Current unit cost<input type="text" readonly value="{{ \App\Support\StockNumbers::displayCost($item->unit_cost) }}"></label>
<label>New unit cost<input name="unit_cost" required inputmode="decimal" value="{{ old('unit_cost', $item->unit_cost) }}"></label>
<label>Rationale<input data-optional name="rationale" maxlength="1000" value="{{ old('rationale') }}"></label>
</div>
<div>
<h2>Cost adjustment notes</h2>
<p>Changes made to unit cost only apply to existing stock and will not change any stock quantities. Earlier unit cost history is preserved and this change will be included in that history.</p>
<ul><li>Explicit zero (0) means unknown.</li></ul>
</div>
</div>
</form>
</div>
@vite('resources/js/catalog-progress.js')
@endsection
