<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Project;
use App\Models\StatVisit;
use App\Support\Settings\Settings;
use App\Support\Stats\Aggregator;
use Illuminate\Database\Seeder;

/**
 * Statistiche finte per il CV dimostrativo (Codespaces, screenshot): tre mesi di visite da diversi Paesi, sempre
 * le stesse a ogni esecuzione. Accende le statistiche (e quindi la pagina Privacy). Da usare dopo DemoSeeder:
 *
 *   php artisan db:seed --class=DemoStatsSeeder
 */
class DemoStatsSeeder extends Seeder
{
    /** Paese, regione, città, latitudine, longitudine, rete, peso */
    private const array PLACES = [
        ['IT', 'Piedmont', 'Turin', 45.0703, 7.6869, 'Telecom Italia', 24],
        ['IT', 'Lombardy', 'Milan', 45.4643, 9.1895, 'Fastweb', 18],
        ['IT', 'Lazio', 'Rome', 41.8933, 12.4829, 'Vodafone Italia', 10],
        ['IT', 'Emilia-Romagna', 'Bologna', 44.4938, 11.3387, 'WINDTRE', 6],
        ['DE', 'Bavaria', 'Munich', 48.1374, 11.5755, 'Deutsche Telekom AG', 7],
        ['GB', 'England', 'London', 51.5072, -0.1276, 'British Telecommunications', 8],
        ['FR', 'Ile-de-France', 'Paris', 48.8566, 2.3522, 'Orange', 5],
        ['ES', 'Madrid', 'Madrid', 40.4168, -3.7038, 'Telefonica de Espana', 4],
        ['NL', 'North Holland', 'Amsterdam', 52.3676, 4.9041, 'KPN B.V.', 4],
        ['CH', 'Zurich', 'Zurich', 47.3769, 8.5417, 'Swisscom', 3],
        ['US', 'California', 'San Francisco', 37.7749, -122.4194, 'Comcast Cable', 6],
        ['US', 'New York', 'New York', 40.7128, -74.006, 'Verizon Business', 4],
        ['CA', 'Ontario', 'Toronto', 43.6532, -79.3832, 'Rogers Communications', 2],
        ['BR', 'Sao Paulo', 'Sao Paulo', -23.5558, -46.6396, 'Claro S.A.', 2],
        ['IN', 'Karnataka', 'Bengaluru', 12.9716, 77.5946, 'Bharti Airtel', 2],
        ['AU', 'New South Wales', 'Sydney', -33.8688, 151.2093, 'Telstra', 1],
    ];

    /** Dispositivo, sistema, browser, versione, peso */
    private const array CLIENTS = [
        ['desktop', 'Windows', 'Chrome', '130', 28],
        ['desktop', 'Mac', 'Safari', '18', 12],
        ['desktop', 'Windows', 'Microsoft Edge', '130', 8],
        ['desktop', 'GNU/Linux', 'Firefox', '132', 5],
        ['smartphone', 'Android', 'Chrome Mobile', '130', 22],
        ['smartphone', 'iOS', 'Mobile Safari', '18', 18],
        ['tablet', 'iOS', 'Mobile Safari', '18', 3],
    ];

    /** Sito di provenienza, peso (null = accesso diretto) */
    private const array SOURCES = [[null, 40], ['linkedin.com', 18], ['google.com', 20], ['github.com', 8], ['bing.com', 4], ['x.com', 3], ['duckduckgo.com', 3]];

    public function run(): void
    {
        mt_srand(2026);

        $pages = ['cv' => 45, 'blog' => 6, 'cv-download' => 9, 'privacy' => 1];

        foreach (Project::query()->get() as $project) {
            $pages['project:'.$project->id] = $project->hasDetailPage() ? 9 : 0;
        }

        foreach (Article::query()->get() as $article) {
            $pages['article:'.$article->id] = 8;
        }

        $pages = array_filter($pages);
        $rows = [];

        for ($day = 89; $day >= 0; $day--) {
            $date = today()->subDays($day);
            $weekend = $date->isWeekend();
            $count = mt_rand(10, 22) + intdiv(90 - $day, 8) - ($weekend ? 7 : 0);

            for ($i = 0; $i < $count; $i++) {
                [$country, $region, $city, $latitude, $longitude, $network] = $this->pick(self::PLACES, 6);
                [$device, $os, $browser, $version] = $this->pick(self::CLIENTS, 4);
                [$referrer] = $this->pick(self::SOURCES, 1);
                $page = (string) $this->pick(array_map(fn (string $key, int $weight): array => [$key, $weight], array_keys($pages), $pages), 1)[0];
                $campaign = mt_rand(1, 14) === 1;
                $at = $date->copy()->setTime(mt_rand(7, 22), mt_rand(0, 59), mt_rand(0, 59));

                $rows[] = [
                    'visited_at' => $at, 'day' => $date->toDateString(), 'page' => $page, 'path' => '/',
                    'locale' => mt_rand(1, 3) === 1 ? 'en-GB' : 'it-IT',
                    'referrer_host' => $campaign ? 'linkedin.com' : $referrer,
                    'utm_source' => $campaign ? 'linkedin' : null, 'utm_medium' => $campaign ? 'social' : null,
                    'utm_campaign' => $campaign ? 'new-job-2026' : null, 'utm_term' => null, 'utm_content' => null,
                    'click_source' => ! $campaign && mt_rand(1, 30) === 1 ? 'facebook' : null,
                    'device' => $device, 'os' => $os, 'browser' => $browser, 'browser_version' => $version,
                    'country' => $country, 'region' => $region, 'city' => $city, 'latitude' => $latitude, 'longitude' => $longitude,
                    'asn' => mt_rand(1000, 60000), 'as_org' => $network,
                    'ip' => mt_rand(2, 220).'.'.mt_rand(0, 255).'.'.mt_rand(0, 255).'.x',
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            StatVisit::query()->insert($chunk);
        }

        for ($day = 89; $day >= 0; $day--) {
            app(Aggregator::class)->aggregate(today()->subDays($day));
        }

        app(Settings::class)->set('stats.enabled', true);
    }

    /**
     * Elemento a caso, in proporzione al peso (ultimo campo dopo $fields valori).
     *
     * @param  list<list<mixed>>  $items
     * @return list<mixed>
     */
    private function pick(array $items, int $fields): array
    {
        $total = array_sum(array_map(fn (array $item): int => (int) $item[$fields], $items));
        $random = mt_rand(1, $total);

        foreach ($items as $item) {
            $random -= (int) $item[$fields];

            if ($random <= 0) {
                return $item;
            }
        }

        return $items[0];
    }
}
