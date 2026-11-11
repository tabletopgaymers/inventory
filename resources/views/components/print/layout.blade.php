@props(['title', 'documentTitle' => null, 'bodyClass' => '', 'styles' => []])
<!doctype html>
<html lang="en"><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $documentTitle ?? $title }}</title>
    @vite(array_merge(['resources/css/print.css', 'resources/js/print.js'], $styles))
</head><body class="print-page {{ $bodyClass }}"><main>
    <div class="print-title"><h1>{{ $title }}</h1><x-app.action type="button" size="small" class="print-screen-only" data-print-document>Print</x-app.action></div>
    {{ $slot }}
</main></body></html>
