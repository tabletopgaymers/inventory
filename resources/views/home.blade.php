@extends('layout')
@section('title', 'TG Inventory')
@section('content')
<p>Signed in as {{ auth()->user()->displayName() }}.</p><p><a href="/users">Open the user directory</a> or <a href="/profile">edit your profile</a>.</p>
@endsection
