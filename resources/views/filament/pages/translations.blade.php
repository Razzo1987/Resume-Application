<x-filament-panels::page>
    <x-filament::tabs :label="__('admin.translations.language')">
        @foreach ($locales as $item)
            <x-filament::tabs.item
                :active="$item['code'] === $current"
                :badge="$item['count'] ?: null"
                badge-color="warning"
                wire:click="selectLocale('{{ $item['code'] }}')"
            >
                {{ $item['name'] }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    @if ($groups === [])
        <x-filament::section icon="heroicon-o-check-circle" icon-color="success">
            <x-slot name="heading">{{ __('admin.translations.complete') }}</x-slot>
            <p>{{ __('admin.translations.complete_help') }}</p>
        </x-filament::section>
    @else
        <p class="resume-translations-intro">{{ __('admin.translations.intro') }}</p>

        @foreach ($groups as $type => $items)
            <x-filament::section :heading="$type" :description="trans_choice('admin.translations.items', count($items))" collapsible>
                <ul class="resume-translations">
                    @foreach ($items as $entry)
                        <li>
                            <div>
                                <strong>{{ $entry['title'] }}</strong>
                                <div class="resume-translations-fields">
                                    @foreach ($entry['fields'] as $field)
                                        <x-filament::badge color="warning" size="sm">{{ $field }}</x-filament::badge>
                                    @endforeach
                                </div>
                            </div>
                            @if ($entry['url'])
                                <x-filament::link :href="$entry['url']" icon="heroicon-m-pencil-square">{{ __('admin.translations.edit') }}</x-filament::link>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-filament::section>
        @endforeach
    @endif

    <style>
        .resume-translations-intro { color: rgb(var(--gray-500, 107 114 128)); }
        .resume-translations { display: grid; gap: .75rem; }
        .resume-translations li { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding-bottom: .75rem; border-bottom: 1px solid rgba(127, 127, 127, .15); }
        .resume-translations li:last-child { padding-bottom: 0; border-bottom: 0; }
        .resume-translations-fields { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: .35rem; }
    </style>
</x-filament-panels::page>
