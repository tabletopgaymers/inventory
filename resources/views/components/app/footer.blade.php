{{--
Use for application identity, environment, and copyable release information. Use environment and metadata overrides for previews. Stage displays a Preparation ID only when valid release metadata supplies one.

## Slots

No caller slots.

## Example

[Application shell](resources/views/components/app/shell.blade.php) and [Application frame](/style-guide/application-frame).
--}}@props(['environment' => null, 'metadata' => null])
@php
    $environment = $environment ?? config('app.env');
    $metadata = $metadata ?? app(\App\Support\ReleaseMetadata::class)->current();
    $environmentLabel = match ($environment) {
        'local' => 'Local Development',
        'development' => 'Development',
        'stage', 'staging' => 'Stage Testing',
        'testing' => 'Testing',
        default => '',
    };
    $branch = $metadata['branch'] ?? '';
    $revision = $metadata['revision'] ?? '';
    $modified = $metadata['modified'] ?? false;
    $short = preg_match('/\A[0-9a-f]{40,64}\z/i', $revision) ? substr($revision, 0, 7) : $revision;
    $versionLabel = implode(' · ', array_filter([$branch, $short])) ?: 'Version unavailable';
    $preparationId = $metadata['preparation_id'] ?? null;
    $preparationId = in_array($environment, ['stage', 'staging'], true) && is_string($preparationId)
        && preg_match('/\A[a-f0-9]{32}\z/', $preparationId) ? $preparationId : null;
    $copyLines = [
        'Git Repo: tabletopgaymers/inventory',
        'Branch: '.($branch ?: 'Unavailable').($environment === 'local' && $modified ? ' (modified)' : ''),
        'Commit: '.($revision ?: 'Unavailable'),
        'Short: '.($short ?: 'Unavailable'),
    ];
    if ($preparationId !== null) $copyLines[] = 'Preparation ID: '.$preparationId;
    $copyText = implode("\n", $copyLines);
@endphp
<footer data-release-footer {{ $attributes->class(['app-footer', 'app-footer-with-preparation' => $preparationId !== null]) }}>
    <span>TG Inventory
        @if($environmentLabel !== '')
            · <strong class="app-footer-environment app-environment-{{ $environment }}">{{ $environmentLabel }}</strong>
        @endif
    </span>
    @if($preparationId !== null)
        <x-app.action control="copy" class="app-footer-preparation" data-release-copy="{{ $preparationId }}" aria-label="Copy Preparation ID">Preparation ID · <span class="app-preparation-id">{{ $preparationId }}</span></x-app.action>
    @endif
    <x-app.action control="copy" class="app-footer-version" data-release-copy="{{ $copyText }}" aria-label="Copy Git version information">{{ $versionLabel }}@if($modified) · <strong class="app-release-modified">modified</strong>@endif</x-app.action>
    <span class="sr-only" data-release-copy-status role="status"></span>
</footer>
