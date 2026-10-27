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
 * Registra le visite al sito pubblico, senza cookie e senza leggere niente dal dispositivo: solo ciò che il browser
 * manda con ogni richiesta (indirizzo, IP, user agent, pagina di provenienza). Gira dopo la risposta.
 *
 * Si contano le pagine (risposte 200 in HTML, o 304 se il browser le aveva già) e i download del CV, non bot, anteprime (prefetch) né le visite
 * dall'IP di un amministratore. L'IP serve solo in memoria per provenienza ed esclusione, poi si salva accorciato.
 */
class Tracker
{
    /** Parametri di clic delle piattaforme: si salva solo da dove arriva il clic, mai il codice. */
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

        // Anteprime caricate in anticipo dal browser (non sono visite)
        $purpose = strtolower((string) ($request->header('Sec-Purpose') ?? $request->header('Purpose') ?? ''));

        if (str_contains($purpose, 'prefetch') || str_contains($purpose, 'prerender')) {
            return false;
        }

        // 304: la pagina era già nella cache del browser, ma è comunque una visita
        $type = (string) $response->headers->get('Content-Type');

        return $response->getStatusCode() === 304 || str_starts_with($type, 'text/html') || str_starts_with($type, 'application/pdf');
    }

    /**
     * @return StatVisit|null la visita registrata, o null se non conta
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
     * Dispositivo, sistema e browser; null se è un bot.
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
        // Espressioni regolari già lette restano nella cache di Laravel (su file): niente YAML a ogni visita
        $detector->setCache(new PSR16Bridge(Cache::store()));
        $detector->discardBotInformation();
        $detector->parse();

        // Bot, ma anche programmi e librerie (curl, wget, script) e lettori di feed: non sono persone che leggono
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
     * Sito di provenienza, solo se esterno (la navigazione interna non è una provenienza).
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
