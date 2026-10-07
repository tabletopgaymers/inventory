@extends('layout')
@section('title', 'Review Relocation Request')
@section('content')
<h2>{{ $draft['title'] }}</h2><p>{{ $locations->firstWhere('id',(int)$draft['source_location_id'])?->name }} → {{ $locations->firstWhere('id',(int)$draft['destination_location_id'])?->name }}</p><p class="plain-text">{{ $draft['details'] }}</p>
<p>Current hypothetical projections; nothing has been submitted or reserved.</p><x-app.table-region label="Relocation review" class="table-wrap"><table><thead><tr><th>Item</th><th>Source: current → projected</th><th>Destination: current → projected</th><th>Request</th></tr></thead><tbody>@include('requests.relocation-rows',['quantities'=>$draft['lines']])</tbody></table></x-app.table-region>
<form method="post" action="/relocations/work/{{ $token }}/submit" class="actions">@csrf<input type="hidden" name="_deliberate" value="1"><a class="button" href="/relocations/work/{{ $token }}">Edit draft</a>@if($submitter)<button class="primary">Submit request</button>@else<p>Submission requires an enabled admitted account owning this request or an authorized manager. You can return to the Draft.</p>@endif</form>
@endsection
