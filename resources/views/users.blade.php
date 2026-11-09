@extends('layout')
@section('title', 'Users')
@section('content-sections')
<x-app.table-region label="User directory" class="app-list-table-panel">
    <table data-user-directory>
        <thead><tr>
            @foreach(['Name', 'Email', 'Elevated roles'] as $label)
                <th scope="col" @if($loop->first) aria-sort="ascending" @endif>
                    <button type="button" class="inline-flex cursor-pointer items-center gap-1 hover:underline" data-user-sort>
                        {{ $label }} <span aria-hidden="true">{{ $loop->first ? '↑' : '↕' }}</span>
                    </button>
                </th>
            @endforeach
        </tr></thead>
        <tbody>
            @foreach($users as $user)
                <tr>
                    <td><a href="/users/{{ $user->id }}">{{ $user->displayName() }}</a></td>
                    <td>{{ $user->provider_email ?: 'Not supplied' }}</td>
                    <td>{{ implode(', ', array_diff($user->roles(), ['basic'])) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-app.table-region>
@vite('resources/js/user-directory.js')
@endsection
