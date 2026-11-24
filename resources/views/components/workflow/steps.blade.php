{{--
Use for workflow steps that navigate by link. Supply an accessible navigation label and set aria-current on the selected link.

## Slots

The default slot holds the step links. The caller decides which steps are accessible.

## Examples

[Purchase step links](resources/views/purchase/tabs.blade.php).
--}}@props(['label'])
<div class="workflow-tab-panel">
    <div class="workflow-navigation"><nav class="workflow-tabs" aria-label="{{ $label }}">{{ $slot }}</nav></div>
</div>
