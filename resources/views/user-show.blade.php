@extends('layout')
@section('title', $user->displayName())
@if(auth()->user()->hasRole('admin') || (int) auth()->id() === (int) $user->id)
@section('page-actions')<a class="button" href="{{ (int) auth()->id() === (int) $user->id ? '/profile' : '/profile?user='.$user->id }}">Edit</a>@endsection
@endif
@section('content-sections')
@php($zone = is_string($user->time_zone) && in_array($user->time_zone, \App\Support\DisplayDates::zones(), true) ? $user->time_zone : \App\Support\DisplayDates::DEFAULT_ZONE)
@php($ownUser = (int) auth()->id() === (int) $user->id)
<div class="app-content">
<div class="app-profile-columns">
<div>
<dl class="app-user-details">
    <dt>First name</dt><dd>{{ filled($user->first_name) ? $user->first_name : '—' }}</dd>
    <dt>Last name</dt><dd>{{ filled($user->last_name) ? $user->last_name : '—' }}</dd>
    <dt>Elevated roles</dt><dd>{{ implode(', ', array_diff($user->roles(), ['basic'])) ?: 'None' }}</dd>
    <dt>Microsoft account</dt><dd>{{ filled($user->provider_email) ? $user->provider_email : '—' }}</dd>
    <dt>TG email</dt><dd>{{ filled($user->contact_email) ? $user->contact_email : '—' }}</dd>
    <dt>TG email confirmation</dt><dd><strong @class(['app-confirmation-status', 'app-confirmation-unconfirmed' => !$user->validContact()])>{{ $user->validContact() ? 'Confirmed' : 'Not confirmed' }}</strong></dd>
</dl>
</div>
<div>
<dl class="app-user-details">
    <dt>Personal email</dt><dd>{{ filled($user->personal_email) ? $user->personal_email : '—' }}</dd>
    <dt>Mobile number</dt><dd>{{ filled($user->mobile_number) ? $user->mobile_number : '—' }}</dd>
    <dt>Mailing address</dt><dd class="app-user-address">{{ filled($user->mailing_address) ? $user->mailing_address : '—' }}</dd>
    <dt>Time zone</dt><dd>{{ $zone }}</dd>
    <dt>Display density</dt><dd>{{ \App\Models\User::DISPLAY_DENSITIES[$user->display_density ?? 'standard'] ?? 'Standard' }}</dd>
</dl>
</div>
</div>
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
@php($roleAction = $user->hasRole($role) ? 'Remove' : 'Grant')
@php($protectedAdmin = $ownUser && $role === 'admin' && $user->hasRole('admin'))
<form class="inline" id="user-role-{{ $role }}-form" method="post" action="/users/{{ $user->id }}/roles" data-user-role-form data-role-label="{{ $roleAction.' '.$role }}" data-role-removal="{{ $roleAction === 'Remove' ? '1' : '0' }}" data-role-confirmation="{{ $roleAction.' the '.$role.' role '.($roleAction === 'Remove' ? 'from ' : 'to ').$user->displayName().'?' }}">
@csrf<input type="hidden" name="_deliberate" value="1"><input type="hidden" name="role" value="{{ $role }}"><input type="hidden" name="grant" value="{{ $user->hasRole($role) ? '0' : '1' }}">
<button type="button" data-user-role-trigger aria-haspopup="dialog" aria-controls="user-role-dialog" @disabled($protectedAdmin) @if($protectedAdmin)title="Another admin must remove your admin role." aria-description="Another admin must remove your admin role."@endif>{{ $roleAction }} {{ $role }}<span class="sr-only"> for {{ $user->displayName() }}</span></button>
</form>
@endif
@endforeach
@endif
@if(auth()->user()->hasRole('admin'))
@if($user->enabled)
<form class="inline app-user-disable-action" id="user-disable-form" method="post" action="/users/{{ $user->id }}/disable">@csrf<input type="hidden" name="_deliberate" value="1"><button type="button" class="discard" id="user-disable-trigger" @disabled($ownUser) @if($ownUser)title="Another admin must disable your account." aria-description="Another admin must disable your account."@else aria-haspopup="dialog" aria-controls="user-disable-dialog"@endif>Disable account<span class="sr-only"> for {{ $user->displayName() }}</span></button></form>
@else
<form class="inline" method="post" action="/users/{{ $user->id }}/enable">@csrf<input type="hidden" name="_deliberate" value="1"><button>Enable account<span class="sr-only"> for {{ $user->displayName() }}</span></button></form>
@endif
@endif
</div>
</div>
@endif
@if($user->enabled && (auth()->user()->hasRole('admin') || auth()->user()->hasRole('manager') || auth()->user()->hasRole('procurement')))
<dialog id="user-role-dialog" class="app-user-disable-dialog" aria-labelledby="user-role-title" aria-describedby="user-role-description">
    <h2 id="user-role-title">Are you sure?</h2>
    <p id="user-role-description"></p>
    <div class="actions">
        <button type="button" id="user-role-cancel" autofocus>Cancel</button>
        <button type="submit" id="user-role-confirm">Confirm role change</button>
    </div>
</dialog>
@endif
@if(auth()->user()->hasRole('admin') && $user->enabled && !$ownUser)
<dialog id="user-disable-dialog" class="app-user-disable-dialog" aria-labelledby="user-disable-title" aria-describedby="user-disable-description">
    <h2 id="user-disable-title">Are you sure?</h2>
    <p id="user-disable-description">Disabling an account will remove any existing roles and will prevent the user from signing in.</p>
    <div class="actions">
        <button type="button" id="user-disable-cancel" autofocus>Cancel</button>
        <button type="submit" form="user-disable-form" class="app-user-disable-confirm" id="user-disable-confirm">Disable account</button>
    </div>
</dialog>
@endif
@vite('resources/js/user-actions.js')
@endsection
