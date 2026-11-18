@props(['title'])
@php
    $environmentPrefix = match (config('app.env')) {
        'local' => 'LCL',
        'development' => 'DEV',
        'stage' => 'STG',
        default => '',
    };
@endphp
<title>{{ $environmentPrefix !== '' ? $environmentPrefix.' · '.$title : $title }}</title>
