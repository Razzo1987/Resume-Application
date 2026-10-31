<?php

declare(strict_types=1);

namespace App\Support\Stats;

use App\Models\StatVisit;
use App\Support\Settings\Settings;
use DeviceDetector\Cache\PSR16Bridge;
use DeviceDetector\ClientHints;
use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\Device\AbstractDeviceParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records visits to the public site, without cookies and without reading anything from the device: only what the
 * browser sends with every request (URL, IP, user agent, referring page). Runs after the response.
 *
 * Pages (200 HTML responses, or 304 if the browser already had them) and CV downloads are counted as visits; bots,
 * programs and feed readers are counted apart, by name and day, without any address; previews (prefetch), the
 * administrator's visits (browser signed in to the admin) and the excluded addresses are not counted. The IP is only
 * kept in memory for the origin, the exclusions and the 24-hour hashes (see Visitors), then stored truncated.
 * Addresses that do not exist are counted too (NotFound), by path.
 */
class Tracker
{
    /** Platform click parameters: only where the click comes from is stored, never the code. */
    public const array CLICK_IDS = [
        'fbclid' => 'facebook',
        'igshid' => 'instagram',
        'gclid' => 'google-ads',
        'gbraid' => 'google-ads',
        'wbraid' => 'google-ads',
        'dclid' => 'google-ads',
        'msclkid' => 'microsoft-ads',
        'li_fat_id' => 'linkedin',
        'ttclid' => 'tiktok',
        'twclid' => 'x',
        'mc_eid' => 'mailchimp',
        'yclid' => 'yandex',
    ];

    /** Crawlers of AI companies (training, search or fetching on behalf of a user), matched in the user agent */
    public const string AI_BOTS = '/(GPTBot|ChatGPT-User|OAI-SearchBot|ClaudeBot|Claude-User|Claude-SearchBot|Claude-Web|anthropic-ai|PerplexityBot|Perplexity-User|CCBot|Bytespider|Amazonbot|meta-externalagent|meta-externalfetcher|Google-Extended|GoogleOther|Applebot-Extended|cohere-ai|cohere-training-data-crawler|Diffbot|YouBot|MistralAI-User|DuckAssistBot|AI2Bot|PanguBot|TikTokSpider|Timpibot|ImagesiftBot|omgili|Grok)/i';

    /** Bot categories of the device detector → categories of the Statistics page */
    private const array BOT_CATEGORIES = [
        'Search bot' => 'search',
        'Crawler' => 'crawler',
        'Site Monitor' => 'monitor',
        'Security Checker' => 'monitor',
        'Security search bot' => 'monitor',
        'Validator' => 'monitor',
        'Benchmark' => 'monitor',
        'Network Monitor' => 'monitor',
        'Service Agent' => 'monitor',
        'Social Media Agent' => 'social',
        'Feed Fetcher' => 'feed',
        'Feed Parser' => 'feed',
        'Feed Reader' => 'feed',
        'Read-it-later Service' => 'feed',
    ];

    public function __construct(
        private readonly Settings $settings,
        private readonly PageResolver $pages,
        private readonly GeoIp $geoIp,
        private readonly Exclusions $exclusions,
        private readonly AdminSession $adminSession,
        private readonly Visitors $visitors,
    ) {}

    public function isEnabled(): bool
    {
        return $this->settings->bool('stats.enabled');
    }

    /**
     * After every response of the public site: a visit, a bot request or nothing.
     */
    public function handle(Request $request, Response $response): void
    {
        if (! $this->isEnabled() || ! $request->isMethod('GET') || ! in_array($response->getStatusCode(), [200, 304], true)) {
            return;
        }

        $userAgent = (string) $request->userAgent();

        if ($userAgent === '') {
            return;
        }

        $detected = $this->detect($userAgent, $request->headers->all());

        if ($detected['bot'] !== null) {
            $this->recordBot($detected['bot']['name'], $detected['bot']['category']);

            return;
        }

        if ($this->shouldTrack($request, $response)) {
            $this->record($request, $detected['client']);
        }
    }

    public function shouldTrack(Request $request, Response $response): bool
    {
        if (! $this->isEnabled() || ! $request->isMethod('GET') || ! in_array($response->getStatusCode(), [200, 304], true)) {
            return false;
        }

        // Previews loaded in advance by the browser (they are not visits)
        $purpose = strtolower((string) ($request->header('Sec-Purpose') ?? $request->header('Purpose') ?? ''));

        if (str_contains($purpose, 'prefetch') || str_contains($purpose, 'prerender')) {
            return false;
        }

        // 304: the page was already in the browser cache, but it is still a visit
        $type = (string) $response->headers->get('Content-Type');

        return $response->getStatusCode() === 304 || str_starts_with($type, 'text/html') || str_starts_with($type, 'application/pdf');
    }

