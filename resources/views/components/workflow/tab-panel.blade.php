{{--
Use for the contents associated with one in-page tab. Pass its unique ID, the matching tab ID as labelledby, and whether it should start active.

## Slots

The default slot holds that tab’s contents.

## Examples

[Purchase work](resources/views/purchase/work.blade.php).
--}}@props(['id', 'labelledby', 'active' => false])
<section {{ $attributes }} id="{{ $id }}" role="tabpanel" aria-labelledby="{{ $labelledby }}" @if(!$active) hidden @endif>{{ $slot }}</section>
