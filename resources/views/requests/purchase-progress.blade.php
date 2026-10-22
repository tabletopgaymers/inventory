@php
    $progressRow = $progressRow ?? null;
    $progressActive = $progressActive ?? 'review';
    $progressSteps = \App\Support\PurchaseProgress::navigation(auth()->user(), $progressRow, $progressActive, $progressToken ?? null);
@endphp
@section('purchase-save')
<button type="button" class="primary app-save-progress" data-purchase-save-progress data-save-shortcut @disabled(empty($progressSaveUrl)) aria-busy="false" @if(empty($progressSaveUrl)) title="No editable progress on this page. Notes and status actions are saved separately." @endif><span data-save-label>Save progress</span><span class="app-search-spinner" aria-hidden="true"></span></button>
@endsection
<div class="app-relocation-progress" data-purchase-progress data-save-url="{{ $progressSaveUrl ?? '' }}" data-save-key="{{ auth()->id() }}:{{ $progressKey }}" data-save-revision="{{ $progressRevision ?? $progressRow?->revision ?? 0 }}" data-current-revision="{{ $progressRow?->revision ?? 0 }}" data-retained-ack="{{ session('success') === 'Permanent note added.' ? 'purchase-note' : (session('success') === 'Purchase status saved.' ? 'purchase-shipping' : '') }}" data-save-version="{{ $progressVersion ?? 0 }}" data-save-dirty="{{ !empty($progressDirty) ? 'true' : 'false' }}">
<nav aria-label="Purchase progress"><ol class="app-relocation-steps">
@if(!empty($draft['action_ack']))<input type="hidden" data-purchase-action-ack value="{{ $draft['action_ack'] }}">@endif
@foreach($progressSteps as $progressStep)<li>@if($progressStep['active'])<strong aria-current="step">{{ ucfirst($progressStep['step']) }}</strong>@elseif($progressStep['href'])<a href="{{ $progressStep['href'] }}" @if(!empty($progressToken) && in_array($progressStep['step'], ['request', 'items'])) data-purchase-work-action="{{ $progressStep['step'] }}" @endif>{{ ucfirst($progressStep['step']) }}</a>@else<span>{{ ucfirst($progressStep['step']) }}</span>@endif</li>@endforeach
</ol></nav>
</div>
<div data-purchase-save-feedback role="status" hidden></div>
<script src="/purchase-progress.js?v=purchase-wizard-2-sentence-case-1" defer></script>
