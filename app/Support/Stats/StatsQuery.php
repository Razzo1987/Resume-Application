<?php

declare(strict_types=1);

namespace App\Support\Stats;

use App\Models\StatDaily;
use App\Models\StatVisit;
use App\Support\Settings\Settings;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Data for the Statistics page: views and CV downloads over time, most viewed pages and rankings by referrer,
 * campaign, country, city, device, operating system, browser, language, network and (shortened) IP address.
 * Without a page filter the rankings cover page views (CV downloads have their own total).
 *
 * With only period and page it reads the daily totals (stat_daily), which also cover the days whose single visits
 * have already been deleted. With other filters (referrer, campaign, country, IP...) it reads the single visits
 * (stat_visits), the only place where those details are combined: the data then goes back
 * stats.retention_months at most.
 */
class StatsQuery
{
    public const string DOWNLOAD = 'cv-download';

    /** Filters on the single visits: name => column ("city" is "country|city", as in the rankings) */
    public const array FILTERS = [
        'referrer' => 'referrer_host',
        'utm_source' => 'utm_source',
        'utm_medium' => 'utm_medium',
        'utm_campaign' => 'utm_campaign',
        'utm_term' => 'utm_term',
        'utm_content' => 'utm_content',
        'click_source' => 'click_source',
        'country' => 'country',
        'city' => 'city',
        'asn' => 'as_org',
        'device' => 'device',
        'os' => 'os',
        'browser' => 'browser',
        'locale' => 'locale',
        'ip' => 'ip',
    ];

    /** @var array<string, string> */
    public readonly array $filters;

    /**
     * @param  array<array-key, mixed>  $filters  name => value (unknown names and empty values are ignored)
     */
    public function __construct(
        public readonly CarbonInterface $from,
        public readonly CarbonInterface $to,
        public readonly ?string $page = null,
        array $filters = [],
    ) {
        $this->filters = self::clean($filters);
    }

    /**
     * @param  string  $period  days ("7", "30", "90", "365") or "all"
     * @param  array<array-key, mixed>  $filters
     */
    public static function forPeriod(string $period, ?string $page = null, array $filters = []): self
    {
        $to = self::today();
        $from = match ($period) {
            'all' => Carbon::parse((string) (StatDaily::query()->min('day') ?? StatVisit::query()->min('day') ?? $to->toDateString())),
            default => $to->copy()->subDays(max(1, (int) $period) - 1),
        };

        return new self($from, $to, $page !== '' ? $page : null, $filters);
    }

    /**
     * Today in the time zone of the site (the days of the statistics are local days).
     */
    public static function today(): Carbon
    {
        return Carbon::today(app(Settings::class)->timezone());
    }

    /**
     * Only known filters with a non-empty value.
     *
     * @param  array<array-key, mixed>  $filters
     * @return array<string, string>
     */
    public static function clean(array $filters): array
    {
        $clean = [];

        foreach ($filters as $name => $value) {
            if (is_string($name) && array_key_exists($name, self::FILTERS) && is_scalar($value) && trim((string) $value) !== '') {
                $clean[$name] = trim((string) $value);
            }
        }

        return $clean;
    }

    /**
     * True when the data comes from the single visits (filters other than period and page).
     */
    public function isDetailed(): bool
    {
        return $this->filters !== [];
    }

