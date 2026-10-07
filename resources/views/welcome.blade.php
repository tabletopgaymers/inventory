<x-app.shell title="Development baseline" :guest="true">
<x-app.page-heading title="Tabletop Gaymers Inventory" />
<x-app.panel>
        <p class="app-eyebrow">{{ $baselineLabel }}</p>

        <p class="{{ $ready ? 'notice' : 'warning' }}" role="status">{{ $message }}</p>
        <p>This development page verifies the application foundation. Inventory workflows are not available yet.</p>
        <dl class="item-metadata">
            <dt>Revision</dt><dd>{{ config('baseline.revision') ?? 'Unrecorded working copy' }}</dd>
            <dt>PHP</dt><dd>{{ $phpVersion }}</dd>
            <dt>Laravel</dt><dd>{{ $laravelVersion }}</dd>
            @if ($ready)
                <dt>MariaDB</dt><dd>{{ $databaseVersion }}</dd>
            @endif
        </dl>
</x-app.panel>
</x-app.shell>
