{{--
Include once in the document head for the SVG favicon and PNG fallbacks at 16, 32, and 64 pixels. Production uses purple and other environments use red.

## Slots

No caller slots.

## Example

[Application shell](resources/views/components/app/shell.blade.php).
--}}@php($faviconName = config('app.env') === 'production' ? 'inventory-purple' : 'inventory-red')
@foreach([16, 32, 64] as $size)
    <link rel="icon" type="image/png" sizes="{{ $size }}x{{ $size }}" href="{{ asset('favicon/'.$faviconName.'-'.$size.'.png') }}">
@endforeach
<link rel="icon" type="image/svg+xml" sizes="any" href="{{ asset('favicon/'.$faviconName.'.svg') }}">
