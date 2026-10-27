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
 * Daily totals (stat_daily) built from the individual visits, per page and per dimension: they remain even after
 * the visits are deleted (stats.retention_months) and make the charts over long periods fast.
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
    ];

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
        $admins = app(AdminIps::class)->prune();

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

        DB::transaction(function () use ($date, $rows): void {
            StatDaily::query()->where('day', $date)->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                StatDaily::query()->insert($chunk);
            }
        });
    }

    /**
     * Deletes visits older than the retention period (the totals remain).
     */
    public function prune(): int
    {
        $months = max(1, (int) $this->settings->get('stats.retention_months', 12));

        return StatVisit::query()->where('day', '<', StatsQuery::today()->subMonths($months)->toDateString())->delete();
    }
}
