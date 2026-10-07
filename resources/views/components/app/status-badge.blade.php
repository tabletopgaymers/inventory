@props(['status'])
<span {{ $attributes->class(['app-status', 'status-badge', strtolower($status)]) }}>{{ $status }}</span>
