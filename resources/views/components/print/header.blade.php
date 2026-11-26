{{--
Use for the metadata section of a printable document. Supply the labels and values in display order. The notes area remains available when notes are blank.

## Slots

No caller slots.

## Examples

[Relocation worksheet](resources/views/relocation/print.blade.php) and [Print specimen](resources/views/style-guide/print.blade.php).
--}}@props(['values', 'notes' => '', 'notesLabel' => 'Notes'])
<div class="print-header">
    <dl class="print-details">
        @foreach($values as $label => $value)<dt>{{ $label }}:</dt><dd>{{ $value }}</dd>@endforeach
    </dl>
    <fieldset class="print-notes" aria-label="{{ $notesLabel }}"><p>{{ $notes }}</p></fieldset>
</div>
