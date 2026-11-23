@props(['lists', 'loadAction', 'selectId', 'loadedList' => null, 'resetUrl' => null])

<x-app.collapsible-panel title="Lists" {{ $attributes->class(['app-saved-list-selector']) }}>
    <form method="post" action="{{ $loadAction }}">
        @csrf
        <div class="app-field">
            <select id="{{ $selectId }}" name="list_id" aria-label="List" required>
                <option value="">Select a list</option>
                @if($loadedList && !$lists->contains('id', $loadedList['id']))
                    <option value="{{ $loadedList['id'] }}" selected disabled>{{ $loadedList['name'] }}</option>
                @endif
                @foreach($lists as $list)<option value="{{ $list->id }}" @selected(old('list_id', $loadedList['id'] ?? null) == $list->id)>{{ $list->name }}</option>@endforeach
            </select>
        </div>
        <div class="actions">
            @if($resetUrl !== null)<x-app.action :href="$resetUrl">Reset</x-app.action>@endif
            <x-app.action type="submit">Load</x-app.action>
        </div>
    </form>
    {{ $slot }}
</x-app.collapsible-panel>
