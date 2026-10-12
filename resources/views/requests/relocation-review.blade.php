@extends('layout')
@section('title', 'Review relocation request')
@section('relocation-progress')
@include('requests.relocation-progress', ['progressRow' => $row, 'progressContext' => 'review', 'progressActive' => 'review', 'progressToken' => $token, 'progressSaveUrl' => '/relocations/work/'.$token, 'progressKey' => 'work:'.$token, 'progressRevision' => $draft['revision'], 'progressVersion' => $draft['version'] ?? 0, 'progressDirty' => \App\Support\RelocationProgress::dirty($draft)])
@endsection
@section('content')

<p data-relocation-identity>{{ $row ? 'Relocation #'.$row->id.' · '.$row->status : 'New relocation' }}</p>

<h2 data-relocation-title>{{ $draft['title'] }}</h2><p>{{ $locations->firstWhere('id',(int)$draft['source_location_id'])?->name }} → {{ $locations->firstWhere('id',(int)$draft['destination_location_id'])?->name }}</p><p class="plain-text" data-relocation-details>{{ $draft['details'] }}</p>
<p>Current hypothetical projections; nothing has been submitted or reserved.</p><x-app.table-region label="Relocation review" class="table-wrap"><table><thead><tr><th>Item</th><th>Source: current → projected</th><th>Destination: current → projected</th><th>Request</th></tr></thead><tbody>@include('requests.relocation-rows',['quantities'=>$draft['lines']])</tbody></table></x-app.table-region>
<form id="relocation-work" method="post" action="/relocations/work/{{ $token }}" class="actions" data-request-form data-relocation-form="work"><input type="hidden" name="progress_step" value="review">@csrf<input type="hidden" name="_deliberate" value="1"><input type="hidden" name="title" value="{{ $draft['title'] }}"><input type="hidden" name="details" value="{{ $draft['details'] }}"><input type="hidden" name="source_location_id" value="{{ $draft['source_location_id'] }}"><input type="hidden" name="destination_location_id" value="{{ $draft['destination_location_id'] }}">@foreach($draft['lines'] as $item=>$quantity)<input type="hidden" name="lines[{{ $item }}]" value="{{ $quantity }}">@endforeach<a class="button" href="/relocations/work/{{ $token }}?step=items">Back: items</a>@if($submitter)<button class="primary" formaction="/relocations/work/{{ $token }}/submit">Submit request</button>@else<p>Submission requires an enabled admitted account owning this request or an authorized manager.</p>@endif</form>
<script src="/requests.js?v=relocation-progress-1-sentence-case-1" defer></script>
@endsection
