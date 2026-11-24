{{--
Use shared action styling for a button or a navigation link. An enabled action with an `href` renders as a link. Use `type="submit"` for a form action. A disabled action renders as a disabled button even when it has an `href`.

The variants are `secondary`, `primary`, `danger`, `destructive`, `new`, and `changed`. Use `size="small"` for compact actions. The `delete`, `clear`, `dismiss`, `filter`, and `copy` controls provide specialized styling. Supply an accessible name for an icon-only action and keep form attributes and behavior with the caller.

## Slots

The default slot supplies the label or icon. Leave it empty with `variant="new"` to use the standard ＋ New label.

## Examples

See [Catalog index](resources/views/catalog/index.blade.php) for a New link and [Buttons](/style-guide/forms#buttons) for variants and controls.
--}}@props(['href' => null, 'variant' => 'secondary', 'size' => null, 'control' => null, 'type' => 'button', 'disabled' => false])
@php
    if (!in_array($variant, ['secondary', 'primary', 'danger', 'destructive', 'new', 'changed'], true)) throw new InvalidArgumentException('Unsupported action variant: '.$variant);
    if ($control !== null && !in_array($control, ['delete', 'clear', 'dismiss', 'filter', 'copy'], true)) throw new InvalidArgumentException('Unsupported action control: '.$control);
    $classes = ['app-action', 'app-action-'.$variant, 'app-action-small' => $size === 'small', 'app-control' => $control !== null];
    if ($control !== null) $classes[] = 'app-control-'.$control;
    $label = $variant === 'new' && $slot->isEmpty() ? '＋ New' : $slot;
@endphp
@if($href && ! $disabled)<a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $label }}</a>
@else<button type="{{ $type }}" @disabled($disabled) {{ $attributes->class($classes) }}>{{ $label }}</button>@endif
