<?php

declare(strict_types=1);

namespace App\Support\Languages;

use Locale;

/**
 * Language pack inclusi nel progetto: lang/{codice}/routes.php e lang/{codice}/site.php.
 */
class LanguagePacks
{
    /** Chiavi dei segmenti URL tradotti. */
    public const array SEGMENTS = ['projects', 'experience', 'blog', 'cv', 'privacy'];

    /**
     * Codici dei language pack disponibili (cartelle con routes.php).
     *
     * @return list<string>
     */
    public function available(): array
    {
        $codes = [];

        foreach (glob(lang_path('*'), GLOB_ONLYDIR) ?: [] as $dir) {
            if (is_file($dir.'/routes.php')) {
                $codes[] = basename($dir);
            }
        }

        sort($codes);

        return $codes;
    }

    public function exists(string $code): bool
    {
        return in_array($code, $this->available(), true);
    }

    public function hasSiteStrings(string $code): bool
    {
        return is_file(lang_path("{$code}/site.php"));
    }

    /**
     * Segmenti URL di default del pack (vuoto se il pack non esiste).
     *
     * @return array<string, string>
     */
    public function segments(string $code): array
    {
        $file = lang_path("{$code}/routes.php");

        if (! is_file($file)) {
            return [];
        }

        /** @var array<string, string> $routes */
        $routes = require $file;

        return $routes;
    }

    /**
     * Nome della lingua nella lingua indicata (da intl), es. "inglese (Regno Unito)".
     */
    /**
     * Nome della lingua nella lingua stessa, senza paese (es. "Deutsch", "Italiano"):
     * è il valore proposto per il selettore lingua del sito, modificabile dall'admin.
     */
    public function nativeName(string $code): string
    {
        $name = Locale::getDisplayLanguage($code, $code);

        return $name !== false ? mb_strtoupper(mb_substr($name, 0, 1)).mb_substr($name, 1) : $code;
    }

    public function displayName(string $code, ?string $inLocale = null): string
    {
        $name = Locale::getDisplayName($code, $inLocale ?? $code);

        return $name !== false ? mb_convert_case($name, MB_CASE_TITLE_SIMPLE) : $code;
    }

    /**
     * Elenco per le select dell'admin: codice => "Nome nativo (codice)".
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        $options = [];

        foreach ($this->available() as $code) {
            $options[$code] = $this->displayName($code).' ('.$code.')';
        }

        return $options;
    }
}
