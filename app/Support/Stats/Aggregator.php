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
 * Totali giornalieri (stat_daily) a partire dalle singole visite, per pagina e per dimensione: restano anche dopo
 * che le visite vengono cancellate (stats.retention_months) e rendono veloci i grafici sui periodi lunghi.
 * Ricalcolare un giorno lo sostituisce per intero, quindi si può rifare quante volte si vuole.
 */
class Aggregator
{
    /** Dimensioni dei totali: colonna della visita (o espressione) => nome */
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
     * Lavoro "stats" del cron: totali di ieri e di oggi (e dei giorni rimasti indietro), pulizia.
     */
    public function run(): string
    {
        $last = StatDaily::query()->max('day');
        $from = $last !== null ? Carbon::parse((string) $last)->subDay() : (StatVisit::query()->min('day') !== null ? Carbon::parse((string) StatVisit::query()->min('day')) : today());
        $days = 0;

        // Mai prima del periodo di conservazione: le visite più vecchie non ci sono più e i totali andrebbero persi
        $oldest = today()->subMonths(max(1, (int) $this->settings->get('stats.retention_months', 12)));

        for ($day = $from->copy()->max($oldest); $day->lte(today()); $day->addDay()) {
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

        // Città con le coordinate (per la mappa): "IT|Milan"
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
     * Cancella le visite più vecchie del periodo di conservazione (i totali restano).
     */
    public function prune(): int
    {
        $months = max(1, (int) $this->settings->get('stats.retention_months', 12));

        return StatVisit::query()->where('day', '<', today()->subMonths($months)->toDateString())->delete();
    }
}
