<x-filament-widgets::widget>
    @foreach ($alerts as $alert => $level)
        <x-filament::section :icon="match ($level) { 'danger' => 'heroicon-o-shield-exclamation', 'info' => 'heroicon-o-arrow-down-circle', 'warning' => str_starts_with($alert, 'github_key') || $alert === 'update_sources_key' ? 'heroicon-o-key' : 'heroicon-o-exclamation-triangle', 'success' => 'heroicon-o-check-circle', default => 'heroicon-o-exclamation-triangle' }" :icon-color="$level">
            <x-slot name="heading">{{ __('admin.system.alerts.'.$alert, $params[$alert] ?? []) }}</x-slot>
            <p>
                {{ __('admin.system.alerts.'.$alert.'_help') }}
                @if ($alert === 'auto_update_failed' && ($event['message'] ?? null))
                    <br><span class="resume-update-problem">{{ $event['message'] }}</span>
                @endif
                @if ($alert === 'auto_update_failed' && ($event['url'] ?? null))
                    <br><x-filament::link :href="$event['url']">{{ __('admin.updates.auto.resume_link') }}</x-filament::link> ·
                @endif
                @if ($alert === 'mfa_off')
                    <x-filament::link :href="filament()->getProfileUrl()">{{ __('admin.system.alerts.mfa_off_link') }}</x-filament::link>
                @elseif (in_array($alert, ['update_available', 'update_downloaded', 'theme_updates', 'update_sources_key', 'github_key_expiring', 'github_key_expired'], true))
                    <x-filament::link :href="\App\Filament\Pages\ManageUpdates::getUrl()">{{ __('admin.pages.updates.title') }}</x-filament::link>
                @else
                    <x-filament::link :href="\App\Filament\Pages\ManageSystem::getUrl()">{{ __('admin.pages.system.title') }}</x-filament::link>
                @endif
            </p>
        </x-filament::section>
    @endforeach
</x-filament-widgets::widget>
