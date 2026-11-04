{{-- Dynamic controls clone the real component instead of rebuilding its CSS contract. --}}
@foreach(['secondary', 'primary', 'danger', 'destructive', 'new', 'changed'] as $variant)
<template data-action-template="{{ $variant }}:"><x-app.action :variant="$variant" /></template>
@endforeach
@foreach(['delete', 'clear', 'dismiss', 'filter'] as $control)
<template data-action-template="secondary:{{ $control }}"><x-app.action :control="$control" /></template>
@endforeach
