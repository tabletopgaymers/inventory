@extends('layout')
@php
    $ownProfile = (int) $profileUser->id === (int) auth()->id();
    $profileUrl = '/profile'.($ownProfile ? '' : '?user='.$profileUser->id);
    $profileZone = is_string($profileUser->time_zone) && in_array($profileUser->time_zone, \App\Support\DisplayDates::zones(), true) ? $profileUser->time_zone : \App\Support\DisplayDates::DEFAULT_ZONE;
@endphp
@section('title', $ownProfile ? 'My profile' : 'Edit profile: '.$profileUser->displayName())
@section('page-actions')
<x-app.action variant="danger"  href="{{ $profileUrl }}">Discard</x-app.action>
<x-app.action variant="primary" type="submit"  form="profile-form">Save</x-app.action>
@endsection
@section('content')
<form id="profile-form" method="post" action="{{ $profileUrl }}">@csrf<input type="hidden" name="_deliberate" value="1">
@if(!app(\App\Support\BaselineProbe::class)->runtimeState(\Illuminate\Support\Facades\DB::connection())['profilePreferencesReady'])<p class="warning">Profile saving is waiting for the profile preferences migration. Your existing profile is retained.</p>@endif
<div class="app-columns app-columns-two app-columns-trim">
<div>
<label>First name<input name="first_name" required maxlength="100" autocomplete="given-name" value="{{ old('first_name', $profileUser->first_name) }}"></label>
<label>Last name<input name="last_name" required maxlength="100" autocomplete="family-name" value="{{ old('last_name', $profileUser->last_name) }}"></label>
<label>Elevated roles<input readonly value="{{ implode(', ', array_diff($profileUser->roles(), ['basic'])) ?: 'None' }}"></label>
<label>Microsoft account<input readonly value="{{ $profileUser->provider_email ?: 'Not supplied' }}"></label>
</div>
<div>
<label>Personal email<input data-optional type="email" name="personal_email" maxlength="255" placeholder="youremail@yourprovider.com" value="{{ old('personal_email', $profileUser->personal_email) }}"></label>
<label>Mobile number<input data-optional type="text" name="mobile_number" maxlength="100" autocomplete="tel" placeholder="555-555-1234" value="{{ old('mobile_number', $profileUser->mobile_number) }}"></label>
<label>Mailing address<textarea data-optional name="mailing_address" rows="4" maxlength="4000" autocomplete="street-address" placeholder="Provide your mailing address, including your name, in a format appropriate to your location.">{{ old('mailing_address', $profileUser->mailing_address) }}</textarea></label>
<label>Time zone<select name="time_zone" required>@if(is_string(old('time_zone')) && !in_array(old('time_zone'), \App\Support\DisplayDates::zones(), true))<option value="{{ old('time_zone') }}" disabled selected>{{ old('time_zone') }} — choose a valid time zone</option>@endif @foreach(\App\Support\DisplayDates::zones() as $zone)<option value="{{ $zone }}" @selected(old('time_zone', $profileZone) === $zone)>{{ $zone }}</option>@endforeach</select></label>
<label>Display density<select name="display_density" required
@if($ownProfile)
data-density-preview
@endif
>@foreach(\App\Models\User::DISPLAY_DENSITIES as $value => $label)<option value="{{ $value }}" @selected(old('display_density', $profileUser->display_density ?? 'standard') === $value)>{{ $label }}</option>@endforeach</select></label>
</div>
</div>
</form>
@endsection
