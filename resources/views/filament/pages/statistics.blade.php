<x-filament-panels::page>
    @unless ($enabled)
        <x-filament::section icon="heroicon-o-chart-bar" icon-color="info">
            <x-slot name="heading">{{ __('admin.stats.disabled') }}</x-slot>
            <p>{{ __('admin.stats.disabled_help') }}</p>
            <div class="resume-stats-actions">{{ $this->settingsAction }}</div>
        </x-filament::section>
    @endunless

    <div class="resume-stats-filters">
        <label>
            <span>{{ __('admin.stats.period') }}</span>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="period">
                    @foreach (['7', '30', '90', '365', 'all'] as $value)
                        <option value="{{ $value }}">{{ __('admin.stats.periods.'.$value) }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>
        <label class="resume-stats-page">
            <span>{{ __('admin.stats.page') }}</span>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="pageFilter">
                    <option value="">{{ __('admin.stats.all_pages') }}</option>
                    @foreach ($pageOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>
    </div>

    <div class="resume-stats-kpis">
        @if ($pageFilter !== \App\Support\Stats\StatsQuery::DOWNLOAD)
            <div class="resume-stats-kpi"><span>{{ __('admin.stats.views') }}</span><strong>{{ \Illuminate\Support\Number::format($totals['views']) }}</strong></div>
        @endif
        @if ($pageFilter === '' || $pageFilter === \App\Support\Stats\StatsQuery::DOWNLOAD)
            <div class="resume-stats-kpi"><span>{{ __('admin.stats.downloads') }}</span><strong>{{ \Illuminate\Support\Number::format($totals['downloads']) }}</strong></div>
        @endif
        <div class="resume-stats-kpi"><span>{{ __('admin.stats.countries') }}</span><strong>{{ \Illuminate\Support\Number::format($totals['countries']) }}</strong></div>
        @if ($pageFilter === '')
            <div class="resume-stats-kpi"><span>{{ __('admin.stats.pages_viewed') }}</span><strong>{{ \Illuminate\Support\Number::format($totals['pages']) }}</strong></div>
        @endif
    </div>

    @livewire(\App\Filament\Widgets\StatsTimelineChart::class, ['period' => $period, 'pageFilter' => $pageFilter], key('stats-timeline'))

    <div class="resume-stats-grid">
        @if ($pageFilter === '')
            @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.pages'), 'items' => $lists['pages'], 'filter' => true])
        @endif
        @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.referrer'), 'items' => $lists['referrer'], 'empty' => __('admin.stats.lists.referrer_empty')])
        @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.utm_campaign'), 'items' => $lists['utm_campaign']])
        @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.utm_source'), 'items' => $lists['utm_source']])
        @if ($lists['click_source'])
            @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.click_source'), 'items' => $lists['click_source']])
        @endif
        @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.locale'), 'items' => $lists['locale']])
    </div>

    <x-filament::section>
        <x-slot name="heading">{{ __('admin.stats.map') }}</x-slot>
        @if ($geoip->isAvailable() || $totals['countries'] > 0)
            <div class="resume-stats-map">{{ $map }}</div>
            @if ($geoip->isAvailable())
                <p class="resume-stats-note">{{ __('admin.stats.geoip.credit') }}</p>
            @endif
        @else
            <p>{{ __('admin.stats.geoip.missing') }}</p>
        @endif
        <div class="resume-stats-grid resume-stats-grid-inner">
            @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.country'), 'items' => $lists['country']])
            @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.city'), 'items' => $lists['city']])
            @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.asn'), 'items' => $lists['asn']])
        </div>
    </x-filament::section>

    <div class="resume-stats-grid">
        @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.device'), 'items' => $lists['device']])
        @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.os'), 'items' => $lists['os']])
        @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.browser'), 'items' => $lists['browser']])
    </div>

    {{ $this->table }}

    <x-filament::section icon="heroicon-o-globe-europe-africa" collapsible :collapsed="$geoip->isAvailable() && ! collect($geoipStatus)->contains('update', true)">
        <x-slot name="heading">{{ __('admin.stats.geoip.title') }}</x-slot>
        <x-slot name="afterHeader">{{ $this->geoipAccountAction }}</x-slot>
        <dl class="resume-system-list">
            <dt>{{ __('admin.stats.geoip.account') }}</dt>
            <dd>{{ __('admin.stats.geoip.source.'.($geoip->credentialsSource() ?? 'none')) }}</dd>
            @foreach ($geoipStatus as $edition => $status)
                <dt>{{ $edition }}</dt>
                <dd>
                    @if ($status['installed'])
                        {{ __('admin.stats.geoip.installed', ['date' => $status['installed_at']?->translatedFormat('j F Y') ?? '-']) }}
                    @else
                        {{ __('admin.stats.geoip.not_installed') }}
                    @endif
                    @if ($status['installed'] && $status['update'])
                        · <span class="resume-update-problem">{{ __('admin.stats.geoip.newer', ['date' => $status['remote_at']?->translatedFormat('j F Y') ?? '-']) }}</span>
                    @endif
                    @if ($status['checked_at'])
                        <br><small>{{ __('admin.stats.geoip.checked', ['when' => $status['checked_at']->diffForHumans()]) }}</small>
                    @endif
                </dd>
            @endforeach
        </dl>
        @if ($geoip->credentials())
            <div class="resume-update-box" x-data="resumeGeoipDownload()">
                <p>{{ __('admin.stats.geoip.update_help') }}</p>
                <x-filament::button icon="heroicon-o-cloud-arrow-down" color="gray" x-on:click="run()" x-bind:disabled="busy">
                    {{ __('admin.stats.geoip.update_now') }}
                </x-filament::button>
                <div class="resume-update-progress" x-show="total > 0" x-cloak>
                    <span x-text="edition"></span>
                    <progress x-bind:max="total" x-bind:value="received"></progress>
                    <span x-text="Math.round(received / total * 100) + '%'"></span>
                </div>
                <p class="resume-update-ok" x-show="done" x-cloak>{{ __('admin.stats.geoip.updated') }}</p>
                <p class="resume-update-problem" x-show="error" x-text="error" x-cloak></p>
            </div>
        @endif
    </x-filament::section>

    <script>
        // Chunked download of the GeoLite databases (each chunk is a short request)
        window.resumeGeoipDownload = () => ({
            busy: false, edition: '', received: 0, total: 0, error: null, done: false,
            async run() {
                this.busy = true; this.error = null; this.done = false;
                try {
                    const pending = await this.$wire.geoipPending();
                    if (pending.error) { this.error = pending.error; return; }
                    for (const edition of pending.editions) {
                        this.edition = edition; this.received = 0; this.total = 0;
                        for (;;) {
                            const result = await this.$wire.geoipChunk(edition);
                            if (result.error) { this.error = result.error; return; }
                            this.received = result.received; this.total = result.total;
                            if (result.done) { break; }
                        }
                    }
                    this.done = true;
                    this.$wire.$refresh();
                } finally {
                    this.busy = false;
                }
            },
        });
    </script>

    <style>
        .resume-stats-filters { display: flex; gap: 1rem; flex-wrap: wrap; align-items: end; }
        .resume-stats-filters label { display: grid; gap: .35rem; font-size: .875rem; font-weight: 500; }
        .resume-stats-page { flex: 1; min-width: 16rem; max-width: 36rem; }
        .resume-stats-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); gap: 1rem; }
        .resume-stats-kpi { display: grid; gap: .25rem; padding: 1rem 1.25rem; border-radius: .75rem; background: white; box-shadow: 0 1px 2px rgb(0 0 0 / .05); outline: 1px solid rgb(0 0 0 / .05); }
        .dark .resume-stats-kpi { background: rgb(255 255 255 / .05); outline-color: rgb(255 255 255 / .1); }
        .resume-stats-kpi span { font-size: .875rem; color: rgb(107 114 128); }
        .resume-stats-kpi strong { font-size: 1.75rem; font-weight: 600; font-variant-numeric: tabular-nums; }
        .resume-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(18rem, 1fr)); gap: 1rem; align-items: start; }
        .resume-stats-grid-inner { margin-top: 1.25rem; }
        .resume-stats-list { margin: 0; padding: 0; list-style: none; display: grid; gap: .35rem; }
        .resume-stats-list li { position: relative; display: flex; justify-content: space-between; gap: .75rem; padding: .3rem .5rem; font-size: .875rem; border-radius: .375rem; overflow: hidden; }
        .resume-stats-bar { position: absolute; inset: 0 auto 0 0; background: var(--info-500, rgb(14 165 233)); opacity: .14; border-radius: .375rem; }
        .resume-stats-list .resume-stats-label { position: relative; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .resume-stats-list .resume-stats-value { position: relative; font-variant-numeric: tabular-nums; font-weight: 600; }
        .resume-stats-list button { text-align: left; text-decoration: underline; text-decoration-color: rgb(0 0 0 / .2); }
        .resume-stats-empty { font-size: .875rem; color: rgb(107 114 128); }
        .resume-stats-map svg { width: 100%; height: auto; display: block; }
        .resume-map-land { fill: rgb(0 0 0 / .08); stroke: white; stroke-width: .5; }
        .resume-map-visited { fill: var(--info-600, rgb(2 132 199)); stroke: white; stroke-width: .5; }
        .dark .resume-map-land { fill: rgb(255 255 255 / .1); stroke: rgb(17 24 39); }
        .dark .resume-map-visited { stroke: rgb(17 24 39); }
        .resume-map-city { fill: var(--warning-500, rgb(245 158 11)); fill-opacity: .85; stroke: white; stroke-width: .75; }
        .resume-stats-note { margin-top: .5rem; font-size: .75rem; color: rgb(107 114 128); }
        .resume-stats-actions { margin-top: .75rem; }
        .resume-system-list { display: grid; grid-template-columns: minmax(9rem, max-content) 1fr; gap: .5rem 1.5rem; margin: 0; }
        .resume-system-list dt { font-weight: 600; }
        .resume-system-list dd { margin: 0; }
        .resume-update-box { margin-top: 1.25rem; padding-top: 1.25rem; border-top: 1px solid rgb(0 0 0 / .08); }
        .resume-update-box p { margin: 0 0 .75rem; }
        .resume-update-progress { display: flex; gap: .75rem; align-items: center; margin-top: .75rem; }
        .resume-update-progress progress { flex: 1; max-width: 24rem; height: .75rem; }
        .resume-update-problem { color: rgb(220 38 38); }
        .resume-update-ok { color: rgb(22 163 74); font-weight: 600; margin-top: .75rem; }
    </style>
</x-filament-panels::page>
