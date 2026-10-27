<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\StatAdminIp;
use App\Models\StatVisit;
use App\Support\Settings\Settings;
use App\Support\Stats\AdminIps;
use App\Support\Stats\Aggregator;
use App\Support\Stats\GeoIp;
use App\Support\Stats\GeoIpUpdater;
use App\Support\Stats\PageResolver;
use App\Support\Stats\StatsQuery;
use App\Support\Stats\WorldMap;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Locale;
use Throwable;

/**
 * Statistiche delle visite (senza cookie): andamento nel tempo, pagine più viste, provenienza, campagne, Paesi e
 * città con la mappa, dispositivi, sistemi, browser, lingue e reti, elenco delle ultime visite. Filtri per periodo
 * e per singola pagina (es. un articolo del blog). Impostazioni, account MaxMind e IP esclusi dai pulsanti in alto.
 */
class Statistics extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'statistics';

    protected string $view = 'filament.pages.statistics';

    /** Giorni del periodo ("7", "30", "90", "365") o "all" */
    #[Url]
    public string $period = '30';

    /** Pagina (es. "article:3"), vuoto = tutto il sito */
    #[Url(as: 'page_filter')]
    public string $pageFilter = '';

    public static function getNavigationLabel(): string
    {
        return __('admin.stats.title');
    }

    public function getTitle(): string
    {
        return __('admin.stats.title');
    }

    public function mount(): void
    {
        // Totali di oggi aggiornati all'apertura (bastano poche query)
        if (app(Settings::class)->bool('stats.enabled')) {
            rescue(fn () => app(Aggregator::class)->aggregate(today()), report: false);
        }
    }

    public function updatedPeriod(): void
    {
        $this->period = in_array($this->period, ['7', '30', '90', '365', 'all'], true) ? $this->period : '30';
        $this->resetTable();
    }

    public function updatedPageFilter(): void
    {
        $this->resetTable();
    }

    public function filterPage(string $page): void
    {
        $this->pageFilter = $page;
        $this->resetTable();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $settings = app(Settings::class);
        $enabled = $settings->bool('stats.enabled');
        $query = StatsQuery::forPeriod($this->period, $this->pageFilter);
        $pages = app(PageResolver::class);
        $locale = app()->getLocale();
        $countries = $query->top('country', 300);
        $cities = $query->cities();

        $lists = [
            'pages' => $this->pageFilter === '' ? $this->labelled($query->pages(), fn (string $page): string => $pages->label($page)) : [],
            'referrer' => $this->labelled($query->top('referrer'), fn (string $host): string => $host),
            'utm_source' => $this->labelled($query->top('utm_source'), fn (string $value): string => $value),
            'utm_campaign' => $this->labelled($query->top('utm_campaign'), fn (string $value): string => $value),
            'click_source' => $this->labelled($query->top('click_source'), fn (string $value): string => Str::headline($value)),
            'country' => $this->labelled(array_slice($countries, 0, 10, true), fn (string $code): string => (Locale::getDisplayRegion('-'.$code, $locale) ?: $code)),
            'city' => $this->labelled(array_slice(array_column(array_map(fn (array $city): array => ['label' => $city['city'].' ('.$city['country'].')', 'views' => $city['views']], $cities), 'views', 'label'), 0, 10, true), fn (string $label): string => $label),
            'device' => $this->labelled($query->top('device'), fn (string $device): string => Lang::has('admin.stats.devices.'.$device) ? (string) __('admin.stats.devices.'.$device) : Str::ucfirst($device)),
            'os' => $this->labelled($query->top('os'), fn (string $value): string => $value),
            'browser' => $this->labelled($query->top('browser'), fn (string $value): string => $value),
            'locale' => $this->labelled($query->top('locale'), fn (string $code): string => (Locale::getDisplayName($code, $locale) ?: $code)),
            'asn' => $this->labelled($query->top('asn'), fn (string $value): string => $value),
        ];

        return [
            'enabled' => $enabled,
            'totals' => $query->totals(),
            'lists' => $lists,
            'map' => app(WorldMap::class)->render($countries, array_map(fn (array $city): array => [
                'label' => $city['city'].' ('.$city['country'].')',
                'latitude' => $city['latitude'],
                'longitude' => $city['longitude'],
                'views' => $city['views'],
            ], $cities), $locale),
            'pageOptions' => $pages->labels(StatVisit::query()->distinct()->pluck('page')->merge(array_keys($query->pages(100)))->unique()->values()->all()),
            'pageLabel' => $this->pageFilter !== '' ? $pages->label($this->pageFilter) : null,
            'geoip' => app(GeoIp::class),
            'geoipStatus' => app(GeoIpUpdater::class)->status(),
            'retention' => (int) $settings->get('stats.retention_months', 12),
        ];
    }

    /**
     * @param  array<string, int>  $values
     * @param  callable(string): string  $label
     * @return list<array{key: string, label: string, views: int}>
     */
    private function labelled(array $values, callable $label): array
    {
        $list = [];

        foreach ($values as $key => $views) {
            $list[] = ['key' => (string) $key, 'label' => $label((string) $key), 'views' => $views];
        }

        return $list;
    }

    public function table(Table $table): Table
    {
        $pages = app(PageResolver::class);
        $query = StatsQuery::forPeriod($this->period, $this->pageFilter);

        return $table
            ->query(fn () => StatVisit::query()
                ->whereBetween('day', [$query->from->toDateString(), $query->to->toDateString()])
                ->when($this->pageFilter !== '', fn ($builder) => $builder->where('page', $this->pageFilter)))
            ->defaultSort('visited_at', 'desc')
            ->heading(__('admin.stats.visits'))
            ->description(fn (): string => __('admin.stats.visits_help', ['months' => (int) app(Settings::class)->get('stats.retention_months', 12)]))
            ->columns([
                TextColumn::make('visited_at')->label(__('admin.stats.columns.when'))->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('page')->label(__('admin.stats.columns.page'))
                    ->formatStateUsing(fn (string $state): string => $pages->label($state))
                    ->description(fn (StatVisit $record): string => $record->locale)
                    ->wrap(),
                TextColumn::make('referrer_host')->label(__('admin.stats.columns.source'))
                    ->formatStateUsing(fn (StatVisit $record): string => $this->source($record))
                    ->default('-')
                    ->wrap(),
                TextColumn::make('city')->label(__('admin.stats.columns.place'))
                    ->formatStateUsing(fn (StatVisit $record): string => trim(implode(', ', array_filter([$record->city, $record->country !== null ? (Locale::getDisplayRegion('-'.$record->country, app()->getLocale()) ?: $record->country) : null]))) ?: '-')
                    ->description(fn (StatVisit $record): ?string => $record->as_org)
                    ->default('-')
                    ->wrap(),
                TextColumn::make('browser')->label(__('admin.stats.columns.device'))
                    ->formatStateUsing(fn (StatVisit $record): string => implode(' · ', array_filter([$record->browser.($record->browser_version ? ' '.$record->browser_version : ''), $record->os])))
                    ->description(fn (StatVisit $record): ?string => $record->device !== null && Lang::has('admin.stats.devices.'.$record->device) ? (string) __('admin.stats.devices.'.$record->device) : $record->device)
                    ->default('-'),
                TextColumn::make('ip')->label('IP')->fontFamily('mono')->size('sm'),
            ])
            ->paginated([25, 50, 100])
            ->emptyStateHeading(__('admin.stats.no_visits'));
    }

    private function source(StatVisit $record): string
    {
        $utm = array_filter([$record->utm_source, $record->utm_medium, $record->utm_campaign]);
        $parts = array_filter([
            $record->referrer_host,
            $utm !== [] ? 'UTM: '.implode(' / ', $utm) : null,
            $record->click_source !== null ? Str::headline($record->click_source) : null,
        ]);

        return $parts !== [] ? implode(' · ', $parts) : '-';
    }

    /**
     * Scarica il blocco successivo di un database GeoLite (chiamato a ripetizione dalla pagina).
     *
     * @return array{received?: int, total?: int, done?: bool, error?: string}
     */
    public function geoipChunk(string $edition): array
    {
        try {
            return app(GeoIpUpdater::class)->next($edition);
        } catch (Throwable $exception) {
            return ['error' => $exception->getMessage()];
        }
    }

    /**
     * Database da scaricare (mancanti o con una versione più recente), dopo un controllo su MaxMind.
     *
     * @return array{editions?: list<string>, error?: string}
     */
    public function geoipPending(): array
    {
        try {
            $status = app(GeoIpUpdater::class)->check();

            return ['editions' => array_keys(array_filter($status, fn (array $edition): bool => $edition['update']))];
        } catch (Throwable $exception) {
            return ['error' => $exception->getMessage()];
        }
    }

    protected function getHeaderActions(): array
    {
        return [$this->settingsAction(), $this->geoipAccountAction(), $this->adminIpsAction()];
    }

    public function settingsAction(): Action
    {
        return Action::make('settings')
            ->label(__('admin.stats.settings'))
            ->icon(Heroicon::OutlinedCog6Tooth)
            ->color('gray')
            ->fillForm(fn (): array => [
                'enabled' => app(Settings::class)->bool('stats.enabled'),
                'retention_months' => (int) app(Settings::class)->get('stats.retention_months', 12),
                'admin_ip_days' => (int) app(Settings::class)->get('stats.admin_ip_days', 14),
            ])
            ->modalDescription(__('admin.stats.settings_help'))
            ->schema([
                Toggle::make('enabled')->label(__('admin.stats.fields.enabled'))->helperText(__('admin.stats.fields.enabled_help')),
                TextInput::make('retention_months')->label(__('admin.stats.fields.retention_months'))->helperText(__('admin.stats.fields.retention_months_help'))->integer()->minValue(1)->maxValue(60)->required(),
                TextInput::make('admin_ip_days')->label(__('admin.stats.fields.admin_ip_days'))->helperText(__('admin.stats.fields.admin_ip_days_help'))->integer()->minValue(1)->maxValue(365)->required(),
            ])
            ->action(function (array $data): void {
                app(Settings::class)->setMany([
                    'stats.enabled' => (bool) $data['enabled'],
                    'stats.retention_months' => (int) $data['retention_months'],
                    'stats.admin_ip_days' => (int) $data['admin_ip_days'],
                ]);

                if ($data['enabled']) {
                    app(AdminIps::class)->touch(request()->ip());
                }

                Notification::make()->success()->title(__('admin.messages.saved'))->send();
            });
    }

    public function geoipAccountAction(): Action
    {
        return Action::make('geoipAccount')
            ->label(__('admin.stats.geoip.account'))
            ->icon(Heroicon::OutlinedGlobeEuropeAfrica)
            ->color('gray')
            ->modalHeading(__('admin.stats.geoip.title'))
            ->modalDescription(new HtmlString(__('admin.stats.geoip.account_help')))
            ->schema([
                Text::make(fn (): string => app(GeoIp::class)->credentialsSource() === 'env' ? __('admin.stats.geoip.env') : ''),
                TextInput::make('account')
                    ->label(__('admin.stats.geoip.account_id'))
                    ->placeholder(fn (): string => app(GeoIp::class)->credentials()['account'] ?? '')
                    ->maxLength(20),
                TextInput::make('key')
                    ->label(__('admin.stats.geoip.license_key'))
                    ->password()
                    ->revealable()
                    ->placeholder(fn (): string => app(GeoIp::class)->credentials() !== null ? __('admin.updates.token_saved') : '')
                    ->maxLength(100),
                Toggle::make('remove')
                    ->label(__('admin.stats.geoip.remove'))
                    ->visible(fn (): bool => app(GeoIp::class)->credentialsSource() === 'admin'),
            ])
            ->action(function (array $data): void {
                $geoIp = app(GeoIp::class);

                if ($data['remove'] ?? false) {
                    $geoIp->setCredentials(null, null);
                    app(GeoIpUpdater::class)->deleteAll();
                    Notification::make()->success()->title(__('admin.messages.saved'))->send();

                    return;
                }

                if (filled($data['key'] ?? null)) {
                    $geoIp->setCredentials((string) ($data['account'] ?: ($geoIp->credentials()['account'] ?? '')), (string) $data['key']);
                }

                try {
                    $status = app(GeoIpUpdater::class)->check();
                    $missing = collect($status)->contains(fn (array $edition): bool => $edition['update']);

                    Notification::make()->success()
                        ->title(__('admin.stats.geoip.account_ok'))
                        ->body($missing ? __('admin.stats.geoip.download_now') : null)
                        ->send();
                } catch (Throwable $exception) {
                    Notification::make()->danger()->title(__('admin.stats.geoip.check_failed'))->body($exception->getMessage())->persistent()->send();
                }
            });
    }

    public function adminIpsAction(): Action
    {
        return Action::make('adminIps')
            ->label(__('admin.stats.admin_ips.title'))
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('gray')
            ->modalDescription(fn (): string => __('admin.stats.admin_ips.help', ['days' => app(AdminIps::class)->days(), 'ip' => (string) request()->ip()]))
            ->schema([
                CheckboxList::make('remove')
                    ->label(__('admin.stats.admin_ips.remove'))
                    ->options(fn (): array => StatAdminIp::query()->orderByDesc('last_seen_at')->get()
                        ->mapWithKeys(fn (StatAdminIp $ip): array => [$ip->id => $ip->ip.' · '.__('admin.stats.admin_ips.last_seen', ['when' => $ip->last_seen_at->diffForHumans()])])
                        ->all()),
            ])
            ->modalSubmitActionLabel(__('admin.stats.admin_ips.submit'))
            ->action(function (array $data): void {
                StatAdminIp::query()->whereIn('id', $data['remove'] ?? [])->delete();
                Notification::make()->success()->title(__('admin.messages.saved'))->send();
            });
    }
}
