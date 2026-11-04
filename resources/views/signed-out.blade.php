@extends('layout')
@section('title', 'Signed out')
@section('content')
@if($microsoftLogout)<p>Your requested Microsoft sign-out has returned. Other Microsoft sessions may remain active.</p>
@elseif($siteLogout)<p>You have been signed out from this site, but not Microsoft.</p>
@else<p>You can sign in to TG Inventory or explicitly request Microsoft sign-out.</p>@endif
<form method="post" action="/auth/microsoft/logout">@csrf<x-app.action type="submit">Sign out of Microsoft</x-app.action></form>
<p>Microsoft sign-out may affect other Microsoft services in this browser.</p><a href="/login">Sign in to TG Inventory</a>
@endsection
