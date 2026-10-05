@extends('layout')
@section('title', 'Users')
@section('content')
<div class="table-wrap"><table><thead><tr><th>Name</th><th>Microsoft email</th><th>Roles</th><th>Access</th><th>Actions</th></tr></thead><tbody>
@foreach($users as $user)<tr><td>{{ $user->displayName() }}</td><td>{{ $user->provider_email ?: 'Not supplied' }}</td><td>{{ implode(', ', $user->roles()) }}</td><td>{{ $user->enabled ? 'Enabled' : 'Disabled' }}</td><td>
@if(auth()->user()->hasRole('admin') && count(array_intersect($user->roles(), ['admin', 'manager', 'procurement'])) && ! $user->validContact())<p class="warning">Organizational contact confirmation is missing.</p>@endif
@foreach(\App\Support\AccessManagement::ROLES as $role)
@if(auth()->user()->hasRole('admin') || (in_array($role, ['manager', 'procurement']) && auth()->user()->hasRole($role)))
<form class="inline" method="post" action="/users/{{ $user->id }}/roles">@csrf<input type="hidden" name="_deliberate" value="1"><input type="hidden" name="role" value="{{ $role }}"><input type="hidden" name="grant" value="{{ $user->hasRole($role) ? '0' : '1' }}"><button>{{ $user->hasRole($role) ? 'Remove' : 'Grant' }} {{ ucfirst($role) }}<span class="sr-only"> for {{ $user->displayName() }}</span></button></form>
@endif
@endforeach
@if(auth()->user()->hasRole('admin') && $user->enabled)<form class="inline" method="post" action="/users/{{ $user->id }}/disable">@csrf<input type="hidden" name="_deliberate" value="1"><button class="discard">Disable account<span class="sr-only"> for {{ $user->displayName() }}</span></button></form>@endif
</td></tr>@endforeach
</tbody></table></div>
@endsection
