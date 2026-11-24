{{--
Use a description list for label and value pairs on detail pages. Choose content, fixed, compact, or stacked to match the available space and context.

## Slots

Pass dt and dd pairs in the default slot.

## Example

[Saved List form](resources/views/saved-lists/form.blade.php).
--}}@props(['variant' => 'content'])
<dl {{ $attributes->class(['app-details', 'app-details-'.$variant]) }}>{{ $slot }}</dl>
