<th class="catalog-status-heading"><div class="catalog-status-heading-row"><span>Status</span>
<button type="button" class="catalog-status-trigger" aria-label="Filter {{ strtolower($kinds[$kind]) }} by status{{ count($selectedStatuses) < count($statusOptions) ? ' (filter applied)' : '' }}" aria-expanded="false" aria-controls="catalog-status-filter" @if(count($selectedStatuses) < count($statusOptions)) data-filter-active @endif>
<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 5h18l-7 8v6l-4 2v-8z" /></svg>
</button></div>
<form id="catalog-status-filter" class="catalog-status-menu" action="/catalog/{{ $kind }}" method="get" data-filter-user="{{ auth()->id() }}" hidden>
<input type="hidden" name="status_filter" value="1">
@if($collection)<input type="hidden" name="collection" value="{{ $collection->id }}">@endif
<div class="catalog-status-menu-heading"><span id="catalog-status-filter-title">Status</span><button type="submit" class="catalog-status-apply primary" aria-label="Apply status filter" title="Apply status filter"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 8C8 2 16 2 20 8L21 10M21 5v5h-5M19 16C16 22 8 22 4 16L3 14M3 19v-5h5" /></svg></button></div>
<div role="group" aria-labelledby="catalog-status-filter-title">
@foreach($statusOptions as $status)<label><input type="checkbox" name="statuses[]" value="{{ $status }}" @checked(in_array($status, $selectedStatuses, true))><span>{{ ucfirst($status) }}</span></label>@endforeach
</div>
</form></th>
