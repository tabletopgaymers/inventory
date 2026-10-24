@props(['values', 'notes' => '', 'notesLabel' => 'Notes'])
<div class="print-header">
    <dl class="print-details">
        @foreach($values as $label => $value)<dt>{{ $label }}:</dt><dd>{{ $value }}</dd>@endforeach
    </dl>
    <fieldset class="print-notes" aria-label="{{ $notesLabel }}"><p>{{ $notes }}</p></fieldset>
</div>
