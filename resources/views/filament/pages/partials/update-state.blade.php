{{-- State of an update source (App\Support\Update\UpdateSource): badge, then the problem in words. --}}
<x-filament::badge :color="$source->color()" :icon="$source->icon()">
    {{ __('admin.updates.state.'.$source->state, ['version' => $source->latest?->version ?? '']) }}
</x-filament::badge>
@if ($source->message && $source->state !== 'available')
    <span class="resume-updates-message">{{ $source->message }}</span>
@endif
