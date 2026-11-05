@extends('layout')
@section('title', 'Events')
@section('page-actions')
@if($canManage)<x-app.action variant="new"  href="/events/new" title="Create event">＋ New</x-app.action>@endif
@endsection
@section('content-sections')
<p class="error" role="alert"><strong>THIS FEATURE IS UNDER DEVELOPMENT.</strong></p>
<p>Plan supplies, reconcile shared counts and finalize an event once. Planning moves no stock.</p>
<x-app.table-region label="Events" class="app-list-table-panel"><table><thead><tr><th>Event</th><th>Status</th></tr></thead><tbody>
@forelse($events as $event)<tr><th><a href="/events/{{ $event->id }}">{{ $event->name }}</a></th><td><x-app.status-badge :status="$event->status" /></td></tr>@empty<tr><td colspan="2">No events created.</td></tr>@endforelse
</tbody></table></x-app.table-region>
@endsection
