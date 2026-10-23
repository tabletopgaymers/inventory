@extends('layout')
@section('title', 'Purchase requests')
@section('page-actions')
<a class="button app-new-action" href="/purchase/new" title="New purchase request">+New</a>
@endsection
@section('content-sections')
<x-app.table-region label="Purchase requests" class="app-list-table-panel">
    <table>
        <thead><tr><th>ID</th><th>Title</th><th>Requester</th><th>Last updated</th><th>Status</th></tr></thead>
        <tbody>
            @forelse($rows as $row)
            <tr>
                <td>{{ $row->id }}</td>
                <td><a href="/purchase/{{ $row->id }}">{{ $row->title ?: 'Untitled request' }}</a></td>
                <td>{{ trim($row->first_name.' '.$row->last_name) ?: 'Account '.$row->owner_id }}</td>
                <td>{{ \App\Support\DisplayDates::date($row->updated_at) }}</td>
                <td><span class="status-badge request-status {{ strtolower($row->status) }}">{{ $row->status }}</span></td>
            </tr>
            @empty
            <tr><td colspan="5">No purchase requests yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-app.table-region>
@endsection
