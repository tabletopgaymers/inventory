@extends('layout')
@section('title', 'Sign in')
@section('content')
<p>Use your Tabletop Gaymers Microsoft account or invited tenant account.</p>
<form method="get" action="/auth/microsoft/redirect"><button class="primary">Sign in with Microsoft</button><label class="app-choice"><input type="checkbox" name="remember" value="1"> Remember me — private computer (optional)</label></form>
@endsection
