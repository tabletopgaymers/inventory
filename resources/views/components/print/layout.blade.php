{{--
Use for a standalone printable document. Supply the visible title and optionally a separate browser-tab title. The layout includes shared print styling and a screen-only Print button. Pass additional Vite entry paths only when a document needs them.

## Slots

The default slot contains the material that follows the title.

## Examples

[Relocation worksheet](resources/views/relocation/print.blade.php).
--}}@props(['title', 'documentTitle' => null, 'bodyClass' => '', 'styles' => []])
<!doctype html>
<html lang="en"><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <x-app.document-title :title="$documentTitle ?? $title" />
    <x-app.favicon />
    @vite(array_merge(['resources/css/print.css', 'resources/js/print.js'], $styles))
</head><body class="print-page {{ $bodyClass }}"><main>
    <div class="print-title"><h1>{{ $title }}</h1><x-app.action type="button" size="small" class="print-screen-only" data-print-document>Print</x-app.action></div>
    {{ $slot }}
</main></body></html>
