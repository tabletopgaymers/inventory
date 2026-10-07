@props(['href' => null, 'variant' => 'secondary', 'size' => null, 'type' => 'button', 'disabled' => false])
@php($classes = ['app-action', 'btn', 'btn-primary' => $variant === 'primary', 'btn-outline' => $variant !== 'primary', 'app-action-danger' => $variant === 'danger', 'btn-sm' => $size === 'small'])
@if($href && ! $disabled)<a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else<button type="{{ $type }}" @disabled($disabled) {{ $attributes->class($classes) }}>{{ $slot }}</button>@endif