    /**
     * @param  array{device: ?string, os: ?string, os_version: ?string, browser: ?string, browser_version: ?string}|null  $client
     * @return StatVisit|null the recorded visit, or null if it does not count
     */
    public function record(Request $request, ?array $client = null): ?StatVisit
    {
        $page = $this->pages->resolve($request->getPathInfo());
        $userAgent = (string) $request->userAgent();
        $ip = (string) $request->ip();

        if ($page === null || $userAgent === '' || $ip === '') {
            return null;
        }

        if ($client === null) {
            $detected = $this->detect($userAgent, $request->headers->all());

            if ($detected['bot'] !== null) {
                return null;
            }

            $client = $detected['client'];
        }

        if ($this->adminSession->isSignedIn($request)) {
            return null;
        }

        $geo = $this->geoIp->lookup($ip);

        if ($this->exclusions->isExcluded($ip, $geo['asn'])) {
            return null;
        }

        $referrer = $this->referrer($request);
        $utm = [
            'utm_source' => $this->param($request, 'utm_source', 100),
            'utm_medium' => $this->param($request, 'utm_medium', 100),
            'utm_campaign' => $this->param($request, 'utm_campaign', 150),
            'utm_term' => $this->param($request, 'utm_term', 150),
            'utm_content' => $this->param($request, 'utm_content', 150),
        ];
        $clickSource = $this->clickSource($request);
        $local = now($this->settings->timezone());

        $visit = StatVisit::query()->create([
            'visited_at' => now(),
            // the day of the visit in the time zone of the site (a visit at 00:30 in Rome is not "yesterday")
            'day' => $local->toDateString(),
            'hour' => $local->hour,
            'page' => $page['page'],
            'path' => Str::limit($request->getPathInfo(), 250, ''),
            'locale' => $page['locale'],
            'referrer_host' => $referrer,
            ...$utm,
            'click_source' => $clickSource,
            'channel' => Channels::of($referrer, $utm['utm_source'], $utm['utm_medium'], $utm['utm_campaign'], $clickSource),
            ...$client,
            'country' => $geo['country'],
            'region' => $geo['region'] !== null ? Str::limit($geo['region'], 100, '') : null,
            'city' => $geo['city'] !== null ? Str::limit($geo['city'], 100, '') : null,
            'latitude' => $geo['latitude'],
            'longitude' => $geo['longitude'],
            'asn' => $geo['asn'],
            'as_org' => $geo['as_org'] !== null ? Str::limit($geo['as_org'], 150, '') : null,
            'ip' => Ip::truncate($ip),
            ...$this->visitors->identify($ip, $userAgent),
        ]);

        // hashes older than 24 hours go away even without a cron (at most once an hour)
        $this->visitors->prune();

        return $visit;
    }

    /**
     * Request for an address of the site that does not exist (after the redirects): counted by path.
     */
    public function recordNotFound(Request $request): void
    {
        if (! $this->isEnabled() || ! in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return;
        }

        $userAgent = (string) $request->userAgent();
        $ip = (string) $request->ip();
        $bot = $userAgent === '' || $this->detect($userAgent, $request->headers->all())['bot'] !== null;

        if (! $bot && ($this->adminSession->isSignedIn($request) || ($ip !== '' && $this->exclusions->isExcluded($ip, null)))) {
            return;
        }

        $path = mb_substr($request->getPathInfo(), 0, 255);
        $referrer = $this->referrer($request);
        $now = now();

        $update = ['hits' => DB::raw('hits + 1'), 'human_hits' => DB::raw('human_hits + '.($bot ? 0 : 1)), 'last_seen_at' => $now];

        if ($referrer !== null) {
            $update['last_referrer_host'] = $referrer;
        }

        DB::table('stat_not_found')->upsert([[
            'path' => $path, 'hits' => 1, 'human_hits' => $bot ? 0 : 1, 'probe' => self::isProbe($path),
            'last_referrer_host' => $referrer, 'first_seen_at' => $now, 'last_seen_at' => $now,
        ]], ['path'], $update);
    }

