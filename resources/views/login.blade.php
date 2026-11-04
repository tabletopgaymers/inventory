@extends('layout')
@section('title', 'Sign in')
@section('content')
<p>Use your Tabletop Gaymers Microsoft account or invited tenant account.</p>
<form method="get" action="/auth/microsoft/redirect"><x-app.action variant="primary" type="submit" >Sign in with Microsoft</x-app.action><label class="app-choice"><input type="checkbox" data-optional name="remember" value="1"><span data-field-label>Remember me — private computer</span></label></form>
@endsection
