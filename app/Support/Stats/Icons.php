<?php

declare(strict_types=1);

namespace App\Support\Stats;

use Illuminate\Support\HtmlString;

/**
 * Small icons of the Statistics page (Tabler Icons, MIT License, in resources/stats/icons.json built by
 * scripts/build-stats-icons.mjs): operating systems, browsers, devices, channels, referring sites, bots and networks.
 * Decorative only (aria-hidden): the name is always written next to them.
 */
class Icons
{
    private const array OS = [
        'Windows' => 'brand-windows', 'Mac' => 'brand-apple', 'macOS' => 'brand-apple', 'iOS' => 'brand-apple', 'iPadOS' => 'brand-apple',
        'Android' => 'brand-android', 'Ubuntu' => 'brand-ubuntu', 'Debian' => 'brand-debian', 'Red Hat' => 'brand-redhat',
        'Fedora' => 'brand-redhat', 'GNU/Linux' => 'terminal-2', 'Linux' => 'terminal-2', 'Chrome OS' => 'brand-chrome',
        'Windows Phone' => 'brand-windows',
    ];

    /** Browser names start with these words (Chrome Mobile, Firefox iOS, Mobile Safari...) */
    private const array BROWSERS = [
        'Microsoft Edge' => 'brand-edge', 'Chrome' => 'brand-chrome', 'Chromium' => 'brand-chrome', 'Firefox' => 'brand-firefox',
        'Mobile Safari' => 'brand-safari', 'Safari' => 'brand-safari', 'Opera' => 'brand-opera', 'Vivaldi' => 'brand-vivaldi',
        'Arc' => 'brand-arc', 'Yandex' => 'brand-yandex', 'Facebook' => 'brand-facebook', 'Instagram' => 'brand-instagram',
        'LinkedIn' => 'brand-linkedin', 'Google Search App' => 'brand-google', 'Edge' => 'brand-edge',
    ];

    private const array DEVICES = [
        'desktop' => 'device-desktop', 'smartphone' => 'device-mobile', 'phablet' => 'device-mobile', 'tablet' => 'device-tablet',
        'tv' => 'device-tv', 'console' => 'device-gamepad-2', 'car browser' => 'car', 'wearable' => 'device-watch',
    ];

    private const array CHANNELS = [
        'direct' => 'pointer', 'search' => 'search', 'social' => 'users', 'ai' => 'sparkles', 'email' => 'mail',
        'sites' => 'world-www', 'campaign' => 'speakerphone',
    ];

    private const array BOTS = [
        'search' => 'search', 'ai' => 'sparkles', 'crawler' => 'spider', 'monitor' => 'heartbeat', 'social' => 'share',
        'feed' => 'rss', 'tool' => 'terminal-2', 'other' => 'robot',
    ];

    /** Referring sites: domain => icon */
    private const array SITES = [
        'linkedin.com' => 'brand-linkedin', 'lnkd.in' => 'brand-linkedin', 'google.' => 'brand-google', 'bing.com' => 'brand-bing',
        'github.com' => 'brand-github', 'x.com' => 'brand-x', 't.co' => 'brand-x', 'twitter.com' => 'brand-x',
        'facebook.com' => 'brand-facebook', 'instagram.com' => 'brand-instagram', 'reddit.com' => 'brand-reddit',
        'youtube.com' => 'brand-youtube', 'chatgpt.com' => 'brand-openai', 'chat.openai.com' => 'brand-openai',
        'bsky.app' => 'brand-bluesky', 'mastodon.' => 'brand-mastodon', 't.me' => 'brand-telegram', 'whatsapp.com' => 'brand-whatsapp',
        'threads.' => 'brand-threads', 'tiktok.com' => 'brand-tiktok', 'pinterest.' => 'brand-pinterest', 'yandex.' => 'brand-yandex',
    ];

    private const array NETWORKS = [Networks::PROVIDER => 'router', Networks::HOSTING => 'server-2', Networks::ORGANIZATION => 'building'];

    /** @var array<string, string>|null */
    private ?array $icons = null;

    /**
     * Icon for a value of a ranking (null if there is none: no generic icon for unknown browsers or sites).
     */
    public function for(string $type, string $value): ?HtmlString
    {
        $name = match ($type) {
            'os', 'os_version' => self::OS[$value] ?? self::byPrefix(self::OS, $value),
            'browser', 'browser_version' => self::byPrefix(self::BROWSERS, $value) ?? 'world',
            'device' => self::DEVICES[$value] ?? null,
            'channel' => self::CHANNELS[$value] ?? null,
            'bot' => self::BOTS[$value] ?? 'robot',
            'referrer' => self::site($value),
            'network' => self::NETWORKS[$value] ?? null,
            default => null,
        };

        return $name !== null ? $this->svg($name) : null;
    }

    public function svg(string $name): ?HtmlString
    {
        $this->icons ??= (array) (json_decode((string) file_get_contents(resource_path('stats/icons.json')), true)['icons'] ?? []);

        if (! isset($this->icons[$name])) {
            return null;
        }

        return new HtmlString('<svg class="resume-stats-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'.$this->icons[$name].'</svg>');
    }

    /**
     * @param  array<string, string>  $map
     */
    private static function byPrefix(array $map, string $value): ?string
    {
        foreach ($map as $prefix => $icon) {
            if ($value === $prefix || str_starts_with($value, $prefix.' ')) {
                return $icon;
            }
        }

        return null;
    }

    private static function site(string $host): ?string
    {
        foreach (self::SITES as $domain => $icon) {
            $matches = str_ends_with($domain, '.')
                ? str_starts_with($host, $domain) || str_contains($host, '.'.$domain)
                : $host === $domain || str_ends_with($host, '.'.$domain);

            if ($matches) {
                return $icon;
            }
        }

        return null;
    }
}
