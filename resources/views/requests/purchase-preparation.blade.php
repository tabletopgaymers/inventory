@extends('layout')

@section('title', 'Purchase Preparation')

@section('content')

<p><a href="/purchases/{{ $row->id }}">{{ $row->title ?: 'Untitled request' }}</a> · #{{ $row->id }} · {{ $row->status }}</p><p>Saved pre-order work may be incomplete. Estimates do not calculate acquisition costs or create an order.</p>

<form method="post" action="/purchases/{{ $row->id }}/preparation" data-request-form>@csrf<input type="hidden" name="_deliberate" value="1"><input type="hidden" name="revision" value="{{ old('revision',$row->revision) }}"><fieldset @disabled(!$canPrepare)><legend>Pre-order preparation</legend>

<label>Supplier<select name="supplier_id"><option value="">Not selected</option>@foreach($suppliers as $supplier)@if($supplier->state==='active' || (string)$supplier->id === (string)$row->supplier_id)<option value="{{ $supplier->id }}" @selected((string)old('supplier_id',$row->supplier_id)===(string)$supplier->id)>{{ $supplier->name }} ({{ $supplier->state }})</option>@endif
@endforeach</select></label>

<label>Receiving storage<select name="receiving_location_id"><option value="">Not selected</option>@foreach($locations as $location)@if($location->state==='active' || (string)$location->id === (string)$row->receiving_location_id)<option value="{{ $location->id }}" @selected((string)old('receiving_location_id',$row->receiving_location_id)===(string)$location->id)>{{ $location->name }} ({{ $location->state }})</option>@endif
@endforeach</select></label>

<label>Planned date<input type="date" name="planned_date" value="{{ old('planned_date',$row->planned_date) }}"></label>

<label>Preparation notes<textarea name="preparation_notes" maxlength="5000">{{ old('preparation_notes',$row->preparation_notes) }}</textarea></label>

<h2>Catalog lines</h2><div class="table-wrap"><table class="stock-editor"><thead><tr><th>Item</th><th>Individual units</th><th>Estimated line amount (USD)</th><th>Line note</th><th></th></tr></thead><tbody data-purchase-lines>@foreach(old('lines',$lines) as $itemId => $line)@php($item = $items->firstWhere('id',(int)$itemId))<tr data-item-id="{{ $itemId }}"><th>{{ $item?->name ?: 'Unavailable item' }}</th><td><input aria-label="Quantity for {{ $item?->name }}" name="lines[{{ $itemId }}][quantity]" value="{{ $line['quantity'] ?? '' }}" inputmode="numeric"></td><td><input aria-label="Estimate for {{ $item?->name }}" name="lines[{{ $itemId }}][estimate]" value="{{ $line['estimate'] ?? '' }}" inputmode="decimal"></td><td><textarea aria-label="Line note for {{ $item?->name }}" name="lines[{{ $itemId }}][note]" maxlength="5000">{{ $line['note'] ?? '' }}</textarea></td><td><button type="button" data-remove-item>Remove</button></td></tr>@endforeach</tbody></table></div>

@if($canPrepare)<label>Add catalog item<select data-purchase-item><option value="">Choose an item</option>@foreach($items->where('state','active') as $item)<option value="{{ $item->id }}">{{ $item->name }} · {{ $item->sku }}</option>@endforeach</select></label><button type="button" data-add-purchase-item>Add item</button>@endif</fieldset>

@if($canPrepare)<p><a href="/catalog">Open catalog maintenance</a>. Catalog editing follows its separate permissions; freeform request details remain valid.</p><div class="actions"><a class="discard" href="/purchases/{{ $row->id }}">Discard</a><button class="primary">Save preparation</button></div>@endif</form>

<script src="/requests.js" defer></script>

@endsection
