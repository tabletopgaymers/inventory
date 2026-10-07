@extends('layout')
@section('title', 'Events')
@section('content')
<p>Plan supplies, reconcile shared counts and finalize an event once. Planning moves no stock.</p>
@if($canManage)<div class="actions"><a class="primary" href="/events/new">Create event</a></div>@endif
<x-app.table-region label="Events" class="table-wrap"><table><thead><tr><th>Event</th><th>Status</th></tr></thead><tbody>
@forelse($events as $event)<tr><th><a href="/events/{{ $event->id }}">{{ $event->name }}</a></th><td><span class="status-badge request-status {{ $event->status==='Planning' ? 'draft' : ($event->status==='Finalized' ? 'received' : 'active') }}">{{ $event->status }}</span></td></tr>@empty<tr><td colspan="2">No events created.</td></tr>@endforelse
</tbody></table></x-app.table-region>
@endsection
