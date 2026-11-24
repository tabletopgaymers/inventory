@extends('layout')
@section('title', 'Users')
@section('content-sections')
<x-app.table-region label="User directory">
    <table data-user-directory>
        <thead><tr>
            @foreach(['Name', 'Email', 'Elevated roles'] as $label)
                <th scope="col" @if($loop->first) aria-sort="ascending" @endif>
                    <x-app.action type="button" class="app-table-sort" data-user-sort>
                        {{ $label }} <span aria-hidden="true">{{ $loop->first ? '↑' : '↕' }}</span>
                    </x-app.action>
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
