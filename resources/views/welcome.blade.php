<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TG Inventory — Local development baseline</title>
    <link rel="stylesheet" href="/baseline.css">
</head>
<body>
    <main>
        <p class="eyebrow">{{ $baselineLabel }}</p>
        <h1>Tabletop Gaymers Inventory</h1>
        <p class="status {{ $ready ? 'success' : 'warning' }}" role="status">{{ $message }}</p>
        <p>This development page verifies the application foundation. Inventory workflows are not available yet.</p>
        <dl>
            <dt>PHP</dt><dd>{{ $phpVersion }}</dd>
            <dt>Laravel</dt><dd>{{ $laravelVersion }}</dd>
            @if ($ready)
                <dt>MariaDB</dt><dd>{{ $databaseVersion }}</dd>
            @endif
        </dl>
    </main>
</body>
</html>
