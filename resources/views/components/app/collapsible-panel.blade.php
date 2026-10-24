@props(['title'])
<details {{ $attributes->class(['app-collapsible-panel']) }}>
    <summary><h2>{{ $title }}</h2></summary>
    <div class="app-collapsible-panel-body">{{ $slot }}</div>
</details>