    /**
     * @return array{views: int, downloads: int, countries: int, pages: int}
     */
    public function totals(): array
    {
        if ($this->isDetailed()) {
            $views = $this->pageVisits()->count();
            $downloads = $this->page === null || $this->page === self::DOWNLOAD ? $this->visits()->where('page', self::DOWNLOAD)->count() : 0;
            $countries = $this->pageVisits()->whereNotNull('country')->distinct()->count('country');
            $pages = $this->page === null ? $this->visits()->where('page', '!=', self::DOWNLOAD)->distinct()->count('page') : 1;
        } else {
            $views = (int) $this->rows('total')->sum('views');
            $downloads = $this->page === null || $this->page === self::DOWNLOAD
                ? (int) $this->base('total')->where('page', self::DOWNLOAD)->sum('views')
                : 0;
            $countries = $this->rows('country')->distinct()->count('value');
            $pages = $this->page === null ? $this->base('total')->where('page', '!=', self::DOWNLOAD)->distinct()->count('page') : 1;
        }

        return [
            'views' => $this->page === self::DOWNLOAD ? 0 : $views,
            'downloads' => $downloads,
            'countries' => $countries,
            'pages' => $pages,
        ];
    }

    /**
     * Views and downloads per day (every day of the period, including those at zero).
     *
     * @return array{labels: list<string>, views: list<int>, downloads: list<int>}
     */
    public function timeline(): array
    {
        if ($this->isDetailed()) {
            $views = $this->pageVisits()->selectRaw('day, count(*) as total')->groupBy('day')->pluck('total', 'day');
            $downloads = $this->visits()->where('page', self::DOWNLOAD)->selectRaw('day, count(*) as total')->groupBy('day')->pluck('total', 'day');
        } else {
            $views = $this->rows('total')->selectRaw('day, sum(views) as total')->groupBy('day')->pluck('total', 'day');
            $downloads = $this->base('total')->where('page', self::DOWNLOAD)->selectRaw('day, sum(views) as total')->groupBy('day')->pluck('total', 'day');
        }

        $labels = $viewSeries = $downloadSeries = [];

        for ($day = Carbon::parse($this->from); $day->lte($this->to); $day->addDay()) {
            $key = $day->toDateString();
            $labels[] = $key;
            $viewSeries[] = (int) ($views[$key] ?? $views[$key.' 00:00:00'] ?? 0);
            $downloadSeries[] = (int) ($downloads[$key] ?? $downloads[$key.' 00:00:00'] ?? 0);
        }

        return ['labels' => $labels, 'views' => $this->page === self::DOWNLOAD ? $downloadSeries : $viewSeries, 'downloads' => $downloadSeries];
    }

    /**
     * Most viewed pages: key => views.
     *
     * @return array<string, int>
     */
    public function pages(int $limit = 10): array
    {
        $query = $this->isDetailed()
            ? $this->visits()->where('page', '!=', self::DOWNLOAD)->selectRaw('page, count(*) as total')
            : $this->base('total')->where('page', '!=', self::DOWNLOAD)->selectRaw('page, sum(views) as total');

        return $query
            ->groupBy('page')
            ->orderByDesc('total')
            ->limit($limit)
            ->pluck('total', 'page')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }

    /**
     * Ranking of a dimension: value => views (the top $limit). "ip" (shortened addresses) always comes from the
     * single visits: the daily totals never keep addresses.
     *
     * @return array<string, int>
     */
    public function top(string $dimension, int $limit = 10): array
    {
        if ($this->isDetailed() || $dimension === 'ip') {
            $column = $dimension === 'city' ? null : (self::FILTERS[$dimension] ?? null);

            if ($column === null) {
                return [];
            }

            return $this->pageVisits()
                ->whereNotNull($column)
                ->selectRaw("{$column} as value, count(*) as total")
                ->groupBy($column)
                ->orderByDesc('total')
                ->limit($limit)
                ->pluck('total', 'value')
                ->map(fn (mixed $total): int => (int) $total)
                ->all();
        }

        return $this->rows($dimension)
            ->selectRaw('value, sum(views) as total')
            ->groupBy('value')
            ->orderByDesc('total')
            ->limit($limit)
            ->pluck('total', 'value')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }

