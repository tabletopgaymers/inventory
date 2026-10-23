@props(['id', 'labelledby', 'active' => false])
<section {{ $attributes }} id="{{ $id }}" role="tabpanel" aria-labelledby="{{ $labelledby }}" @if(!$active) hidden @endif>{{ $slot }}</section>
