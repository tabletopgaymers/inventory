@php
    $progressRow = $progressRow ?? null;
    $progressContext = $progressContext ?? 'show';
    $progressSteps = \App\Support\RelocationProgress::navigation(auth()->user(), $progressRow, $progressContext, $progressActive ?? null, $progressToken ?? null, $progressReadOnly ?? false);
@endphp
@section('relocation-save')
<button type="button" class="primary app-save-progress" data-save-progress @disabled(empty($progressSaveUrl)) aria-busy="false" @if(empty($progressSaveUrl)) title="No editable progress on this page." @endif><span data-save-label>Save progress</span><span class="app-search-spinner" aria-hidden="true"></span></button>
@endsection
<div class="app-relocation-progress" data-relocation-progress data-save-url="{{ $progressSaveUrl ?? '' }}" data-save-key="{{ auth()->id() }}:{{ $progressKey ?? 'index' }}" data-save-revision="{{ $progressRevision ?? $progressRow?->revision ?? '' }}" data-save-version="{{ $progressVersion ?? 0 }}" data-save-dirty="{{ !empty($progressDirty) ? 'true' : 'false' }}">
<nav aria-label="Relocation progress"><ol class="app-relocation-steps">
@foreach($progressSteps as $progressStep)<li>@if($progressStep['active'])<strong aria-current="step">{{ ucfirst($progressStep['step']) }}</strong>@elseif($progressStep['href'])<a href="{{ $progressStep['href'] }}" @if($progressStep['action']) data-relocation-work-action="{{ $progressStep['action'] }}" @endif>{{ ucfirst($progressStep['step']) }}</a>@else<span>{{ ucfirst($progressStep['step']) }}</span>@endif</li>@endforeach
</ol></nav>
</div>
<div data-save-feedback role="status" hidden></div>
<script src="/relocation-progress.js?v=placement-1-sentence-case-1" defer></script>
