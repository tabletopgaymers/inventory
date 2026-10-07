@props(['label', 'for' => null, 'name' => null, 'help' => null, 'error' => null])
@php($fieldId = $for ?: 'field-'.\Illuminate\Support\Str::uuid())
@php($fieldError = $error ?: ($name ? $errors->first($name) : null))
<div data-field-id="{{ $fieldId }}" {{ $attributes->class(['app-field']) }}>
    @if($for)<label for="{{ $for }}">{{ $label }}</label>{{ $slot }}@else<label>{{ $label }}{{ $slot }}</label>@endif
    @if($help)<p id="{{ $fieldId }}-help" class="app-field-help">{{ $help }}</p>@endif
    @if($fieldError)<p id="{{ $fieldId }}-error" class="app-field-error">{{ $fieldError }}</p>@endif
</div>
