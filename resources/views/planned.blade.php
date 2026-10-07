@extends('layout')
@section('title', $title)
@section('title-status')<x-app.status-badge status="Planned" />@endsection
@section('content')
<p class="intro">{{ $purpose }}</p>
<p>This area is planned for {{ $phase }}. Its actions are not available yet.</p>
<h2>Intended actions</h2>
<ul>@foreach($actions as $action)<li>{{ $action }}</li>@endforeach</ul>
<h2>Related destinations</h2>
<ul class="related-links">@foreach($related as $href => $label)<li><a href="{{ $href }}">{{ $label }}</a></li>@endforeach</ul>
<h2>Workflow preview</h2>
<p>This is a separate demonstration with sample data. Changes there do not update this application.</p>
<p><a href="https://tg-inventory.delugeia.com/#{{ ['item-history' => 'item', 'location-counts' => 'counts'][$page] ?? $page }}" target="_blank" rel="noopener noreferrer">View workflow preview (opens in a new tab)</a></p>
@endsection
