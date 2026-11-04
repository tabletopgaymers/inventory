@extends('layout')
@section('title', 'TG Inventory')
@section('content')
<p>Signed in as {{ auth()->user()->displayName() }}.</p>
<p><a href="/users">Open the user directory</a> or <a href="/profile">edit your profile</a>.</p>
<h2>Find your next task</h2>
<ul class="destination-list">
<li><a href="/inventory">Inventory</a> — search supplies, download matching quantities and open their item history.</li>
<li><a href="/relocation">Relocations</a> — request supplies from storage and follow shipment and receipt.</li>
<li><a href="/purchase">Purchases</a> — request supplies to purchase and follow ordering and receipt.</li>
<li><a href="/inventory/location-counts">Location counts</a> — save personal criteria, print a worksheet and reconcile counted supplies.</li>
<li><a href="/events">Events</a> — plan supplies, save shared counts and finalize distribution and leftovers once.</li>
</ul>
@endsection
