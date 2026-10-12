@extends('layout')
@section('title', $row ? 'Edit relocation request' : 'New relocation request')
@section('relocation-progress')
@include('requests.relocation-progress', ['progressRow' => $row, 'progressContext' => 'work', 'progressActive' => $row && $row->status === 'Requested' && $step === 'items' ? 'fulfillment' : $step, 'progressToken' => $token, 'progressSaveUrl' => '/relocations/work/'.$token, 'progressKey' => 'work:'.$token, 'progressRevision' => $draft['revision'], 'progressVersion' => $draft['version'] ?? 0, 'progressDirty' => \App\Support\RelocationProgress::dirty($draft)])
@endsection
@section('content')
<link rel="stylesheet" href="/request-workflow.css?v=desktop-800-1">

@if($row)<p data-relocation-identity>Relocation #{{ $row->id }} · <span class="status-badge request-status {{ strtolower($row->status) }}">{{ $row->status }}</span> · Saved {{ \App\Support\DisplayDates::timestamp($row->updated_at) }}</p>@endif

@if($errors->has('lines'))<p class="error"><a href="#requested-items">{{ $errors->first('lines') }}</a></p>@endif
<p>Title, Source and Destination are required to save a Draft. Items can wait until review. Quantities are individual units. No stock is reserved.</p>
@if(!$draft['source_location_id'])<p class="warning">Choose an active Source before saving. New requests default to the designated active Central location when available; ask a catalog manager if Central is missing or ineligible. Existing incomplete Drafts require valid choices on their next save.</p>@endif
<form id="relocation-work" method="post" action="/relocations/work/{{ $token }}" data-request-form data-relocation-form="work"><input type="hidden" name="progress_step" value="{{ $step }}">@csrf<input type="hidden" name="_deliberate" value="1">
<section @if($step !== 'locations') hidden @endif><label>Title<input name="title" required maxlength="255" value="{{ old('title',$draft['title']) }}"></label>
<div class="app-relocation-locations"><label>Source<select name="source_location_id" required>@if(!old('source_location_id',$draft['source_location_id']))<option value="" disabled selected>Choose an active Source</option>@endif @foreach($locations as $location)@if($location->state==='active' || (string)$location->id===(string)$draft['source_location_id'])<option value="{{ $location->id }}" @selected((string)old('source_location_id',$draft['source_location_id'])===(string)$location->id)>{{ $location->name }}@if($location->state !== 'active') ({{ $location->state }})@endif</option>@endif
@endforeach</select></label>
<span class="app-location-arrow" aria-hidden="true">→</span><label>Destination<select name="destination_location_id" required><option value="">Choose a Destination</option>@foreach($locations as $location)@if($location->state==='active' || (string)$location->id===(string)$draft['destination_location_id'])<option value="{{ $location->id }}" @selected((string)old('destination_location_id',$draft['destination_location_id'])===(string)$location->id)>{{ $location->name }}@if($location->state !== 'active') ({{ $location->state }})@endif</option>@endif
@endforeach</select></label>
</div>
<label class="app-relocation-notes">Notes (optional)<textarea name="details" maxlength="5000">{{ old('details',$draft['details']) }}</textarea></label>
</section>
<section @if($step !== 'items') hidden @endif>
<p>{{ $locations->firstWhere('id',(int)$draft['source_location_id'])?->name }} → {{ $locations->firstWhere('id',(int)$draft['destination_location_id'])?->name }}</p>
<section class="requested-items" id="requested-items" tabindex="-1"><h2>Requested items</h2>
@if(!$rows)<p>No items added yet. Use Add items below to select catalog items.</p>@endif<p>Projections use current balances and requested units; they do not reserve stock. Returning from Locations refreshes current balances without saving the request.</p><x-app.table-region label="Relocation items" class="table-wrap"><table class="stock-editor"><thead><tr><th>Item</th><th>Source: current → projected</th><th>Destination: current → projected</th><th>Request</th><th></th></tr></thead><tbody>@include('requests.relocation-rows',['quantities'=>old('lines',$draft['lines']),'editing'=>true,'columns'=>5])</tbody></table></x-app.table-region></section>
<h2>Add items</h2><div class="search-actions"><label>Search inventory<input name="search" maxlength="255" value="{{ old('search',$draft['search_fields']['search'] ?? $draft['criteria']['search'] ?? '') }}"></label><button name="action" value="search" formnovalidate>Search</button><button name="action" value="show_all" formnovalidate>Show all</button><button name="action" value="reset" formnovalidate>Reset</button></div>
<details><summary>Collection filters</summary><div class="collection-checklist">@foreach($collections as $collection)<label class="app-choice"><input type="checkbox" name="collections[]" value="{{ $collection->id }}" @checked(in_array($collection->id,old('collections',$draft['search_fields']['collections'] ?? $draft['criteria']['collections'] ?? [])))>{{ $collection->category_name }} : {{ $collection->name }}</label>@endforeach</div></details>
@if($draft['criteria'] !== null)<p>Added items are excluded. Nonblank nonnumeric entries add zero; negative, fractional or excessive numbers are rejected.</p><x-app.table-region label="Search results to add" class="table-wrap"><table class="stock-editor"><thead><tr><th>Item</th><th>Current source</th><th>Current destination</th><th>Request</th></tr></thead><tbody>@php($group=null)@forelse($results as $item)@if($group!==$item['category'].' : '.$item['collection'])@php($group=$item['category'].' : '.$item['collection'])<tr class="collection-heading"><th colspan="4">{{ $group }}</th></tr>@endif<tr><td class="app-grouped-item">{{ $item['name'] }}</td><td class="number" data-route-balance>{{ $item['source'] === null ? 'Unknown' : number_format($item['source']) }}</td><td class="number" data-route-balance>{{ $item['destination'] === null ? 'Unknown' : number_format($item['destination']) }}</td><td><input name="selection[{{ $item['id'] }}]" aria-label="Add quantity for {{ $item['name'] }} · {{ $item['collection'] }} · SKU {{ $item['sku'] }} (optional)" value="{{ old('selection.'.$item['id'],$draft['selection'][$item['id']] ?? '') }}"></td></tr>@empty<tr><td colspan="4">No matching active items to add.</td></tr>@endforelse</tbody></table></x-app.table-region><div class="actions"><button name="action" value="add" formnovalidate>Add selected <span data-selection-count></span></button></div>@endif
</section>
<div class="actions">
@if($step === 'items')<button name="action" value="locations" formnovalidate>Back: locations</button>@endif
<a class="discard" href="{{ $row ? '/relocations/'.$row->id : '/relocations' }}">Discard changes</a>

@if($step === 'locations')<button name="action" value="items" class="primary" formnovalidate>Next: items</button>@elseif(!$row || $row->status === 'Draft')<button name="action" value="review" class="primary" formnovalidate>Next: review</button>@endif
</div>
</form><script src="/requests.js?v=relocation-progress-1-sentence-case-1" defer></script>
@endsection