    /**
     * Cities with coordinates, for the map.
     *
     * @return list<array{country: string, city: string, latitude: float, longitude: float, views: int}>
     */
    public function cities(int $limit = 200): array
    {
        if ($this->isDetailed()) {
            return $this->pageVisits()
                ->whereNotNull('city')
                ->whereNotNull('latitude')
                ->selectRaw('country, city, avg(latitude) as lat, avg(longitude) as lon, count(*) as total')
                ->groupBy('country', 'city')
                ->orderByDesc('total')
                ->limit($limit)
                ->toBase()
                ->get()
                ->map(fn (object $row): array => [
                    'country' => (string) $row->country,
                    'city' => (string) $row->city,
                    'latitude' => (float) $row->lat,
                    'longitude' => (float) $row->lon,
                    'views' => (int) $row->total,
                ])
                ->values()
                ->all();
        }

        return $this->rows('city')
            ->whereNotNull('latitude')
            ->selectRaw('value, avg(latitude) as latitude, avg(longitude) as longitude, sum(views) as total')
            ->groupBy('value')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(function (StatDaily $row): array {
                [$country, $city] = array_pad(explode('|', $row->value, 2), 2, '');

                return [
                    'country' => $country,
                    'city' => $city,
                    'latitude' => (float) $row->getAttribute('latitude'),
                    'longitude' => (float) $row->getAttribute('longitude'),
                    'views' => (int) $row->getAttribute('total'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Values of a filter in the period (and page), the most frequent first (for the filter menus): value => visits.
     * The other filters are not applied, so a value can always be changed.
     *
     * @return array<string, int>
     */
    public function options(string $filter, int $limit = 100): array
    {
        $period = StatVisit::query()
            ->whereBetween('day', [$this->from->toDateString(), $this->to->toDateString()])
            ->when($this->page !== null, fn (Builder $query) => $query->where('page', $this->page));

        if ($filter === 'city') {
            return $period->whereNotNull('city')
                ->selectRaw("concat(country, '|', city) as value, count(*) as total")
                ->groupBy('country', 'city')
                ->orderByDesc('total')
                ->limit($limit)
                ->pluck('total', 'value')
                ->map(fn (mixed $total): int => (int) $total)
                ->all();
        }

        $column = self::FILTERS[$filter] ?? null;

        if ($column === null) {
            return [];
        }

        return $period->whereNotNull($column)
            ->selectRaw("{$column} as value, count(*) as total")
            ->groupBy($column)
            ->orderByDesc('total')
            ->limit($limit)
            ->pluck('total', 'value')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }

    /**
     * Single visits of the period with the page filter and all the other filters (CV downloads included when there
     * is no page filter).
     *
     * @return Builder<StatVisit>
     */
    public function visits(): Builder
    {
        $query = StatVisit::query()->whereBetween('day', [$this->from->toDateString(), $this->to->toDateString()]);

        if ($this->page !== null) {
            $query->where('page', $this->page);
        }

        foreach ($this->filters as $name => $value) {
            if ($name === 'city') {
                [$country, $city] = array_pad(explode('|', $value, 2), 2, '');
                $query->where('country', $country)->where('city', $city);
            } else {
                $query->where(self::FILTERS[$name], $value);
            }
        }

        return $query;
    }

    /**
     * Single visits counted as page views: the page filter, or all pages except CV downloads.
     *
     * @return Builder<StatVisit>
     */
    private function pageVisits(): Builder
    {
        $query = $this->visits();

        return $this->page !== null ? $query : $query->where('page', '!=', self::DOWNLOAD);
    }

    /**
     * Totals of a dimension in the period, with the page filter (or all pages except downloads).
     *
     * @return Builder<StatDaily>
     */
    private function rows(string $dimension): Builder
    {
        $query = $this->base($dimension);

        return $this->page !== null ? $query->where('page', $this->page) : $query->where('page', '!=', self::DOWNLOAD);
    }

    /**
     * @return Builder<StatDaily>
     */
    private function base(string $dimension): Builder
    {
        return StatDaily::query()
            ->where('dimension', $dimension)
            ->whereBetween('day', [$this->from->toDateString(), $this->to->toDateString()]);
    }
}
