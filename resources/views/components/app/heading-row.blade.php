{{--
Use for a heading and adjacent actions or metadata. Choose section, page, or table to apply the corresponding shared layout and spacing. The caller supplies the heading level.

## Slots

Put the heading, supplemental content, and actions in the default slot.

## Example

[Table specimen](resources/views/style-guide/table-sample.blade.php).
--}}@props(['variant' => 'section'])
<div {{ $attributes->class(['app-heading-row', 'app-heading-row-'.$variant]) }}>{{ $slot }}</div>
