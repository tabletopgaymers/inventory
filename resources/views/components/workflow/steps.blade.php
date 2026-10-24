@props(['label'])
<div class="workflow-tab-panel">
    <div class="workflow-navigation"><nav class="workflow-tabs" aria-label="{{ $label }}">{{ $slot }}</nav></div>
</div>
