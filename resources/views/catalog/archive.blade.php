@extends('layout')
@section('title', 'Archive '.$record->name)
@section('content')
<nav class="app-breadcrumb" aria-label="Breadcrumb"><a href="/catalog/{{ $kind }}">{{ $label }}</a><span>›</span><a href="/catalog/{{ $kind }}/{{ $record->id }}/edit">{{ $record->name }}</a><span>› Archive</span></nav>
@if($record->state === 'archived')
<p>This record is already archived. Restore it to Inactive from its edit page before activating it.</p>
<x-app.action href="/catalog/{{ $kind }}/{{ $record->id }}/edit">Back to Edit</x-app.action>
@elseif($kind === 'storage_locations' && $record->is_central)
<p class="warning">Central must remain the distinguished active storage location.</p>
<x-app.action href="/catalog/{{ $kind }}/{{ $record->id }}/edit">Back to Edit</x-app.action>
@else
<p>Archive <strong>{{ $record->name }}</strong>?</p>
<p>The record, associated records, inventory and earlier history will be retained. This changes only the selected record. Restoration returns it to Inactive; activation is a separate action.</p>
<form method="post" action="/catalog/{{ $kind }}/{{ $record->id }}/lifecycle">@csrf
    <input type="hidden" name="action" value="archive"><input type="hidden" name="confirm" value="1">
    <div class="actions"><x-app.action variant="danger" href="/catalog/{{ $kind }}/{{ $record->id }}/edit">Cancel</x-app.action><x-app.action variant="danger" type="submit">Archive</x-app.action></div>
</form>
@endif
@endsection
