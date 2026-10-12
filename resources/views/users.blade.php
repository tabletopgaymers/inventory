@extends('layout')
@section('title', 'Users')
@section('content-sections')
<x-app.table-region label="User directory" class="app-list-table-panel"><table><thead><tr><th>Name</th><th>Email</th><th>Extended roles</th></tr></thead><tbody>
@foreach($users as $user)<tr><td><a href="/users/{{ $user->id }}">{{ $user->displayName() }}</a></td><td>{{ $user->provider_email ?: 'Not supplied' }}</td><td>{{ implode(', ', array_diff($user->roles(), ['basic'])) }}</td></tr>@endforeach
</tbody></table></x-app.table-region>
@endsection
