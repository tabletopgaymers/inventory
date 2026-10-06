@extends('layout')
@section('title', 'TG Inventory')
@section('content')
<p>Signed in as {{ auth()->user()->displayName() }}.</p>
<p><a href="/users">Open the user directory</a> or <a href="/profile">edit your profile</a>.</p>
<h2>Find your next task</h2>
<ul class="destination-list">
<li><a href="/inventory">Inventory</a> — find supplies and open their item history. <span class="status-badge">Planned</span></li>
<li><a href="/relocations">Relocations</a> — request supplies from storage. <span class="status-badge">Planned</span></li>
<li><a href="/purchases">Purchases</a> — request supplies to purchase. <span class="status-badge">Planned</span></li>
<li><a href="/inventory/location-counts">Location Counts</a> — count supplies at a storage location. <span class="status-badge">Planned</span></li>
</ul>
@endsection
