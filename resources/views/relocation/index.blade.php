@extends('layout')
@section('title', 'Relocation requests')
@section('page-actions')
<a class="button app-new-action" href="/relocation/new">+New</a>
@endsection
@section('content-sections')
<x-app.table-region label="Relocation requests" class="app-list-table-panel">
    <table>
        <thead><tr><th>ID</th><th>Title</th><th>Source</th><th>Destination</th><th>Last updated</th><th>Status</th></tr></thead>
        <tbody>
            @forelse($rows as $row)
            <tr>
                <td>{{ $row->id }}</td>
                <td><a href="/relocation/{{ $row->id }}">{{ $row->title ?: 'Untitled request' }}</a></td>
                <td>{{ $row->source_name ?: '—' }}</td>
                <td>{{ $row->destination_name ?: '—' }}</td>
                <td>{{ \App\Support\DisplayDates::date($row->updated_at) }}</td>
                <td><span class="status-badge request-status {{ strtolower($row->status) }}">{{ $row->status }}</span></td>
            </tr>
            @empty
            <tr><td colspan="6">No relocation requests yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-app.table-region>
@endsection
