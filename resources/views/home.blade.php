@extends('layout')
@section('title', 'Tabletop Gaymers Inventory')
@section('content-sections')
@php
    $descriptions = [
        '/inventory' => 'Find items, check quantities by location, and view stock and cost history. Managers and administrators can correct stock; procurement officers and administrators can adjust unit costs.',
        '/relocation' => 'Request a transfer of supplies between locations and follow its shipment and receipt. Managers and administrators handle fulfillment, shipping and reconciliation.',
        '/purchase' => 'Request catalog or uncatalogued items, suggest a supplier, and choose a delivery address. Any signed-in user can submit a purchase request; procurement officers and administrators verify suppliers, place orders and record receipt.',
        route('reconcile.index', absolute: false) => 'Compare physical counts with recorded stock, print a worksheet, and review corrections before applying them. Managers and administrators can reconcile inventory.',
        '/catalog' => 'Browse categories, collections, items, locations, suppliers, purposes and programs. Managers and administrators maintain catalog records; procurement officers can verify suggested suppliers.',
        '/users' => 'Find user contact information and view profiles and roles. You can edit your own profile; administrators can edit other profiles and manage accounts.',
        '/events' => 'Under development. Plan event supplies, track distributions and reconcile leftovers. Managers and administrators manage event records.',
    ];
@endphp
@foreach(\App\Support\AppNavigation::destinations(auth()->user()) as $destination)
    <div class="app-content">
    <h3><a href="{{ $destination['href'] }}">{{ $destination['label'] }}</a></h3>
    <p>{{ $descriptions[$destination['href']] ?? '' }}</p>
    </div>
@endforeach
@endsection
