{{--
Use for a workflow section header with nearby actions. It uses the section heading-row variant and adds shared workflow spacing. The caller sets the semantic heading level.

## Slots

The default slot holds the heading and action markup.

## Examples

[Relocation material table](resources/views/relocation/material-table.blade.php).
--}}<x-app.heading-row variant="section" {{ $attributes->class(['workflow-heading']) }}>{{ $slot }}</x-app.heading-row>
