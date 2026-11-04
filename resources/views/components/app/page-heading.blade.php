@props(['title', 'description' => null])
@isset($breadcrumb)<nav class="app-breadcrumb" aria-label="Breadcrumb">{{ $breadcrumb }}</nav>@endisset
<x-app.heading-row variant="page" {{ $attributes->class(['app-page-heading']) }}>
    <div><div class="app-title-row"><h1>{{ $title }}</h1>{{ $status ?? '' }}</div>@if($description)<p>{{ $description }}</p>@endif</div>
    @isset($actions)<div class="app-actions">{{ $actions }}</div>@endisset
</x-app.heading-row>
