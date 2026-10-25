<x-filament-panels::page>
    @if ($configured !== $active)
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning">
            <x-slot name="heading">{{ __('admin.themes.fallback', ['theme' => $configured]) }}</x-slot>
            <p>{{ __('admin.themes.fallback_help') }}</p>
        </x-filament::section>
    @endif

    <div class="resume-themes">
        @foreach ($themes as $item)
            @php($theme = $item['theme'])
            <x-filament::section>
                <x-slot name="heading">
                    {{ $theme->title }}
                    <span class="resume-themes-version">{{ $theme->version }}</span>
                </x-slot>
                <x-slot name="afterHeader">
                    @if ($theme->name === $active)
                        <x-filament::badge color="success" icon="heroicon-o-check">{{ __('admin.themes.active') }}</x-filament::badge>
                    @endif
                </x-slot>

                @if ($theme->description)
                    <p>{{ $theme->description }}</p>
                @endif

                <dl class="resume-system-list">
                    <dt>{{ __('admin.themes.name') }}</dt>
                    <dd><code>{{ $theme->name }}</code></dd>
                    @unless ($item['base'])
                        <dt>{{ __('admin.themes.extends') }}</dt>
                        <dd>{{ $theme->extends ?? 'base' }}</dd>
                    @endunless
                    @if ($theme->requires)
                        <dt>{{ __('admin.themes.requires') }}</dt>
                        <dd>{{ $theme->requires }} @unless ($item['compatible']) — <strong>{{ __('admin.themes.incompatible') }}</strong> @endunless</dd>
                    @endif
                    @unless ($item['assets'])
                        <dt>{{ __('admin.themes.assets') }}</dt>
                        <dd><strong>{{ __('admin.themes.assets_missing') }}</strong></dd>
                    @endunless
                </dl>

                @if ($theme->name !== $active || ! $item['base'])
                    <div class="resume-themes-actions">
                        @if ($theme->name !== $active && $item['assets'])
                            {{ ($this->activateAction)(['theme' => $theme->name]) }}
                        @endif
                        @if (! $item['base'] && $theme->name !== $active && $theme->name !== $configured)
                            {{ ($this->removeAction)(['theme' => $theme->name]) }}
                        @endif
                    </div>
                @endif
            </x-filament::section>
        @endforeach
    </div>


    <style>
        .resume-themes { display: grid; gap: 1.5rem; }
        .resume-themes-version { margin-left: .5rem; font-weight: 400; font-size: .875rem; opacity: .7; }
        .resume-system-list { display: grid; grid-template-columns: minmax(9rem, max-content) 1fr; gap: .5rem 1.5rem; margin: 1rem 0 0; }
        .resume-system-list dt { font-weight: 600; }
        .resume-system-list dd { margin: 0; }
        .resume-themes-actions { display: flex; gap: .75rem; margin-top: 1rem; }
    </style>
</x-filament-panels::page>
