{{--
Use for in-page workflow navigation. Supply step keys and labels, a unique prefix, the initial key, and an accessible label. Give each panel the corresponding generated ID and mount the workflow tab controller for interaction.

## Slots

The optional default slot renders next to the tab list for related navigation.

## Examples

[Purchase work](resources/views/purchase/work.blade.php) and [Tab form](/style-guide/tab-form).
--}}@props(['tabs', 'prefix', 'active', 'label'])
<div class="workflow-tab-panel">
    <div class="workflow-navigation">
        <div class="workflow-tabs" role="tablist" aria-label="{{ $label }}">
        @foreach($tabs as $key => $title)
            <x-app.action type="button" role="tab" id="{{ $prefix }}-tab-{{ $key }}" aria-controls="{{ $prefix }}-panel-{{ $key }}" aria-selected="{{ $active === $key ? 'true' : 'false' }}" tabindex="{{ $active === $key ? 0 : -1 }}" data-workflow-tab="{{ $key }}">{{ $title }}</x-app.action>
        @endforeach
        </div>
        {{ $slot }}
    </div>
</div>
