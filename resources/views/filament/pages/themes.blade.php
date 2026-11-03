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

                <div @class(['resume-themes-body', 'has-preview' => $item['preview']])>
                @if ($item['preview'])
                    <img class="resume-themes-preview" src="{{ $item['preview'] }}" alt="{{ __('admin.themes.preview', ['theme' => $theme->title]) }}" loading="lazy" width="640" height="400">
                @endif
                <div class="resume-themes-info">
                @if ($theme->description)
                    <p>{{ $theme->description }}</p>
                @endif

                @if ($item['source'])
                    <div class="resume-themes-update">
                        @include('filament.pages.partials.update-state', ['source' => $item['source']])
                        @if ($item['source']->hasUpdate())
                            @if ($item['source']->latest->url)
                                <a href="{{ $item['source']->latest->url }}" target="_blank" rel="noopener" class="resume-link">{{ __('admin.themes.updates.notes') }}</a>
                            @endif
                            {{ ($this->updateAction)(['theme' => $theme->name, 'version' => $item['source']->latest->version]) }}
                        @elseif ($item['source']->needsKey())
                            <a href="{{ \App\Filament\Pages\ManageUpdates::getUrl() }}" class="resume-link">{{ __('admin.updates.keys.manage') }}</a>
                        @endif
                    </div>
                @endif

                @if ($theme->variants)
                    <div class="resume-themes-variants">
                        <span class="resume-themes-variants-label">{{ __('admin.themes.variants') }}</span>
                        @foreach ($theme->variants as $variant)
                            @if ($theme->name === $active && $variant->name === $activeVariant)
                                <x-filament::badge color="success" icon="heroicon-o-check">{{ $variant->title }}</x-filament::badge>
                            @elseif ($item['assets'])
                                <x-filament::button size="xs" color="gray" outlined
                                    wire:click="mountAction('activate', {{ \Illuminate\Support\Js::from(['theme' => $theme->name, 'variant' => $variant->name]) }})"
                                    :tooltip="$variant->description">{{ $variant->title }}</x-filament::button>
                            @else
                                <span>{{ $variant->title }}</span>
                            @endif
                        @endforeach
                    </div>
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
                    @if ($item['source'])
                        <dt>{{ __('admin.themes.updates.source') }}</dt>
                        <dd>@include('filament.pages.partials.update-access', ['source' => $item['source']])</dd>
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
                </div>
                </div>
            </x-filament::section>
        @endforeach
    </div>



    <style>
        .resume-themes { display: grid; gap: 1.5rem; }
        .resume-themes-body.has-preview { display: grid; grid-template-columns: minmax(12rem, 20rem) 1fr; gap: 1.5rem; align-items: start; }
        @media (max-width: 48rem) { .resume-themes-body.has-preview { grid-template-columns: 1fr; } }
        .resume-themes-preview { width: 100%; height: auto; aspect-ratio: 16 / 10; object-fit: cover; object-position: top; border-radius: .5rem; box-shadow: 0 0 0 1px rgb(3 7 18 / .1); }
        .dark .resume-themes-preview { box-shadow: 0 0 0 1px rgb(255 255 255 / .15); }
        .resume-themes-info > :first-child { margin-top: 0; }
        .resume-themes-version { margin-left: .5rem; font-weight: 400; font-size: .875rem; opacity: .7; }
        .resume-system-list { display: grid; grid-template-columns: minmax(9rem, max-content) 1fr; gap: .5rem 1.5rem; margin: 1rem 0 0; }
        .resume-system-list dt { font-weight: 600; }
        .resume-system-list dd { margin: 0; }
        .resume-themes-actions { display: flex; gap: .75rem; margin-top: 1rem; }
        .resume-themes-update, .resume-themes-variants { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .75rem; margin-top: 1rem; }
        .resume-themes-variants-label { font-weight: 600; margin-right: .25rem; }
        .resume-updates-access { margin-left: .5rem; font-size: .8125rem; opacity: .75; }
        .resume-updates-message { font-size: .8125rem; opacity: .75; }
        .resume-link { text-decoration: underline; }
    </style>
</x-filament-panels::page>
