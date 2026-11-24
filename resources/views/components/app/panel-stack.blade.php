{{--
Use to apply the shared density-based vertical gap to sibling panels. An existing wrapper such as a form can use app-panel-stack directly.

## Slots

Put adjacent panels in the default slot.

## Example

[Shared page layout](resources/views/layout.blade.php).
--}}<div {{ $attributes->class(['app-panel-stack']) }}>{{ $slot }}</div>
