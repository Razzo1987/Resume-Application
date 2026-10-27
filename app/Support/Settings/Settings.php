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
 * Impostazioni dell'applicazione (tabella settings, chiave → valore JSON) con valori di default.
 * I valori tradotti sono array {"it-IT": "...", "en-GB": "..."}.
 */
class Settings
{
    private const string CACHE_KEY = 'resume.settings';

    /**
     * Valori di default. Le chiavi elencate in TRANSLATABLE contengono testi per lingua.
     *
     * @var array<string, mixed>
     */
    public const array DEFAULTS = [
        'site.theme' => null,                         // null = tema da configurazione (RESUME_THEME)
        'cv.filename_pattern' => 'CV {name} - {lang}.pdf',
        'cv.fallback_to_default' => false,           // lingua senza CV → offri il CV della lingua di default
        'seo.title' => [],
        'seo.description' => [],
        'seo.og_image' => null,
        'site.icon' => null,                          // icona del sito (favicon), PNG quadrato
        'footer.gdpr_text' => [],
        'footer.show_last_updated' => false,         // "Aggiornato a…" nel footer: spento di default
        'print.enabled' => true,                      // versione di stampa + pulsante "Stampa"
        'privacy.show_email' => true,                 // dati di contatto visibili nella pagina (con cloaking)
        'privacy.show_phone' => true,
        'privacy.show_birthdate' => false,
        'jsonld.include_email' => false,              // dati personali nel JSON-LD: spenti di default
        'jsonld.include_phone' => false,
        'jsonld.include_birthdate' => false,
        'updates.auto' => 'download',                 // aggiornamenti automatici col cron: off, download, patch
        'privacy.page' => false,                      // pagina Privacy e link nel footer (sempre attiva con le statistiche)
        'privacy.text' => [],                         // testo dell'informativa per lingua (vuoto = testo modello)
        'stats.enabled' => false,                     // statistiche delle visite (senza cookie)
        'stats.retention_months' => 12,               // dopo quanti mesi si cancellano i dati delle singole visite
        'stats.admin_ip_days' => 14,                  // per quanti giorni l'IP usato nell'admin non conta nelle visite
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
     * Testo tradotto con fallback sulla lingua di default.
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
     * Testi delle impostazioni scritti in almeno una lingua ma mancanti in quella indicata.
     *
     * @return list<string> chiavi, es. "seo.title"
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

        // Le impostazioni cambiano le pagine pubbliche
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
