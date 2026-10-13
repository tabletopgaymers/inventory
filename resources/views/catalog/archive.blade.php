@extends('layout')
@section('title', 'Archive '.$record->name)
@section('content')
<nav class="app-breadcrumb" aria-label="Breadcrumb"><a href="{{ $indexUrl }}">{{ $label }}</a><span>›</span><a href="{{ $actionBase }}/edit">{{ $record->name }}</a><span>› Archive</span></nav>
@if($record->state === 'archived')
<p>This record is already archived. Restore it to Inactive from its edit page before activating it.</p>
<x-app.action href="{{ $actionBase }}/edit">Back to Edit</x-app.action>
@elseif($kind === 'storage_locations' && $record->is_central)
<p class="warning">Central must remain the distinguished active storage location.</p>
<x-app.action href="{{ $actionBase }}/edit">Back to Edit</x-app.action>
@else
<p>Archive <strong>{{ $record->name }}</strong>?</p>
<p>The record, associated records, inventory and earlier history will be retained. This changes only the selected record. Restoration returns it to Inactive; activation is a separate action.</p>
<form method="post" action="{{ $actionBase }}/lifecycle">@csrf
    <input type="hidden" name="action" value="archive"><input type="hidden" name="confirm" value="1">
    <div class="actions"><x-app.action variant="danger" href="{{ $actionBase }}/edit">Cancel</x-app.action><x-app.action variant="danger" type="submit">Archive</x-app.action></div>
</form>
@endif
@endsection
