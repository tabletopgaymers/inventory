@props(['tabs', 'prefix', 'active', 'label'])
<div class="app-content workflow-tab-panel">
    <div class="workflow-navigation">
        <div class="workflow-tabs" role="tablist" aria-label="{{ $label }}">
        @foreach($tabs as $key => $title)
            <button type="button" role="tab" id="{{ $prefix }}-tab-{{ $key }}" aria-controls="{{ $prefix }}-panel-{{ $key }}" aria-selected="{{ $active === $key ? 'true' : 'false' }}" tabindex="{{ $active === $key ? 0 : -1 }}" data-workflow-tab="{{ $key }}">{{ $title }}</button>
        @endforeach
        </div>
        {{ $slot }}
    </div>
</div>
