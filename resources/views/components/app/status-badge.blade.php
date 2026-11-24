{{--
Use for a status label with a shared tone. Supply the actual status to select the configured tone and default text. Override label when the context calls for different wording while keeping that tone.

## Slots

No caller slots.

## Examples

[Catalog view](resources/views/catalog/show.blade.php) and [Presentation](/style-guide/presentation#statuses).
--}}@props(['status', 'label' => null])
@php($tone = config('status-badges.statuses.'.strtolower(trim($status)), config('status-badges.default')))
<span data-status-badge data-status="{{ $status }}" data-status-tone="{{ $tone }}" {{ $attributes->class(['app-status', 'status-badge']) }}>{{ $label ?? config('status-badges.labels.'.strtolower(trim($status)), $status) }}</span>
