<?php

declare(strict_types=1);

namespace App\Support\Stats;

use App\Models\StatDaily;
use App\Models\StatVisit;
use App\Support\Settings\Settings;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Daily totals (stat_daily) built from the individual visits, per page and per dimension (and for the whole site:
 * visitors, sessions, bounces, duration, under the page "*"): they remain even after the visits are deleted
 * (stats.retention_months) and make the charts over long periods fast.
 * Recalculating a day replaces it entirely, so it can be redone as many times as needed.
 */
class Aggregator
{
    /** Dimensions of the totals: visit column (or expression) => name */
    public const array DIMENSIONS = [
        'locale' => 'locale',
        'device' => 'device',
        'os' => 'os',
        'browser' => 'browser',
        'country' => 'country',
        'referrer' => 'referrer_host',
        'utm_source' => 'utm_source',
        'utm_medium' => 'utm_medium',
        'utm_campaign' => 'utm_campaign',
        'click_source' => 'click_source',
        'asn' => 'as_org',
        'channel' => 'channel',
        'hour' => 'hour',
    ];

    /** Dimensions made of two columns ("Windows 11", "Chrome 130") */
    public const array COMBINED = [
        'os_version' => ['os', 'os_version'],
        'browser_version' => ['browser', 'browser_version'],
    ];

    /** Page of the totals that cover the whole site (visitors, sessions, bounces, duration) */
    public const string SITE = '*';

    public function __construct(private readonly Settings $settings) {}

    /**
     * The cron's "stats" job: totals for yesterday and today (and for any days left behind), cleanup.
     */
    public function run(): string
    {
        $last = StatDaily::query()->max('day');
        $today = StatsQuery::today();
        $from = $last !== null ? Carbon::parse((string) $last)->subDay() : (StatVisit::query()->min('day') !== null ? Carbon::parse((string) StatVisit::query()->min('day')) : $today->copy());
        $days = 0;

        // Never before the retention period: older visits are gone and the totals would be lost
        $oldest = $today->copy()->subMonths(max(1, (int) $this->settings->get('stats.retention_months', 12)));

        for ($day = $from->copy()->max($oldest); $day->lte($today); $day->addDay()) {
            $this->aggregate($day);
            $days++;
        }

        $pruned = $this->prune();
        $admins = app(Exclusions::class)->prune();
        app(Visitors::class)->prune(force: true);

        return "days {$days}, deleted {$pruned} visits, {$admins} admin IPs";
    }

