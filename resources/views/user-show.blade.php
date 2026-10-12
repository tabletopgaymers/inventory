@extends('layout')
@section('title', $user->displayName())
@if(auth()->user()->hasRole('admin') || (int) auth()->id() === (int) $user->id)
@section('page-actions')<a class="button" href="{{ (int) auth()->id() === (int) $user->id ? '/profile' : '/profile?user='.$user->id }}">Edit</a>@endsection
@endif
@section('content-sections')
@php($zone = is_string($user->time_zone) && in_array($user->time_zone, \App\Support\DisplayDates::zones(), true) ? $user->time_zone : \App\Support\DisplayDates::DEFAULT_ZONE)
<div class="app-content">
<dl class="app-user-details">
    <dt>Name</dt><dd>{{ $user->displayName() }}</dd>
    <dt>TG Email</dt><dd>{{ $user->contact_email ?: 'Not supplied' }}@if($user->contact_email) ({{ $user->validContact() ? 'confirmed' : 'not confirmed' }})@endif</dd>
    <dt>MS Account</dt><dd>{{ $user->provider_email ?: 'Not supplied' }}</dd>
    <dt>Ext Roles</dt><dd>{{ implode(', ', array_diff($user->roles(), ['basic'])) ?: 'None' }}</dd>
    <dt>Time Zone</dt><dd>{{ $zone }}</dd>
    <dt>Personal Email</dt><dd><em>coming later</em></dd>
    <dt>Mobile Number</dt><dd><em>coming later</em></dd>
    <dt>Mailing Address</dt><dd><em>coming later</em></dd>
</dl>
@if(auth()->user()->hasRole('admin') && count(array_intersect($user->roles(), ['admin', 'manager', 'procurement'])) && ! $user->validContact())
<p class="warning">Organizational contact confirmation is missing.</p>
@endif
</div>
@if(auth()->user()->hasRole('admin') || ($user->enabled && (auth()->user()->hasRole('manager') || auth()->user()->hasRole('procurement'))))
<div class="app-content">
<div class="actions app-user-actions">
@if($user->enabled)
@foreach(array_diff(\App\Support\AccessManagement::ROLES, ['basic']) as $role)
@if(auth()->user()->hasRole('admin') || (in_array($role, ['manager', 'procurement']) && auth()->user()->hasRole($role)))
<form class="inline" method="post" action="/users/{{ $user->id }}/roles">@csrf<input type="hidden" name="_deliberate" value="1"><input type="hidden" name="role" value="{{ $role }}"><input type="hidden" name="grant" value="{{ $user->hasRole($role) ? '0' : '1' }}"><button>{{ $user->hasRole($role) ? 'Remove' : 'Grant' }} {{ ucfirst($role) }}<span class="sr-only"> for {{ $user->displayName() }}</span></button></form>
@endif
@endforeach
@endif
@if(auth()->user()->hasRole('admin'))
@if($user->enabled)
<form class="inline app-user-disable-action" id="user-disable-form" method="post" action="/users/{{ $user->id }}/disable">@csrf<input type="hidden" name="_deliberate" value="1"><button type="button" class="discard" id="user-disable-trigger" aria-haspopup="dialog" aria-controls="user-disable-dialog">Disable Account<span class="sr-only"> for {{ $user->displayName() }}</span></button></form>
@else
<form class="inline" method="post" action="/users/{{ $user->id }}/enable">@csrf<input type="hidden" name="_deliberate" value="1"><button>Enable Account<span class="sr-only"> for {{ $user->displayName() }}</span></button></form>
@endif
@endif
</div>
</div>
@endif
@if(auth()->user()->hasRole('admin') && $user->enabled)
<dialog id="user-disable-dialog" class="app-user-disable-dialog" aria-labelledby="user-disable-title" aria-describedby="user-disable-description">
    <h2 id="user-disable-title">Are you sure?</h2>
    <p id="user-disable-description">Disabling an account will remove any existing roles and will prevent the user from signing in.</p>
    <div class="actions">
        <button type="button" id="user-disable-cancel" autofocus>Cancel</button>
        <button type="submit" form="user-disable-form" class="app-user-disable-confirm" id="user-disable-confirm">Disable Account</button>
    </div>
</dialog>
<script src="/user-actions.js?v=disable-confirm-1" defer></script>
@endif
@endsection
