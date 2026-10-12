@extends('layout')
@php
    $ownProfile = (int) $profileUser->id === (int) auth()->id();
    $profileUrl = '/profile'.($ownProfile ? '' : '?user='.$profileUser->id);
    $profileZone = is_string($profileUser->time_zone) && in_array($profileUser->time_zone, \App\Support\DisplayDates::zones(), true) ? $profileUser->time_zone : \App\Support\DisplayDates::DEFAULT_ZONE;
@endphp
@section('title', $ownProfile ? 'My profile' : 'Edit profile: '.$profileUser->displayName())
@section('content')
<form method="post" action="{{ $profileUrl }}">@csrf<input type="hidden" name="_deliberate" value="1">
<label>First name (optional)<input name="first_name" maxlength="100" value="{{ old('first_name', $profileUser->first_name) }}"></label>
<label>Last name (optional)<input name="last_name" maxlength="100" value="{{ old('last_name', $profileUser->last_name) }}"></label>
<p>Microsoft email: {{ $profileUser->provider_email ?: 'Not supplied' }}</p><p>Roles: {{ implode(', ', $profileUser->roles()) ?: 'Basic access' }}</p>
<p>Effective time zone: {{ $profileZone }}. Stored timestamps remain UTC.</p>
@if(!app(\App\Support\ProfilePreferences::class)->state(\Illuminate\Support\Facades\DB::connection())['ready'])<p class="warning">Profile saving is waiting for the guarded time-zone migration. Your existing profile is retained.</p>@endif
<label>Time zone<select name="time_zone" required>@if(is_string(old('time_zone')) && !in_array(old('time_zone'), \App\Support\DisplayDates::zones(), true))<option value="{{ old('time_zone') }}" disabled selected>{{ old('time_zone') }} — choose a valid time zone</option>@endif @foreach(\App\Support\DisplayDates::zones() as $zone)<option value="{{ $zone }}" @selected(old('time_zone', $profileZone) === $zone)>{{ $zone }}</option>@endforeach</select></label>
<label>Organizational contact email (optional)<input type="email" name="contact_email" maxlength="255" value="{{ old('contact_email', $profileUser->contact_email) }}"></label>
<p>Elevated roles require an address at tabletopgaymers.org and {{ $ownProfile ? 'your confirmation' : 'confirmation by this user' }}. No verification email is sent.</p>
<p>Current confirmation: {{ $profileUser->contact_attested ? ($ownProfile ? 'Confirmed by you' : 'Confirmed by this user') : 'Not confirmed' }}. Changing the address clears its previous confirmation.</p>
@if($ownProfile)
<label class="app-choice"><input type="checkbox" name="contact_attested" value="1" @checked(old('contact_attested'))> I confirm this contact address is correct (optional).</label>
@else
<p>This user must confirm their own organizational contact address.</p>
@endif
<div class="actions"><a class="discard" href="{{ $profileUrl }}">Discard</a><button class="primary">Save</button></div></form>
@endsection
