@props(['title', 'description' => null])
@isset($breadcrumb)<nav class="app-breadcrumb" aria-label="Breadcrumb">{{ $breadcrumb }}</nav>@endisset
<div {{ $attributes->class(['app-page-heading']) }}>
    <div><div class="app-title-row"><h1>{{ $title }}</h1>{{ $status ?? '' }}</div>@if($description)<p>{{ $description }}</p>@endif</div>
    @isset($actions)<div class="app-actions">{{ $actions }}</div>@endisset
</div>
