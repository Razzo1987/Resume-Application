<?php

declare(strict_types=1);

namespace App\Support\Stats;

use App\Models\StatDaily;
use App\Support\Settings\Settings;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Data for the Statistics page, from the daily totals (stat_daily): views and CV downloads over time, most viewed
 * pages and rankings by referrer, campaign, country, city, device, operating system, browser, language, network.
 * Without a page filter the rankings cover page views (CV downloads have their own total).
 */
class StatsQuery
{
    public const string DOWNLOAD = 'cv-download';

    public function __construct(
        public readonly CarbonInterface $from,
        public readonly CarbonInterface $to,
        public readonly ?string $page = null,
    ) {}

    /**
     * @param  string  $period  days ("7", "30", "90", "365") or "all"
     */
    public static function forPeriod(string $period, ?string $page = null): self
    {
        $to = self::today();
        $from = match ($period) {
            'all' => Carbon::parse((string) (StatDaily::query()->min('day') ?? $to->toDateString())),
            default => $to->copy()->subDays(max(1, (int) $period) - 1),
        };

        return new self($from, $to, $page !== '' ? $page : null);
    }

    /**
     * Today in the time zone of the site (the days of the statistics are local days).
     */
    public static function today(): Carbon
    {
        return Carbon::today(app(Settings::class)->timezone());
    }

    /**
     * @return array{views: int, downloads: int, countries: int, pages: int}
     */
    public function totals(): array
    {
        $views = (int) $this->rows('total')->sum('views');
        $downloads = $this->page === null || $this->page === self::DOWNLOAD
            ? (int) $this->base('total')->where('page', self::DOWNLOAD)->sum('views')
            : 0;

        return [
            'views' => $this->page === self::DOWNLOAD ? 0 : $views,
            'downloads' => $downloads,
            'countries' => $this->rows('country')->distinct()->count('value'),
            'pages' => $this->page === null ? $this->base('total')->where('page', '!=', self::DOWNLOAD)->distinct()->count('page') : 1,
        ];
    }

    /**
     * Views and downloads per day (every day of the period, including those at zero).
     *
     * @return array{labels: list<string>, views: list<int>, downloads: list<int>}
     */
    public function timeline(): array
    {
        $views = $this->rows('total')->selectRaw('day, sum(views) as total')->groupBy('day')->pluck('total', 'day');
        $downloads = $this->base('total')->where('page', self::DOWNLOAD)->selectRaw('day, sum(views) as total')->groupBy('day')->pluck('total', 'day');
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
        return $this->base('total')
            ->where('page', '!=', self::DOWNLOAD)
            ->selectRaw('page, sum(views) as total')
            ->groupBy('page')
            ->orderByDesc('total')
            ->limit($limit)
            ->pluck('total', 'page')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }

    /**
     * Ranking of a dimension: value => views (the top $limit).
     *
     * @return array<string, int>
     */
    public function top(string $dimension, int $limit = 10): array
    {
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
