<?php

declare(strict_types=1);

namespace App\Support\Settings;

use App\Site\Cache\PageCache;
use App\Support\Languages\Locales;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Application settings (settings table, key → JSON value) with default values.
 * Translated values are arrays {"it-IT": "...", "en-GB": "..."}.
 */
class Settings
{
    private const string CACHE_KEY = 'resume.settings';

    /**
     * Default values. The keys listed in TRANSLATABLE hold per-language texts.
     *
     * @var array<string, mixed>
     */
    public const array DEFAULTS = [
        'site.theme' => null,                         // null = theme from the configuration (RESUME_THEME)
        'cv.filename_pattern' => 'CV {name} - {lang}.pdf',
        'cv.fallback_to_default' => false,           // language without a CV → offer the default language's CV
        'seo.title' => [],
        'seo.description' => [],
        'seo.og_image' => null,
        'site.icon' => null,                          // site icon (favicon), square PNG
        'footer.gdpr_text' => [],
        'footer.show_last_updated' => false,         // "Aggiornato a…" in the footer: off by default
        'print.enabled' => true,                      // print version + "Stampa" button
        'privacy.show_email' => true,                 // contact details visible on the page (with cloaking)
        'privacy.show_phone' => true,
        'privacy.show_birthdate' => false,
        'jsonld.include_email' => false,              // personal data in the JSON-LD: off by default
        'jsonld.include_phone' => false,
        'jsonld.include_birthdate' => false,
        'updates.auto' => 'download',                 // automatic updates via the cron: off, download, patch
        'privacy.page' => false,                      // Privacy page and footer link (always on when statistics are on)
        'privacy.text' => [],                         // privacy notice text per language (empty = template text)
        'stats.enabled' => false,                     // visit statistics (without cookies)
        'stats.retention_months' => 12,               // after how many months the data of single visits is deleted
        'stats.admin_ip_days' => 14,                  // for how many days the admin's IP is not counted in the visits
        'site.timezone' => null,                      // time zone of the site (null = guessed from the default language)
    ];

    /** Time zone guessed from the country of the default language, until one is chosen in Settings */
    private const array COUNTRY_TIMEZONES = [
        'IT' => 'Europe/Rome', 'GB' => 'Europe/London', 'IE' => 'Europe/Dublin', 'US' => 'America/New_York',
        'DE' => 'Europe/Berlin', 'AT' => 'Europe/Vienna', 'CH' => 'Europe/Zurich', 'FR' => 'Europe/Paris',
        'BE' => 'Europe/Brussels', 'ES' => 'Europe/Madrid', 'PT' => 'Europe/Lisbon', 'BR' => 'America/Sao_Paulo',
        'NL' => 'Europe/Amsterdam',
    ];

    /** @var list<string> */
    public const array TRANSLATABLE = ['seo.title', 'seo.description', 'footer.gdpr_text', 'privacy.text'];

    /** @var array<string, mixed>|null */
    private ?array $values = null;

    public function __construct(private readonly Locales $locales) {}

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values ??= array_replace(self::DEFAULTS, $this->load());
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key, false);
    }

    /**
     * Time zone of the site: days and times of the visit statistics, dates and times shown and entered in the admin,
     * publication dates of the articles. Data stays stored in UTC.
     */
    public function timezone(): string
    {
        $configured = $this->get('site.timezone');

        if (is_string($configured) && in_array($configured, timezone_identifiers_list(), true)) {
            return $configured;
        }

        $country = strtoupper(substr($this->locales->default(), -2));

        return self::COUNTRY_TIMEZONES[$country] ?? (string) config('app.timezone', 'UTC');
    }

    /**
     * Translated text, falling back to the default language.
     */
    public function translated(string $key, ?string $locale = null): ?string
    {
        $values = $this->get($key, []);

        if (! is_array($values)) {
            return is_string($values) ? $values : null;
        }

        $locale ??= app()->getLocale();

        foreach ([$locale, $this->locales->default()] as $code) {
            if (is_string($values[$code] ?? null) && trim($values[$code]) !== '') {
                return $values[$code];
            }
        }

        return null;
    }

    /**
     * Settings texts written in at least one language but missing in the given one.
     *
     * @return list<string> keys, e.g. "seo.title"
     */
    public function missingTranslations(string $locale): array
    {
        $missing = [];

        foreach (self::TRANSLATABLE as $key) {
            $values = $this->get($key, []);
            $filled = is_array($values) ? array_filter($values, fn (mixed $value): bool => is_string($value) && trim($value) !== '') : [];

            if ($filled !== [] && ! isset($filled[$locale])) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    public function set(string $key, mixed $value): void
    {
        $this->setMany([$key => $value]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        $now = now();

        foreach ($values as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'updated_at' => $now, 'created_at' => $now],
            );
        }

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->values = null;

        // Settings change the public pages
        app(PageCache::class)->flush();
    }

    /**
     * @return array<string, mixed>
     */
    private function load(): array
    {
        try {
            if (! Schema::hasTable('settings')) {
                return [];
            }

            /** @var array<string, mixed> */
            return Cache::rememberForever(self::CACHE_KEY, fn (): array => DB::table('settings')
                ->pluck('value', 'key')
                ->map(fn (?string $json): mixed => $json === null ? null : json_decode($json, true))
                ->all());
        } catch (Throwable) {
            return [];
        }
    }
}
