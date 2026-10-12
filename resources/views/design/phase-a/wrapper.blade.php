@extends('design.phase-a.layout')
@section('title', 'Application frame')
@section('intro', 'The current shared application frame, with a sample two-panel user page.')
@section('guide-content')
@include('design.phase-a.user-example')
<div class="app-content" id="profile-example">
    <h2>Shared frame and panels</h2>
    <p>Header, menu, account controls, environment label and footer come from the application layout. Information and action panels share padding; buttons retain their own internal padding.</p>
    <p>Role controls align left and Disable account aligns right. The preview modal changes no account.</p>
    <p><a href="/design/phase-a/examples#users">Return to complete examples</a>.</p>
</div>
@include('design.phase-a.disable-preview')
@endsection