    public function aggregate(CarbonInterface $day): void
    {
        $date = $day->toDateString();
        $rows = [];

        foreach (StatVisit::query()->where('day', $date)->selectRaw('page, count(*) as views')->groupBy('page')->toBase()->get() as $row) {
            $rows[] = ['day' => $date, 'page' => $row->page, 'dimension' => 'total', 'value' => '', 'views' => (int) $row->views, 'latitude' => null, 'longitude' => null];
        }

        foreach (self::DIMENSIONS as $dimension => $column) {
            $query = StatVisit::query()
                ->where('day', $date)
                ->whereNotNull($column)
                ->selectRaw("page, {$column} as value, count(*) as views")
                ->groupBy('page', $column);

            foreach ($query->toBase()->get() as $row) {
                $rows[] = ['day' => $date, 'page' => $row->page, 'dimension' => $dimension, 'value' => mb_substr((string) $row->value, 0, 200), 'views' => (int) $row->views, 'latitude' => null, 'longitude' => null];
            }
        }

        foreach (self::COMBINED as $dimension => [$name, $version]) {
            $query = StatVisit::query()
                ->where('day', $date)
                ->whereNotNull($name)
                ->whereNotNull($version)
                ->selectRaw("page, concat({$name}, ' ', {$version}) as value, count(*) as views")
                ->groupByRaw("page, {$name}, {$version}");

            foreach ($query->toBase()->get() as $row) {
                $rows[] = ['day' => $date, 'page' => $row->page, 'dimension' => $dimension, 'value' => mb_substr((string) $row->value, 0, 200), 'views' => (int) $row->views, 'latitude' => null, 'longitude' => null];
            }
        }

        // Cities with their coordinates (for the map): "IT|Milan"
        $cities = StatVisit::query()
            ->where('day', $date)
            ->whereNotNull('city')
            ->selectRaw('page, country, city, avg(latitude) as latitude, avg(longitude) as longitude, count(*) as views')
            ->groupBy('page', 'country', 'city');

        foreach ($cities->toBase()->get() as $row) {
            $rows[] = [
                'day' => $date, 'page' => $row->page, 'dimension' => 'city', 'value' => mb_substr($row->country.'|'.$row->city, 0, 200),
                'views' => (int) $row->views, 'latitude' => $row->latitude !== null ? round((float) $row->latitude, 4) : null,
                'longitude' => $row->longitude !== null ? round((float) $row->longitude, 4) : null,
            ];
        }

        // Visitors per page and for the whole site
        $visitors = StatVisit::query()->where('day', $date)->whereNotNull('visitor_id');

        foreach ((clone $visitors)->selectRaw('page, count(distinct visitor_id) as visitors')->groupBy('page')->toBase()->get() as $row) {
            $rows[] = ['day' => $date, 'page' => $row->page, 'dimension' => 'visitors', 'value' => '', 'views' => (int) $row->visitors, 'latitude' => null, 'longitude' => null];
        }

        if (($total = (clone $visitors)->distinct()->count('visitor_id')) > 0) {
            $rows[] = ['day' => $date, 'page' => self::SITE, 'dimension' => 'visitors', 'value' => '', 'views' => $total, 'latitude' => null, 'longitude' => null];
        }

        // Sessions started on this day: how many, bounces, duration, entry and exit pages
        $sessions = self::sessions(StatVisit::query()
            ->whereIn('session_id', StatVisit::query()->where('day', $date)->whereNotNull('session_id')->select('session_id'))
            ->orderBy('session_id')->orderBy('visited_at')->orderBy('id')
            ->toBase()
            ->get(['session_id', 'page', 'day', 'visited_at']), $date);

        foreach (['sessions', 'bounces', 'duration'] as $metric) {
            if ($sessions['sessions'] > 0) {
                $rows[] = ['day' => $date, 'page' => self::SITE, 'dimension' => $metric, 'value' => '', 'views' => $sessions[$metric], 'latitude' => null, 'longitude' => null];
            }
        }

        foreach (['entry' => $sessions['entries'], 'exit' => $sessions['exits']] as $dimension => $pages) {
            foreach ($pages as $page => $count) {
                $rows[] = ['day' => $date, 'page' => $page, 'dimension' => $dimension, 'value' => '', 'views' => $count, 'latitude' => null, 'longitude' => null];
            }
        }

        DB::transaction(function () use ($date, $rows): void {
            StatDaily::query()->where('day', $date)->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                StatDaily::query()->insert($chunk);
            }
        });
    }

    /**
     * Sessions in a list of page views (ordered by session and time): number, bounces (sessions of a single page),
     * total duration in seconds (from the first to the last page: the time on the last page is unknown) and entry and
     * exit pages. With $day only the sessions started on that day count.
     *
     * @param  iterable<object{session_id: string, page: string, day: string, visited_at: string}>  $views
     * @return array{sessions: int, views: int, bounces: int, duration: int, entries: array<string, int>, exits: array<string, int>}
     */
    public static function sessions(iterable $views, ?string $day = null): array
    {
        $result = ['sessions' => 0, 'views' => 0, 'bounces' => 0, 'duration' => 0, 'entries' => [], 'exits' => []];
        $groups = [];

        foreach ($views as $view) {
            $groups[(string) $view->session_id][] = $view;
        }

        foreach ($groups as $pages) {
            $first = $pages[0];
            $last = $pages[count($pages) - 1];

            if ($day !== null && substr((string) $first->day, 0, 10) !== $day) {
                continue;
            }

            $result['sessions']++;
            $result['views'] += count($pages);
            $result['bounces'] += count($pages) === 1 ? 1 : 0;
            $result['duration'] += max(0, (int) strtotime((string) $last->visited_at) - (int) strtotime((string) $first->visited_at));
            $result['entries'][$first->page] = ($result['entries'][$first->page] ?? 0) + 1;
            $result['exits'][$last->page] = ($result['exits'][$last->page] ?? 0) + 1;
        }

        return $result;
    }

    /**
     * Deletes visits, bot counts and pages not found older than the retention period (the totals remain).
     */
    public function prune(): int
    {
        $months = max(1, (int) $this->settings->get('stats.retention_months', 12));
        $oldest = StatsQuery::today()->subMonths($months);

        DB::table('stat_bots')->where('day', '<', $oldest->toDateString())->delete();
        DB::table('stat_not_found')->where('last_seen_at', '<', $oldest)->delete();

        return StatVisit::query()->where('day', '<', $oldest->toDateString())->delete();
    }
}
