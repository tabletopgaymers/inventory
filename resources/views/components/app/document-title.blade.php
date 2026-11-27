{{--
Use inside the document's title element to format the browser-tab title. Supply the complete base title. The configured environment determines whether LCL, DEV, or STG is added.

## Slots

No caller slots.

## Example

[Application shell](resources/views/components/app/shell.blade.php) and [print layout](resources/views/components/print/layout.blade.php).
--}}@props(['title'])
@php
    $environmentPrefix = match (config('app.env')) {
        'local' => 'LCL',
        'development' => 'DEV',
        'stage' => 'STG',
        default => '',
    };
@endphp
{{ $environmentPrefix !== '' ? $environmentPrefix.' · '.$title : $title }}
