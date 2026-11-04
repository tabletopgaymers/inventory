@props(['title', 'documentTitle' => null])
<!doctype html>
<html lang="en"><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $documentTitle ?? $title }}</title>
    @vite(['resources/css/print.css', 'resources/js/print.js'])
</head><body class="print-page"><main>
    <div class="print-title"><h1>{{ $title }}</h1><x-app.action type="button" size="small" data-print-document>Print</x-app.action></div>
    {{ $slot }}
</main></body></html>
