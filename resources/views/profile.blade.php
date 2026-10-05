@extends('layout')
@section('title', 'My profile')
@section('content')
<form method="post" action="/profile">@csrf<input type="hidden" name="_deliberate" value="1">
<label>First name<input name="first_name" maxlength="100" value="{{ old('first_name', auth()->user()->first_name) }}"></label>
<label>Last name<input name="last_name" maxlength="100" value="{{ old('last_name', auth()->user()->last_name) }}"></label>
<p>Microsoft email: {{ auth()->user()->provider_email ?: 'Not supplied' }}</p><p>Roles: {{ implode(', ', auth()->user()->roles()) ?: 'Basic access' }}</p>
<label>Organizational contact email<input type="email" name="contact_email" maxlength="255" value="{{ old('contact_email', auth()->user()->contact_email) }}"></label>
<p>Elevated roles require an address at tabletopgaymers.org and your confirmation. No verification email is sent.</p>
<p>Current confirmation: {{ auth()->user()->contact_attested ? 'Confirmed by you' : 'Not confirmed' }}. Changing the address clears its previous confirmation.</p>
<label class="checkbox"><input type="checkbox" name="contact_attested" value="1" @checked(old('contact_attested'))> I confirm this contact address is correct.</label>
<div class="actions"><a class="discard" href="/profile">Discard</a><button class="primary">Save</button></div></form>
@endsection
