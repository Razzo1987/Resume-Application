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
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records visits to the public site, without cookies and without reading anything from the device: only what the
 * browser sends with every request (URL, IP, user agent, referring page). Runs after the response.
 *
 * Pages (200 HTML responses, or 304 if the browser already had them) and CV downloads are counted; bots, previews
 * (prefetch) and visits from an administrator's IP are not. The IP is only kept in memory for the origin and the
 * exclusion, then stored truncated.
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

    public function __construct(
        private readonly Settings $settings,
        private readonly PageResolver $pages,
        private readonly GeoIp $geoIp,
        private readonly AdminIps $adminIps,
    ) {}

    public function isEnabled(): bool
    {
        return $this->settings->bool('stats.enabled');
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
     * @return StatVisit|null the recorded visit, or null if it does not count
     */
    public function record(Request $request): ?StatVisit
    {
        $page = $this->pages->resolve($request->getPathInfo());
        $userAgent = (string) $request->userAgent();
        $ip = (string) $request->ip();

        if ($page === null || $userAgent === '' || $ip === '' || $this->adminIps->isExcluded($ip)) {
            return null;
        }

        $client = $this->client($userAgent, $request->headers->all());

        if ($client === null) {
            return null; // bot
        }

        $geo = $this->geoIp->lookup($ip);

        return StatVisit::query()->create([
            'visited_at' => now(),
            // the day of the visit in the time zone of the site (a visit at 00:30 in Rome is not "yesterday")
            'day' => now($this->settings->timezone())->toDateString(),
            'page' => $page['page'],
            'path' => Str::limit($request->getPathInfo(), 250, ''),
            'locale' => $page['locale'],
            'referrer_host' => $this->referrer($request),
            'utm_source' => $this->param($request, 'utm_source', 100),
            'utm_medium' => $this->param($request, 'utm_medium', 100),
            'utm_campaign' => $this->param($request, 'utm_campaign', 150),
            'utm_term' => $this->param($request, 'utm_term', 150),
            'utm_content' => $this->param($request, 'utm_content', 150),
            'click_source' => $this->clickSource($request),
            ...$client,
            'country' => $geo['country'],
            'region' => $geo['region'] !== null ? Str::limit($geo['region'], 100, '') : null,
            'city' => $geo['city'] !== null ? Str::limit($geo['city'], 100, '') : null,
            'latitude' => $geo['latitude'],
            'longitude' => $geo['longitude'],
            'asn' => $geo['asn'],
            'as_org' => $geo['as_org'] !== null ? Str::limit($geo['as_org'], 150, '') : null,
            'ip' => Ip::truncate($ip),
        ]);
    }

    /**
     * Device, operating system and browser; null for a bot.
     *
     * @param  array<string, list<string|null>>  $headers
     * @return array{device: ?string, os: ?string, browser: ?string, browser_version: ?string}|null
     */
    private function client(string $userAgent, array $headers): ?array
    {
        AbstractDeviceParser::setVersionTruncation(AbstractDeviceParser::VERSION_TRUNCATION_MAJOR);

        $server = [];

        foreach ($headers as $name => $values) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = (string) ($values[0] ?? '');
        }

        $detector = new DeviceDetector($userAgent, ClientHints::factory($server));
        // Regular expressions already read stay in Laravel's cache (on file): no YAML parsing on every visit
        $detector->setCache(new PSR16Bridge(Cache::store()));
        $detector->discardBotInformation();
        $detector->parse();

        // Bots, but also programs and libraries (curl, wget, scripts) and feed readers: they are not people reading
        if ($detector->isBot() || in_array($detector->getClient('type'), ['library', 'feed reader'], true)) {
            return null;
        }

        $os = $detector->getOs('name');
        $browser = $detector->getClient('name');
        $version = $detector->getClient('version');
        $device = $detector->getDeviceName();

        return [
            'device' => $device !== '' ? $device : null,
            'os' => is_string($os) && $os !== '' && $os !== 'UNK' ? Str::limit($os, 50, '') : null,
            'browser' => is_string($browser) && $browser !== '' && $browser !== 'UNK' ? Str::limit($browser, 50, '') : null,
            'browser_version' => is_string($version) && $version !== '' ? Str::limit($version, 20, '') : null,
        ];
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
