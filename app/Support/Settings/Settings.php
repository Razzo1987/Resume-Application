<?php

declare(strict_types=1);

namespace App\Support\Settings;

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
        'footer.gdpr_text' => [],
        'footer.show_last_updated' => false,         // "Aggiornato a…" nel footer: spento di default
        'print.enabled' => true,                      // versione di stampa + pulsante "Stampa"
        'privacy.show_email' => true,                 // dati di contatto visibili nella pagina (con cloaking)
        'privacy.show_phone' => true,
        'privacy.show_birthdate' => false,
        'jsonld.include_email' => false,              // dati personali nel JSON-LD: spenti di default
        'jsonld.include_phone' => false,
        'jsonld.include_birthdate' => false,
    ];

    /** @var list<string> */
    public const array TRANSLATABLE = ['seo.title', 'seo.description', 'footer.gdpr_text'];

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
