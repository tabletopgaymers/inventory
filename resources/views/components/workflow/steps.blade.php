@props(['label'])
<div class="app-content workflow-tab-panel">
    <nav class="workflow-tabs" aria-label="{{ $label }}">{{ $slot }}</nav>
</div>
