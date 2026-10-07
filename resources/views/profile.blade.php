@extends('layout')
@section('title', 'My profile')
@section('content')
<form method="post" action="/profile">@csrf<input type="hidden" name="_deliberate" value="1">
<label>First name (optional)<input name="first_name" maxlength="100" value="{{ old('first_name', auth()->user()->first_name) }}"></label>
<label>Last name (optional)<input name="last_name" maxlength="100" value="{{ old('last_name', auth()->user()->last_name) }}"></label>
<p>Microsoft email: {{ auth()->user()->provider_email ?: 'Not supplied' }}</p><p>Roles: {{ implode(', ', auth()->user()->roles()) ?: 'Basic access' }}</p>
<p>Effective time zone: {{ \App\Support\DisplayDates::effectiveZone() }}. Stored timestamps remain UTC.</p>
@if(!app(\App\Support\ProfilePreferences::class)->state(\Illuminate\Support\Facades\DB::connection())['ready'])<p class="warning">Profile saving is waiting for the guarded time-zone migration. Your existing profile is retained.</p>@endif
<label>Time zone<select name="time_zone" required>@if(is_string(old('time_zone')) && !in_array(old('time_zone'), \App\Support\DisplayDates::zones(), true))<option value="{{ old('time_zone') }}" disabled selected>{{ old('time_zone') }} — choose a valid time zone</option>@endif @foreach(\App\Support\DisplayDates::zones() as $zone)<option value="{{ $zone }}" @selected(old('time_zone', \App\Support\DisplayDates::effectiveZone()) === $zone)>{{ $zone }}</option>@endforeach</select></label>
<label>Organizational contact email (optional)<input type="email" name="contact_email" maxlength="255" value="{{ old('contact_email', auth()->user()->contact_email) }}"></label>
<p>Elevated roles require an address at tabletopgaymers.org and your confirmation. No verification email is sent.</p>
<p>Current confirmation: {{ auth()->user()->contact_attested ? 'Confirmed by you' : 'Not confirmed' }}. Changing the address clears its previous confirmation.</p>
<label class="app-choice"><input type="checkbox" name="contact_attested" value="1" @checked(old('contact_attested'))> I confirm this contact address is correct (optional).</label>
<div class="actions"><a class="discard" href="/profile">Discard</a><button class="primary">Save</button></div></form>
@endsection
