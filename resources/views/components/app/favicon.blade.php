@php($faviconName = config('app.env') === 'production' ? 'inventory-purple' : 'inventory-red')
@foreach([16, 32, 64] as $size)
    <link rel="icon" type="image/png" sizes="{{ $size }}x{{ $size }}" href="{{ asset('favicon/'.$faviconName.'-'.$size.'.png') }}">
@endforeach
<link rel="icon" type="image/svg+xml" sizes="any" href="{{ asset('favicon/'.$faviconName.'.svg') }}">