    /**
     * Path typical of the automatic attempts to find weak points (WordPress, configuration files, backups...): the
     * site has no such addresses.
     */
    public static function isProbe(string $path): bool
    {
        return preg_match('#(\.(php\d?|phtml|asp|aspx|jsp|cgi|pl|env|ini|sql|bak|old|swp|zip|tar|gz|tgz|rar|7z|ya?ml|log|sh|git|svn|DS_Store)(/|$)|/\.(?!well-known/)|wp-|xmlrpc|phpmyadmin|cgi-bin|/vendor/|/boaform|/HNAP1|/actuator|/solr/|/owa/|/autodiscover|/telescope|/_ignition|/server-status)#i', $path) === 1;
    }

    private function recordBot(string $name, string $category): void
    {
        DB::table('stat_bots')->upsert([[
            'day' => now($this->settings->timezone())->toDateString(), 'name' => Str::limit($name, 100, ''), 'category' => $category, 'hits' => 1,
        ]], ['day', 'name'], ['hits' => DB::raw('hits + 1')]);
    }

    /**
     * Bot (name and category) or device, operating system and browser of a person.
     *
     * @param  array<string, list<string|null>>  $headers
     * @return array{bot: array{name: string, category: string}|null, client: array{device: ?string, os: ?string, os_version: ?string, browser: ?string, browser_version: ?string}}
     */
    private function detect(string $userAgent, array $headers): array
    {
        $client = ['device' => null, 'os' => null, 'os_version' => null, 'browser' => null, 'browser_version' => null];

        if (preg_match(self::AI_BOTS, $userAgent, $match) === 1) {
            return ['bot' => ['name' => $match[1], 'category' => 'ai'], 'client' => $client];
        }

        AbstractDeviceParser::setVersionTruncation(AbstractDeviceParser::VERSION_TRUNCATION_MAJOR);

        $server = [];

        foreach ($headers as $name => $values) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = (string) ($values[0] ?? '');
        }

        $detector = new DeviceDetector($userAgent, ClientHints::factory($server));
        // Regular expressions already read stay in Laravel's cache (on file): no YAML parsing on every visit
        $detector->setCache(new PSR16Bridge(Cache::store()));
        $detector->parse();

        if ($detector->isBot()) {
            $bot = $detector->getBot();
            $name = is_array($bot) && is_string($bot['name'] ?? null) && $bot['name'] !== '' ? $bot['name'] : 'Bot';
            $category = is_array($bot) && is_string($bot['category'] ?? null) ? (self::BOT_CATEGORIES[$bot['category']] ?? 'other') : 'other';

            return ['bot' => ['name' => $name, 'category' => $category], 'client' => $client];
        }

        // Programs and libraries (curl, wget, scripts) and feed readers: they are not people reading
        $type = $detector->getClient('type');

        if (in_array($type, ['library', 'feed reader'], true)) {
            $name = $detector->getClient('name');

            return ['bot' => ['name' => is_string($name) && $name !== '' ? $name : 'Program', 'category' => $type === 'library' ? 'tool' : 'feed'], 'client' => $client];
        }

        $os = $detector->getOs('name');
        $osVersion = $detector->getOs('version');
        $browser = $detector->getClient('name');
        $version = $detector->getClient('version');
        $device = $detector->getDeviceName();

        return ['bot' => null, 'client' => [
            'device' => $device !== '' ? $device : null,
            'os' => is_string($os) && $os !== '' && $os !== 'UNK' ? Str::limit($os, 50, '') : null,
            'os_version' => is_string($osVersion) && $osVersion !== '' && $osVersion !== 'UNK' ? Str::limit($osVersion, 20, '') : null,
            'browser' => is_string($browser) && $browser !== '' && $browser !== 'UNK' ? Str::limit($browser, 50, '') : null,
            'browser_version' => is_string($version) && $version !== '' ? Str::limit($version, 20, '') : null,
        ]];
    }

    /**
     * Referring site, only if external (internal navigation is not a referrer).
     */
    private function referrer(Request $request): ?string
    {
        $host = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = strtolower((string) preg_replace('/^www\./i', '', $host));
        $own = strtolower((string) preg_replace('/^www\./i', '', $request->getHost()));
        $configured = strtolower((string) preg_replace('/^www\./i', '', (string) parse_url((string) config('app.url'), PHP_URL_HOST)));

        return $host === $own || $host === $configured ? null : Str::limit($host, 150, '');
    }

    private function param(Request $request, string $name, int $limit): ?string
    {
        $value = $request->query($name);

        return is_string($value) && trim($value) !== '' ? Str::limit(trim(strip_tags($value)), $limit, '') : null;
    }

    private function clickSource(Request $request): ?string
    {
        foreach (self::CLICK_IDS as $parameter => $source) {
            if ($request->query->has($parameter)) {
                return $source;
            }
        }

        return null;
    }
}
