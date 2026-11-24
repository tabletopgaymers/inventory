{{--
The application shell includes these inert templates once so JavaScript can clone shared actions through createAction. The templates cover the six button variants and the secondary delete, clear, dismiss, and filter controls. JavaScript supplies labels, accessible names, and behavior.

## Slots

No caller slots.

## Example

[Application shell](resources/views/components/app/shell.blade.php) and [action cloning helper](resources/js/actions.js).
--}}{{-- Dynamic controls clone the real component instead of rebuilding its CSS contract. --}}
@foreach(['secondary', 'primary', 'danger', 'destructive', 'new', 'changed'] as $variant)
<template data-action-template="{{ $variant }}:"><x-app.action :variant="$variant" /></template>
@endforeach
@foreach(['delete', 'clear', 'dismiss', 'filter'] as $control)
<template data-action-template="secondary:{{ $control }}"><x-app.action :control="$control" /></template>
@endforeach
