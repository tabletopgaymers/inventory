@extends('layout')
@section('title', 'Page unavailable')
@section('content')
<p>That page no longer exists or you do not have the rights to view it.</p>
<div class="app-actions"><x-app.action  href="/profile">My profile</x-app.action><x-app.action  href="/">Home</x-app.action></div>
@endsection
