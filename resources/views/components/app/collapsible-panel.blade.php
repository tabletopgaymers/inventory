{{--
Use a titled disclosure for content that can be hidden without leaving the page. Pass the title and add the native open attribute for an initially expanded panel. Add the app-collapsible-panel-mobile-padding class to match adjacent standard panels at narrow widths.

## Slots

The default slot holds the collapsible content.

## Example

[Saved List form](resources/views/saved-lists/form.blade.php).
--}}@props(['title'])
<details {{ $attributes->class(['app-collapsible-panel']) }}>
    <summary><h2>{{ $title }}</h2></summary>
    <div class="app-collapsible-panel-body">{{ $slot }}</div>
</details>
