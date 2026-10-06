@extends('layout')
@section('title', 'TG Inventory')
@section('content')
<p>Signed in as {{ auth()->user()->displayName() }}.</p>
<p><a href="/users">Open the user directory</a> or <a href="/profile">edit your profile</a>.</p>
<h2>Find your next task</h2>
<ul class="destination-list">
<li><a href="/inventory">Inventory</a> — search supplies, download matching quantities and open their item history.</li>
<li><a href="/relocations">Relocations</a> — request supplies from storage and save fulfillment preparation.</li>
<li><a href="/purchases">Purchases</a> — request supplies to purchase and save pre-order preparation.</li>
<li><a href="/inventory/location-counts">Location Counts</a> — save personal criteria, print a worksheet and reconcile counted supplies.</li>
</ul>
@endsection
