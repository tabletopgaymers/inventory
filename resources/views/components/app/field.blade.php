{{--
Use for one visible field label with optional help and validation feedback. With for, supply a control with the same ID. Otherwise the label wraps the control. Use name to look up validation feedback or supply an explicit error.

## Slots

The default slot holds the input, select, or textarea. The shell connects message IDs to supported controls.

## Example

[Saved List form](resources/views/saved-lists/form.blade.php) and [Forms](/style-guide/forms#fields).
--}}@props(['label', 'for' => null, 'name' => null, 'help' => null, 'error' => null])
@php($fieldId = $for ?: 'field-'.\Illuminate\Support\Str::uuid())
@php($fieldError = $error ?: ($name ? $errors->first($name) : null))
<div data-field-id="{{ $fieldId }}" {{ $attributes->class(['app-field']) }}>
    @if($for)<label for="{{ $for }}">{{ $label }}</label>{{ $slot }}@else<label>{{ $label }}{{ $slot }}</label>@endif
    @if($help)<p id="{{ $fieldId }}-help" class="app-field-help">{{ $help }}</p>@endif
    @if($fieldError)<p id="{{ $fieldId }}-error" class="app-field-error">{{ $fieldError }}</p>@endif
</div>
