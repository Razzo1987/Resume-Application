<x-filament-panels::page>
    @unless ($enabled)
        <x-filament::section icon="heroicon-o-chart-bar" icon-color="info">
            <x-slot name="heading">{{ __('admin.stats.disabled') }}</x-slot>
            <p>{{ __('admin.stats.disabled_help') }}</p>
            <div class="resume-stats-actions">{{ $this->settingsAction }}</div>
        </x-filament::section>
    @endunless

    @if ($proxyNote)
        <x-filament::section icon="heroicon-o-information-circle" icon-color="gray" compact class="resume-stats-proxy-note">
            <x-slot name="heading">{{ __('admin.stats.proxy.title') }}</x-slot>
            <p>{{ $proxyNote }}</p>
        </x-filament::section>
    @endif

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

    <details class="resume-stats-more" @if ($activeFilters) open @endif>
        <summary>{{ __('admin.stats.filters.more') }}@if ($activeFilters) ({{ count($activeFilters) }})@endif</summary>
        <p class="resume-stats-note">{{ __('admin.stats.filters.hint') }}</p>
        <div class="resume-stats-filter-grid">
            @foreach ($filterOptions as $name => $options)
                <label>
                    <span>{{ __('admin.stats.filters.names.'.$name) }}</span>
                    <x-filament::input.wrapper :disabled="$options === []">
                        <x-filament::input.select wire:model.live="filters.{{ $name }}" :disabled="$options === []">
                            <option value="">{{ __('admin.stats.filters.any') }}</option>
                            @foreach ($options as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
            @endforeach
        </div>
    </details>

    @if ($activeFilters || $pageLabel)
        <div class="resume-stats-active" role="group" aria-label="{{ __('admin.stats.filters.active') }}">
            @if ($pageLabel)
                <x-filament::badge color="info" tag="button" type="button" icon="heroicon-m-x-mark" icon-position="after" wire:click="$set('pageFilter', '')" :tooltip="__('admin.stats.filters.remove')" aria-label="{{ __('admin.stats.filters.remove_named', ['filter' => __('admin.stats.page').': '.$pageLabel]) }}">
                    {{ __('admin.stats.page') }}: {{ $pageLabel }}
                </x-filament::badge>
            @endif
            @foreach ($activeFilters as $filter)
                <x-filament::badge color="info" tag="button" type="button" icon="heroicon-m-x-mark" icon-position="after" wire:click="removeFilter(@js($filter['name']))" :tooltip="__('admin.stats.filters.remove')" aria-label="{{ __('admin.stats.filters.remove_named', ['filter' => $filter['label'].': '.$filter['value']]) }}">
                    {{ $filter['label'] }}: {{ $filter['value'] }}
                </x-filament::badge>
            @endforeach
            <x-filament::link tag="button" size="sm" wire:click="clearFilters">{{ __('admin.stats.filters.clear') }}</x-filament::link>
        </div>
        @if ($detailed)
            <p class="resume-stats-note">{{ __('admin.stats.filters.detailed', ['months' => $retention]) }}</p>
        @endif
    @endif

    @php
        $format = fn (?int $seconds): string => $seconds === null ? '-' : ($seconds < 60
            ? __('admin.stats.seconds', ['count' => $seconds])
            : __('admin.stats.minutes', ['minutes' => intdiv($seconds, 60), 'seconds' => str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT)]));
        $number = fn (int|float|null $value, int $precision = 0): string => $value === null ? '-' : \Illuminate\Support\Number::format($value, $precision);
        $isDownload = $pageFilter === \App\Support\Stats\StatsQuery::DOWNLOAD;
    @endphp

    <div class="resume-stats-kpis">
        @if ($pageFilter === '')
            <div class="resume-stats-kpi" title="{{ __('admin.stats.kpi_help.visitors') }}"><span>{{ __('admin.stats.visitors') }}</span><strong>{{ $number($kpis['visitors']) }}</strong></div>
            <div class="resume-stats-kpi" title="{{ __('admin.stats.kpi_help.sessions') }}"><span>{{ __('admin.stats.sessions') }}</span><strong>{{ $number($kpis['sessions']) }}</strong></div>
            <div class="resume-stats-kpi"><span>{{ __('admin.stats.views') }}</span><strong>{{ $number($totals['views']) }}</strong></div>
            <div class="resume-stats-kpi"><span>{{ __('admin.stats.pages_per_session') }}</span><strong>{{ $number($kpis['pages_per_session'], 1) }}</strong></div>
            <div class="resume-stats-kpi" title="{{ __('admin.stats.kpi_help.duration') }}"><span>{{ __('admin.stats.avg_duration') }}</span><strong>{{ $format($kpis['avg_duration']) }}</strong></div>
            <div class="resume-stats-kpi" title="{{ __('admin.stats.kpi_help.bounce') }}"><span>{{ __('admin.stats.bounce_rate') }}</span><strong>{{ $kpis['bounce_rate'] === null ? '-' : $number($kpis['bounce_rate'], 1).'%' }}</strong></div>
            <div class="resume-stats-kpi"><span>{{ __('admin.stats.downloads') }}</span><strong>{{ $number($totals['downloads']) }}</strong></div>
            <div class="resume-stats-kpi"><span>{{ __('admin.stats.countries') }}</span><strong>{{ $number($totals['countries']) }}</strong></div>
        @else
            <div class="resume-stats-kpi"><span>{{ $isDownload ? __('admin.stats.downloads') : __('admin.stats.views') }}</span><strong>{{ $number($isDownload ? $totals['downloads'] : $totals['views']) }}</strong></div>
            <div class="resume-stats-kpi" title="{{ __('admin.stats.kpi_help.visitors') }}"><span>{{ __('admin.stats.visitors') }}</span><strong>{{ $number($kpis['visitors']) }}</strong></div>
            <div class="resume-stats-kpi" title="{{ __('admin.stats.kpi_help.entries') }}"><span>{{ __('admin.stats.entries') }}</span><strong>{{ $number($kpis['entries']) }}</strong></div>
            <div class="resume-stats-kpi" title="{{ __('admin.stats.kpi_help.exits') }}"><span>{{ __('admin.stats.exits') }}</span><strong>{{ $number($kpis['exits']) }}</strong></div>
            <div class="resume-stats-kpi"><span>{{ __('admin.stats.countries') }}</span><strong>{{ $number($totals['countries']) }}</strong></div>
        @endif
    </div>
    @if ($since === null || $since > \App\Support\Stats\StatsQuery::forPeriod($period)->from->toDateString())
        <p class="resume-stats-note">{{ $since === null ? __('admin.stats.since_none') : __('admin.stats.since', ['date' => \Illuminate\Support\Carbon::parse($since)->format('d/m/Y')]) }}</p>
    @endif

    @livewire(\App\Filament\Widgets\StatsTimelineChart::class, ['period' => $period, 'pageFilter' => $pageFilter, 'filters' => $filters], key('stats-timeline'))

    @php($heatMax = max([1, ...array_map(fn (array $hours): int => max($hours), $heatmap)]))
    <x-filament::section collapsible>
        <x-slot name="heading">{{ __('admin.stats.heatmap') }}</x-slot>
        <x-slot name="description">{{ __('admin.stats.heatmap_help') }}</x-slot>
        <div class="resume-stats-heatmap-wrap">
            <table class="resume-stats-heatmap">
                <caption class="resume-sr-only">{{ __('admin.stats.heatmap') }}</caption>
                <thead>
                    <tr>
                        <th scope="col"><span class="resume-sr-only">{{ __('admin.stats.day') }}</span></th>
                        @for ($hour = 0; $hour < 24; $hour++)
                            <th scope="col">{{ $hour % 3 === 0 ? str_pad((string) $hour, 2, '0', STR_PAD_LEFT) : '' }}<span class="resume-sr-only">{{ $hour % 3 === 0 ? '' : str_pad((string) $hour, 2, '0', STR_PAD_LEFT) }}</span></th>
                        @endfor
                    </tr>
                </thead>
                <tbody>
                    @foreach ($heatmap as $weekday => $hours)
                        @php($dayName = \Illuminate\Support\Carbon::now()->startOfWeek()->addDays($weekday - 1)->locale(app()->getLocale())->isoFormat('ddd'))
                        <tr>
                            <th scope="row">{{ $dayName }}</th>
                            @foreach ($hours as $hour => $views)
                                <td title="{{ $dayName }} {{ str_pad((string) $hour, 2, '0', STR_PAD_LEFT) }}:00 - {{ $views }}"><span class="resume-stats-heat" style="opacity: {{ $views > 0 ? round(0.12 + 0.88 * $views / $heatMax, 2) : 0 }}"></span><span class="resume-sr-only">{{ $views }}</span></td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <div class="resume-stats-grid">
        @if ($pageFilter === '')
            @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.pages'), 'items' => $lists['pages'], 'filter' => 'page'])
            @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.entry'), 'items' => $lists['entry'], 'filter' => 'page', 'empty' => __('admin.stats.lists.sessions_empty')])
            @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.exit'), 'items' => $lists['exit'], 'filter' => 'page', 'empty' => __('admin.stats.lists.sessions_empty')])
        @endif
        @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.channel'), 'items' => $lists['channel'], 'filter' => 'channel'])
        @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.referrer'), 'items' => $lists['referrer'], 'filter' => 'referrer', 'empty' => __('admin.stats.lists.referrer_empty')])
        @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.utm_campaign'), 'items' => $lists['utm_campaign'], 'filter' => 'utm_campaign'])
        @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.utm_source'), 'items' => $lists['utm_source'], 'filter' => 'utm_source'])
        @if ($lists['utm_medium'])
            @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.utm_medium'), 'items' => $lists['utm_medium'], 'filter' => 'utm_medium'])
        @endif
        @if ($lists['click_source'])
            @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.click_source'), 'items' => $lists['click_source'], 'filter' => 'click_source'])
        @endif
        @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.locale'), 'items' => $lists['locale'], 'filter' => 'locale'])
    </div>

    <x-filament::section>
        <x-slot name="heading">{{ __('admin.stats.map') }}</x-slot>
        @if ($geoip->isAvailable() || $totals['countries'] > 0)
            <nav class="resume-stats-crumbs" aria-label="{{ __('admin.stats.map_path') }}">
                @if ($focus)
                    <button type="button" wire:click="removeFilter('country')">{{ __('admin.stats.world') }}</button>
                    <span aria-hidden="true">&rsaquo;</span>
                    @if ($cityLabel)
                        <button type="button" wire:click="removeFilter('city')">{{ $focusLabel }}</button>
                        <span aria-hidden="true">&rsaquo;</span>
                        <span aria-current="page">{{ $cityLabel }}</span>
                    @else
                        <span aria-current="page">{{ $focusLabel }}</span>
                    @endif
                @else
                    <span aria-current="page">{{ __('admin.stats.world') }}</span>
                    <span class="resume-stats-note">{{ __('admin.stats.map_hint') }}</span>
                @endif
            </nav>
            <div class="resume-stats-map">{{ $map }}</div>
            @if ($geoip->isAvailable())
                <p class="resume-stats-note">{{ __('admin.stats.geoip.credit') }}</p>
            @endif
        @else
            <p>{{ __('admin.stats.geoip.missing') }}</p>
        @endif
        <div class="resume-stats-grid resume-stats-grid-inner">
            @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.country'), 'items' => $lists['country'], 'filter' => 'country'])
            @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.city'), 'items' => $lists['city'], 'filter' => 'city'])
            @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.organization'), 'items' => $lists['organization'], 'filter' => 'asn', 'mark' => 'provider', 'empty' => __('admin.stats.lists.organization_empty'), 'help' => __('admin.stats.lists.organization_help')])
            @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.provider'), 'items' => $lists['provider'], 'filter' => 'asn', 'mark' => 'organization'])
        </div>
    </x-filament::section>

    <div class="resume-stats-grid">
        @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.device'), 'items' => $lists['device'], 'filter' => 'device'])
        @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.os'), 'items' => $lists['os'], 'filter' => 'os'])
        @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.browser'), 'items' => $lists['browser'], 'filter' => 'browser'])
        @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.os_version'), 'items' => $lists['os_version']])
        @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.browser_version'), 'items' => $lists['browser_version']])
        @include('filament.pages.statistics-list', ['title' => __('admin.stats.lists.ip'), 'items' => $lists['ip'], 'filter' => 'ip'])
    </div>

    <div class="resume-stats-grid">
        <x-filament::section compact>
            <x-slot name="heading">{{ __('admin.stats.bots.title') }}</x-slot>
            <x-slot name="description">{{ __('admin.stats.bots.help', ['count' => \Illuminate\Support\Number::format($bots['total'])]) }}</x-slot>
            @if ($bots['bots'])
                <div class="resume-stats-chips">
                    @foreach ($bots['categories'] as $category => $hits)
                        <span class="resume-stats-chip">{{ $icons->for('bot', $category) }} {{ __('admin.stats.bots.categories.'.$category) }}: <strong>{{ \Illuminate\Support\Number::format($hits) }}</strong></span>
                    @endforeach
                </div>
                @php($botMax = max([1, ...array_column($bots['bots'], 'hits')]))
                <ul class="resume-stats-list">
                    @foreach ($bots['bots'] as $bot)
                        <li>
                            <span class="resume-stats-bar" style="width: {{ round($bot['hits'] / $botMax * 100, 1) }}%"></span>
                            <span class="resume-stats-label" title="{{ $bot['name'] }} - {{ __('admin.stats.bots.categories.'.$bot['category']) }}">{{ $bot['icon'] }} {{ $bot['name'] }} <small>{{ __('admin.stats.bots.categories.'.$bot['category']) }}</small></span>
                            <span class="resume-stats-value">{{ \Illuminate\Support\Number::format($bot['hits']) }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="resume-stats-empty">{{ __('admin.stats.lists.empty') }}</p>
            @endif
        </x-filament::section>

        <x-filament::section compact>
            <x-slot name="heading">{{ __('admin.stats.not_found.title') }}</x-slot>
            <x-slot name="description">{{ __('admin.stats.not_found.help') }}</x-slot>
            @if ($notFound['pages'])
                <ul class="resume-stats-list resume-stats-404">
                    @foreach ($notFound['pages'] as $row)
                        <li>
                            <span class="resume-stats-label" title="{{ $row->path }}"><code>{{ $row->path }}</code>
                                <small>{{ __('admin.stats.not_found.detail', ['people' => $row->human_hits, 'when' => \Illuminate\Support\Carbon::parse($row->last_seen_at, 'UTC')->diffForHumans()]) }}@if ($row->last_referrer_host) · {{ __('admin.stats.not_found.from', ['site' => $row->last_referrer_host]) }}@endif</small>
                            </span>
                            <span class="resume-stats-value">{{ \Illuminate\Support\Number::format($row->hits) }}</span>
                            <span class="resume-stats-row-action">{{ ($this->createRedirectAction)(['path' => $row->path]) }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="resume-stats-empty">{{ __('admin.stats.not_found.empty') }}</p>
            @endif
            @if ($notFound['probes'] > 0)
                <details class="resume-stats-more resume-stats-probes">
                    <summary>{{ trans_choice('admin.stats.not_found.probes', $notFound['probes'], ['count' => \Illuminate\Support\Number::format($notFound['probes'])]) }}</summary>
                    <p class="resume-stats-note">{{ __('admin.stats.not_found.probes_help') }}</p>
                    <ul class="resume-stats-list">
                        @foreach ($notFound['probe_paths'] as $row)
                            <li><span class="resume-stats-label" title="{{ $row->path }}"><code>{{ $row->path }}</code></span><span class="resume-stats-value">{{ \Illuminate\Support\Number::format($row->hits) }}</span></li>
                        @endforeach
                    </ul>
                </details>
            @endif
            @if ($notFound['pages'] || $notFound['probes'] > 0)
                <div class="resume-stats-actions">{{ $this->clearNotFoundAction }}</div>
            @endif
        </x-filament::section>
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
                    <progress x-bind:max="total" x-bind:value="received" aria-label="{{ __('admin.stats.geoip.update_now') }}"></progress>
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
        .resume-stats-more summary { cursor: pointer; font-size: .875rem; font-weight: 600; }
        .resume-stats-filter-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr)); gap: .75rem 1rem; margin-top: .75rem; }
        .resume-stats-filter-grid label { display: grid; gap: .35rem; font-size: .875rem; font-weight: 500; }
        .resume-stats-active { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
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
        .resume-stats-list .resume-stats-label { position: relative; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .resume-stats-list .resume-stats-value { position: relative; font-variant-numeric: tabular-nums; font-weight: 600; }
        .resume-stats-list button { text-align: left; text-decoration: underline; text-decoration-color: rgb(0 0 0 / .2); }
        .resume-stats-empty { font-size: .875rem; color: rgb(107 114 128); }
        .resume-stats-map svg { width: 100%; height: auto; display: block; }
        .resume-map-land { fill: rgb(0 0 0 / .08); stroke: white; stroke-width: .5; }
        .resume-map-visited { fill: var(--info-600, rgb(2 132 199)); stroke: white; stroke-width: .5; }
        .dark .resume-map-land { fill: rgb(255 255 255 / .1); stroke: rgb(17 24 39); }
        .dark .resume-map-visited { stroke: rgb(17 24 39); }
        .resume-map-city { fill: var(--warning-500, rgb(245 158 11)); fill-opacity: .85; stroke: white; stroke-width: .75; }
        .resume-map-city-active { fill: var(--danger-500, rgb(239 68 68)); fill-opacity: 1; stroke-width: 1.5; }
        .resume-map-link { cursor: pointer; }
        .resume-map-link:hover .resume-map-visited, .resume-map-link:focus-visible .resume-map-visited { fill: var(--info-700, rgb(3 105 161)); }
        .resume-map-link:hover .resume-map-city, .resume-map-link:focus-visible .resume-map-city { fill-opacity: 1; stroke: rgb(17 24 39); }
        .resume-map-link:focus-visible { outline: none; }
        .resume-map-zoomed + * { margin-top: 0; }
        .resume-stats-map svg.resume-map-zoomed { max-height: 34rem; }
        .resume-stats-crumbs { display: flex; flex-wrap: wrap; gap: .4rem; align-items: center; margin-bottom: .75rem; font-size: .875rem; }
        .resume-stats-crumbs button { text-decoration: underline; text-decoration-color: rgb(0 0 0 / .25); }
        .resume-stats-crumbs [aria-current] { font-weight: 600; }
        .resume-stats-crumbs .resume-stats-note { margin: 0 0 0 .5rem; font-weight: 400; }
        .resume-stats-icon { display: inline-block; width: 1rem; height: 1rem; margin-right: .3rem; vertical-align: -.15rem; flex: none; opacity: .8; }
        .resume-stats-list small { margin-left: .35rem; color: rgb(75 85 99); font-size: .75rem; }
        .dark .resume-stats-list small, .dark .resume-stats-mark { color: rgb(209 213 219); }
        .resume-stats-list code { font-size: .8rem; }
        .resume-stats-mark { position: relative; flex: none; display: inline-flex; padding: .1rem; border-radius: .25rem; color: rgb(75 85 99); }
        .resume-stats-mark:hover, .resume-stats-mark:focus-visible { background: rgb(0 0 0 / .06); }
        .resume-stats-mark .resume-stats-icon { margin: 0; }
        .resume-stats-row-action { position: relative; flex: none; }
        .resume-stats-404 li { align-items: center; }
        .resume-stats-404 .resume-stats-label { display: grid; white-space: normal; }
        .resume-stats-404 .resume-stats-label small { margin-left: 0; }
        .resume-stats-chips { display: flex; flex-wrap: wrap; gap: .4rem .75rem; margin-bottom: .75rem; font-size: .8rem; }
        .resume-stats-chip { display: inline-flex; align-items: center; gap: .25rem; }
        .resume-sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
        .resume-stats-probes { margin-top: 1rem; }
        .resume-stats-heatmap-wrap { overflow-x: auto; }
        .resume-stats-heatmap { border-collapse: separate; border-spacing: 2px; width: 100%; min-width: 34rem; font-size: .7rem; }
        .resume-stats-heatmap th { font-weight: 500; color: rgb(107 114 128); text-align: left; padding: 0 .25rem; white-space: nowrap; }
        .resume-stats-heatmap td { position: relative; height: 1.35rem; background: rgb(0 0 0 / .04); border-radius: .2rem; }
        .dark .resume-stats-heatmap td { background: rgb(255 255 255 / .05); }
        .resume-stats-heat { position: absolute; inset: 0; border-radius: .2rem; background: var(--info-600, rgb(2 132 199)); }
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
