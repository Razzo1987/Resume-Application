<x-filament-widgets::widget>
    @foreach ($alerts as $alert => $level)
        <x-filament::section :icon="$level === 'danger' ? 'heroicon-o-shield-exclamation' : 'heroicon-o-exclamation-triangle'" :icon-color="$level">
            <x-slot name="heading">{{ __('admin.system.alerts.'.$alert) }}</x-slot>
            <p>
                {{ __('admin.system.alerts.'.$alert.'_help') }}
                <x-filament::link :href="\App\Filament\Pages\ManageSystem::getUrl()">{{ __('admin.pages.system.title') }}</x-filament::link>
            </p>
        </x-filament::section>
    @endforeach
</x-filament-widgets::widget>
