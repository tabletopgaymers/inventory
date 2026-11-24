@extends('layout')
@section('title', 'Lists')
@section('page-actions')
<x-app.action variant="new" href="/catalog/lists/create" />
@endsection
@section('content-sections')
@vite('resources/js/saved-lists.js')
<x-app.table-region label="Saved lists" class="app-list-table-panel">
<table data-saved-lists><thead><tr>
@foreach(['ID', 'Name', 'Owner', 'Location', 'Items', 'Updated', 'Status'] as $heading)
    <th scope="col" @if($loop->first) aria-sort="ascending" @endif><x-app.action class="app-table-sort" data-table-sort>{{ $heading }} <span aria-hidden="true">{{ $loop->first ? '↑' : '↕' }}</span></x-app.action></th>
@endforeach
</tr></thead>
<tbody>
@forelse($lists as $list)
<tr><td>{{ $list->id }}</td><td><a href="/catalog/lists/{{ $list->id }}">{{ $list->name }}</a></td><td>{{ $list->owner->displayName() }}</td><td>{{ $locations[$list->storage_location_id] ?? '—' }}</td><td class="table-numeric" data-sort-value="{{ $counts[$list->id] ?? 0 }}">{{ number_format($counts[$list->id] ?? 0) }}</td><td data-sort-value="{{ $list->getRawOriginal('updated_at') }}">{{ \App\Support\DisplayDates::date($list->updated_at) }}</td><td><x-app.status-badge :status="$list->status" :label="\App\Models\SavedList::STATUSES[$list->status]" /></td></tr>
@empty
<tr><td colspan="7">No saved lists yet.</td></tr>
@endforelse
</tbody></table>
</x-app.table-region>
@endsection
