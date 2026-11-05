@extends('layout')
@php
    $stepLabels = config('purchase-workflow.tabs');
    $base = '/purchase/work/'.$token;
@endphp
@section('title', 'Purchase request')
@section('subtitle'){{ $values['title'] ?? $row->title }}@endsection
@section('title-status')<x-app.status-badge :status="$row->status" />@endsection
@section('page-actions')
@if($editable)<x-workflow.save-progress form="purchase-receive-form" />@endif
@endsection
@section('content-sections')
@vite('resources/js/purchase-receive.js')
<x-app.panel-stack>
<div data-progress-feedback hidden></div>
@include('purchase.tabs')
@if($reviewErrors)<div class="error" role="alert">@foreach($reviewErrors as $messages)@foreach((array)$messages as $message)<p>{{ $message }}</p>@endforeach @endforeach<p>Correct the saved <a href="{{ $base }}?tab=invoice">{{ $stepLabels['invoice'] }}</a> or <a href="{{ $base }}?tab=charges">{{ $stepLabels['charges'] }}</a> before completing.</p></div>@endif
<form id="purchase-receive-form" class="app-content" action="{{ $base }}/receive" method="post" data-purchase-receive>
@csrf<input type="hidden" name="purchase_form" value="receive"><input type="hidden" name="revision" value="{{ $revision }}">
<x-workflow.heading><h2>{{ config('purchase-workflow.panels.receive') }}</h2>@if($editable)<x-app.action variant="primary" type="submit" name="action" value="complete" :disabled="!$preview || count($reviewErrors)>0">{{ config('purchase-workflow.buttons.receive') }}</x-app.action>@endif</x-workflow.heading>
<div class="app-columns app-columns-two">
<x-app.detail-list><dt>Title</dt><dd>{{ $values['title'] }}</dd><dt>Supplier</dt><dd>{{ $preview['vendor_snapshot']['name'] ?? '—' }}</dd><dt>Deliver to</dt><dd>{{ $values['delivery_name'] ?? '—' }}</dd><dt>Mailing address</dt><dd>{{ $values['delivery_address'] ?? '—' }}</dd></x-app.detail-list>
<div>
<x-app.detail-list><dt>Receiver</dt><dd>{{ $receiverName }}</dd>@if(!$editable)<dt>Date received</dt><dd>{{ $receivedDate ? \App\Support\DisplayDates::businessDate($receivedDate) : '—' }}</dd>@endif</x-app.detail-list>
@if($editable)<x-app.field label="Date received" for="purchase-received-date" name="received_date"><input type="date" id="purchase-received-date" name="received_date" value="{{ $receivedDate }}" required></x-app.field>@endif
</div>
</div>
</form>
@if($preview)@include('purchase.partials.acquisition-breakdown', ['showRecalculate'=>false])@endif
<x-app.collapsible-panel title="Permanent notes"><form method="post" action="{{ $base }}/note">@csrf<label>Note<textarea name="note" required maxlength="5000" rows="3"></textarea></label><x-app.action type="submit">Add note</x-app.action></form>@foreach($notes as $note)<article><p><strong>{{ $note->actor_name }}</strong> · {{ \App\Support\DisplayDates::timestamp($note->occurred_at) }}</p><p class="plain-text">{{ $note->body }}</p></article>@endforeach</x-app.collapsible-panel>
@if($editable)<x-app.collapsible-panel title="Other actions"><form class="app-inline-group" method="post" action="{{ $base }}/transition" data-confirm-transition>@csrf<input type="hidden" name="revision" value="{{ $revision }}"><x-app.action type="submit" name="action" value="cancel">Cancel purchase</x-app.action></form></x-app.collapsible-panel>@endif
</x-app.panel-stack>
@endsection
