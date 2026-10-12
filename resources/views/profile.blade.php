@extends('layout')
@php
    $ownProfile = (int) $profileUser->id === (int) auth()->id();
    $profileUrl = '/profile'.($ownProfile ? '' : '?user='.$profileUser->id);
    $profileZone = is_string($profileUser->time_zone) && in_array($profileUser->time_zone, \App\Support\DisplayDates::zones(), true) ? $profileUser->time_zone : \App\Support\DisplayDates::DEFAULT_ZONE;
@endphp
@section('title', $ownProfile ? 'My profile' : 'Edit profile: '.$profileUser->displayName())
@section('page-actions')
<a class="button discard" href="{{ $profileUrl }}">Discard</a>
<button type="submit" class="primary" form="profile-form">Save</button>
@endsection
@section('content')
<form id="profile-form" method="post" action="{{ $profileUrl }}">@csrf<input type="hidden" name="_deliberate" value="1">
@if(!app(\App\Support\ProfilePreferences::class)->state(\Illuminate\Support\Facades\DB::connection())['ready'])<p class="warning">Profile saving is waiting for the contact/preferences migration. Your existing profile is retained.</p>@endif
<div class="app-profile-columns">
<div>
<label>First name<input name="first_name" required maxlength="100" autocomplete="given-name" value="{{ old('first_name', $profileUser->first_name) }}"></label>
<label>Last name<input name="last_name" required maxlength="100" autocomplete="family-name" value="{{ old('last_name', $profileUser->last_name) }}"></label>
<label>Elevated roles<input readonly value="{{ implode(', ', array_diff($profileUser->roles(), ['basic'])) ?: 'None' }}"></label>
<label>Microsoft account<input readonly value="{{ $profileUser->provider_email ?: 'Not supplied' }}"></label>
<label>TG email<input data-optional type="email" name="contact_email" maxlength="255" placeholder="youremail@tabletopgaymers.org" value="{{ old('contact_email', $profileUser->contact_email) }}"></label>
<div class="app-profile-confirmation" id="tg-email-confirmation">TG email confirmation</div>
@if($ownProfile)
<label class="app-choice"><input type="checkbox" name="contact_attested" value="1" aria-describedby="tg-email-confirmation" @checked(old('contact_attested'))><span>Confirm my TG email address. Current status: <strong @class(['app-confirmation-status', 'app-confirmation-unconfirmed' => !$profileUser->validContact()])>{{ $profileUser->validContact() ? 'Confirmed' : 'Not confirmed' }}</strong></span></label>
@else
<p>Current status: <strong @class(['app-confirmation-status', 'app-confirmation-unconfirmed' => !$profileUser->validContact()])>{{ $profileUser->validContact() ? 'Confirmed' : 'Not confirmed' }}</strong>. This user must confirm their own TG email address.</p>
@endif
<p class="app-field-help">Elevated roles require a TG email address and {{ $ownProfile ? 'your confirmation' : 'confirmation by this user' }}. Changing the address clears its previous confirmation. No verification email is sent.</p>
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
