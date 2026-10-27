<?php

declare(strict_types=1);

namespace App\Support\Languages;

use Locale;

/**
 * Language packs included in the project: lang/{code}/routes.php and lang/{code}/site.php.
 */
class LanguagePacks
{
    /** Keys of the translated URL segments. */
    public const array SEGMENTS = ['projects', 'experience', 'blog', 'cv', 'privacy'];

    /**
     * Codes of the available language packs (folders with routes.php).
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
     * Default URL segments of the pack (empty if the pack does not exist).
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
     * Name of the language in the language itself, without country (e.g. "Deutsch", "Italiano"):
     * it is the value suggested for the site's language switcher, editable from the admin.
     */
    public function nativeName(string $code): string
    {
        $name = Locale::getDisplayLanguage($code, $code);

        return $name !== false ? mb_strtoupper(mb_substr($name, 0, 1)).mb_substr($name, 1) : $code;
    }

    /**
     * Name of the language in the given language (from intl), e.g. "inglese (Regno Unito)".
     */
    public function displayName(string $code, ?string $inLocale = null): string
    {
        $name = Locale::getDisplayName($code, $inLocale ?? $code);

        return $name !== false ? mb_convert_case($name, MB_CASE_TITLE_SIMPLE) : $code;
    }

    /**
     * List for the admin selects: code => "Native name (code)".
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
