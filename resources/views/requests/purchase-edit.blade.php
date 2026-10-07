@extends('layout')

@section('title', $row ? 'Edit Purchase Request' : 'New Purchase Request')

@section('content')

<p>All request fields are optional. Save a blank Draft first, then add permanent notes separately.</p>

<form method="post" action="{{ $row ? '/purchases/'.$row->id : '/purchases' }}" data-request-form>@csrf<input type="hidden" name="_deliberate" value="1">@if($row)<input type="hidden" name="revision" value="{{ old('revision',$row->revision) }}">@else<input type="hidden" name="token" value="{{ $token }}">@endif

<label>Title (optional)<input name="title" maxlength="255" value="{{ old('title',$row?->title) }}"></label>

<label>Suggested merchant (optional)<input name="suggested_merchant" maxlength="255" value="{{ old('suggested_merchant',$row?->suggested_merchant) }}"></label>

<label>Request details (optional)<textarea name="details" maxlength="5000">{{ old('details',$row?->details) }}</textarea></label>

<div class="actions"><a class="discard" href="{{ $row ? '/purchases/'.$row->id : '/purchases' }}">Discard</a><button class="primary">{{ $row ? 'Save details' : 'Save Draft' }}</button></div></form>

<script src="/requests.js" defer></script>

@endsection
