<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Project;
use App\Models\StatVisit;
use App\Support\Settings\Settings;
use App\Support\Stats\Aggregator;
use App\Support\Stats\Channels;
use App\Support\Stats\StatsQuery;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Fake statistics for the demo CV (Codespaces, screenshots): three months of visits (sessions of one to four pages)
 * from several countries, bots and pages not found, the same on every run. Turns on the statistics (and so the Privacy page). To be used after DemoSeeder:
 *
 *   php artisan db:seed --class=DemoStatsSeeder
 */
class DemoStatsSeeder extends Seeder
{
    /** Country, region, city, latitude, longitude, network, weight */
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

    /** Device, operating system and version, browser and version, weight */
    private const array CLIENTS = [
        ['desktop', 'Windows', '11', 'Chrome', '130', 20],
        ['desktop', 'Windows', '10', 'Chrome', '130', 8],
        ['desktop', 'Mac', '15', 'Safari', '18', 12],
        ['desktop', 'Windows', '11', 'Microsoft Edge', '130', 8],
        ['desktop', 'GNU/Linux', null, 'Firefox', '132', 5],
        ['smartphone', 'Android', '15', 'Chrome Mobile', '130', 22],
        ['smartphone', 'iOS', '18', 'Mobile Safari', '18', 18],
        ['tablet', 'iOS', '18', 'Mobile Safari', '18', 3],
    ];

    /** Referring site, weight (null = direct access) */
    private const array SOURCES = [[null, 40], ['linkedin.com', 18], ['google.com', 20], ['github.com', 8], ['bing.com', 4], ['x.com', 3], ['duckduckgo.com', 3], ['chatgpt.com', 3], ['perplexity.ai', 1]];

    /** Networks of organizations: some visits come from companies and universities (network, weight) */
    private const array ORGANIZATIONS = [['Northwind Consumer Goods', 3], ['Contoso Ltd', 2], ['Politecnico di Torino', 2], ['Fabrikam Bank', 1]];

    /** Bots: name, category, requests per day (minimum, maximum) */
    private const array BOTS = [
        ['Googlebot', 'search', 20, 45], ['Bingbot', 'search', 5, 15], ['GPTBot', 'ai', 2, 9], ['ClaudeBot', 'ai', 1, 5],
        ['PerplexityBot', 'ai', 0, 3], ['AhrefsBot', 'crawler', 3, 12], ['UptimeRobot', 'monitor', 24, 24], ['LinkedInBot', 'social', 0, 3],
        ['curl', 'tool', 0, 4],
    ];

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
        $rows = $bots = [];
        $today = StatsQuery::today();

        for ($day = 89; $day >= 0; $day--) {
            $date = $today->copy()->subDays($day);
            $weekend = $date->isWeekend();
            $count = mt_rand(10, 22) + intdiv(90 - $day, 8) - ($weekend ? 7 : 0);

            // visits (sessions) of one to four pages, a few minutes apart
            for ($i = 0; $i < intdiv($count * 2, 3); $i++) {
                [$country, $region, $city, $latitude, $longitude, $network] = $this->pick(self::PLACES, 6);
                [$device, $os, $osVersion, $browser, $version] = $this->pick(self::CLIENTS, 5);
                [$referrer] = $this->pick(self::SOURCES, 1);
                $campaign = mt_rand(1, 14) === 1;
                $organization = mt_rand(1, 9) === 1 ? (string) $this->pick(self::ORGANIZATIONS, 1)[0] : null;
                $clickSource = ! $campaign && mt_rand(1, 30) === 1 ? 'facebook' : null;
                $source = $campaign ? 'linkedin.com' : $referrer;
                $visitor = sprintf('%08x%08x', mt_rand(), mt_rand());
                $session = sprintf('%08x%08x', mt_rand(), mt_rand());
                $ip = mt_rand(2, 220).'.'.mt_rand(0, 255).'.'.mt_rand(0, 255).'.x';
                $asn = mt_rand(1000, 60000);
                $locale = mt_rand(1, 3) === 1 ? 'en-GB' : 'it-IT';
                // local time of the visit, stored in UTC like the real ones
                $local = $date->copy()->setTime(mt_rand(7, 22), mt_rand(0, 59), mt_rand(0, 59));
                $length = mt_rand(1, 2) === 1 ? 1 : mt_rand(2, 4);

                for ($view = 0; $view < $length; $view++) {
                    $page = $view === 0 && mt_rand(1, 3) > 1 ? 'cv' : (string) $this->pick(array_map(fn (string $key, int $weight): array => [$key, $weight], array_keys($pages), $pages), 1)[0];

                    $rows[] = [
                        'visited_at' => $local->copy()->utc(), 'day' => $date->toDateString(), 'hour' => $local->hour, 'page' => $page, 'path' => '/',
                        'locale' => $locale,
                        'referrer_host' => $view === 0 ? $source : null,
                        'utm_source' => $campaign ? 'linkedin' : null, 'utm_medium' => $campaign ? 'social' : null,
                        'utm_campaign' => $campaign ? 'new-job-2026' : null, 'utm_term' => null, 'utm_content' => null,
                        'click_source' => $clickSource,
                        'channel' => Channels::of($view === 0 ? $source : null, $campaign ? 'linkedin' : null, $campaign ? 'social' : null, $campaign ? 'new-job-2026' : null, $clickSource),
                        'device' => $device, 'os' => $os, 'os_version' => $osVersion, 'browser' => $browser, 'browser_version' => $version,
                        'country' => $country, 'region' => $region, 'city' => $city, 'latitude' => $latitude, 'longitude' => $longitude,
                        'asn' => $asn, 'as_org' => $organization ?? $network,
                        'ip' => $ip, 'visitor_id' => $visitor, 'session_id' => $session, 'ip_hash' => null, 'visitor_hash' => null,
                    ];
                    $local->addSeconds(mt_rand(20, 240));
                }
            }

            foreach (self::BOTS as [$name, $category, $min, $max]) {
                if (($hits = mt_rand($min, $max)) > 0) {
                    $bots[] = ['day' => $date->toDateString(), 'name' => $name, 'category' => $category, 'hits' => $hits];
                }
            }
        }

        foreach (array_chunk($bots, 500) as $chunk) {
            DB::table('stat_bots')->insert($chunk);
        }

        $now = now();

        foreach ([
            ['/it-IT/progetti/vecchio-portale', 14, 9, false, 'linkedin.com'], ['/en-GB/experience/old-role', 5, 3, false, 'google.com'],
            ['/cv.pdf', 7, 7, false, null], ['/wp-login.php', 42, 0, true, null], ['/.env', 19, 0, true, null], ['/xmlrpc.php', 11, 0, true, null],
        ] as [$path, $hits, $people, $probe, $referrer]) {
            DB::table('stat_not_found')->insert(['path' => $path, 'hits' => $hits, 'human_hits' => $people, 'probe' => $probe, 'last_referrer_host' => $referrer, 'first_seen_at' => $now->copy()->subDays(40), 'last_seen_at' => $now->copy()->subDays(mt_rand(0, 6))]);
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            StatVisit::query()->insert($chunk);
        }

        for ($day = 89; $day >= 0; $day--) {
            app(Aggregator::class)->aggregate($today->copy()->subDays($day));
        }

        app(Settings::class)->set('stats.enabled', true);
    }

    /**
     * Random item, proportional to its weight (the last field, after $fields values).
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
