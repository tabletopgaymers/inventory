@extends('layout')
@section('title', 'Sign in')
@section('content')
<p>Use your Tabletop Gaymers Microsoft account or invited tenant account.</p>
<form method="get" action="/auth/microsoft/redirect"><button class="primary">Sign in with Microsoft</button><label class="checkbox"><input type="checkbox" name="remember" value="1"> Remember me — private computer</label></form>
@endsection
