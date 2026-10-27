<x-filament-widgets::widget>
    @foreach ($alerts as $alert => $level)
        <x-filament::section :icon="match ($level) { 'danger' => 'heroicon-o-shield-exclamation', 'info' => 'heroicon-o-arrow-down-circle', 'success' => 'heroicon-o-check-circle', default => 'heroicon-o-exclamation-triangle' }" :icon-color="$level">
            <x-slot name="heading">{{ __('admin.system.alerts.'.$alert, $params[$alert] ?? []) }}</x-slot>
            <p>
                {{ __('admin.system.alerts.'.$alert.'_help') }}
                @if ($alert === 'auto_update_failed' && ($event['message'] ?? null))
                    <br><span class="resume-update-problem">{{ $event['message'] }}</span>
                @endif
                @if ($alert === 'auto_update_failed' && ($event['url'] ?? null))
                    <br><x-filament::link :href="$event['url']">{{ __('admin.updates.auto.resume_link') }}</x-filament::link> ·
                @endif
                <x-filament::link :href="\App\Filament\Pages\ManageSystem::getUrl()">{{ __('admin.pages.system.title') }}</x-filament::link>
            </p>
        </x-filament::section>
    @endforeach
</x-filament-widgets::widget>
