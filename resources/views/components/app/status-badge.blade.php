@props(['status', 'label' => null])
@php($tone = config('status-badges.statuses.'.strtolower(trim($status)), config('status-badges.default')))
<span data-status-badge data-status="{{ $status }}" data-status-tone="{{ $tone }}" {{ $attributes->class(['app-status', 'status-badge']) }}>{{ $label ?? config('status-badges.labels.'.strtolower(trim($status)), $status) }}</span>
