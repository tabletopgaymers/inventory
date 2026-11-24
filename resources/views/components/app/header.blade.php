{{--
Use for the application top bar and its environment indicator. The configured environment supplies the default. Use the environment override for a specimen.

## Slots

Put account and other top-bar controls in the default slot.

## Example

[Application shell](resources/views/components/app/shell.blade.php).
--}}@props(['environment' => null])
@php
    $environmentStatus = match ($environment ?? config('app.env')) {
        'local' => ['tone' => 'local', 'label' => 'Local Development'],
        'development' => ['tone' => 'development', 'label' => 'Development'],
        'testing' => ['tone' => 'testing', 'label' => 'Testing Environment'],
        'stage', 'staging' => ['tone' => 'staging', 'label' => 'Stage Testing'],
        default => null,
    };
@endphp
<header {{ $attributes->class(['app-header', 'app-header-environment' => $environmentStatus !== null, $environmentStatus ? 'app-environment-'.$environmentStatus['tone'] : '']) }}>
    @if($environmentStatus)
        <span class="app-environment-status">{{ $environmentStatus['label'] }}</span>
    @endif
    {{ $slot }}
</header>
