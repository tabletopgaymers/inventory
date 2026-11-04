@props(['href' => null, 'variant' => 'secondary', 'size' => null, 'control' => null, 'type' => 'button', 'disabled' => false])
@php
    if (!in_array($variant, ['secondary', 'primary', 'danger', 'destructive', 'new', 'changed'], true)) throw new InvalidArgumentException('Unsupported action variant: '.$variant);
    if ($control !== null && !in_array($control, ['delete', 'clear', 'dismiss', 'filter'], true)) throw new InvalidArgumentException('Unsupported action control: '.$control);
    $classes = ['app-action', 'app-action-'.$variant, 'app-action-small' => $size === 'small', 'app-control' => $control !== null];
    if ($control !== null) $classes[] = 'app-control-'.$control;
@endphp
@if($href && ! $disabled)<a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else<button type="{{ $type }}" @disabled($disabled) {{ $attributes->class($classes) }}>{{ $slot }}</button>@endif
