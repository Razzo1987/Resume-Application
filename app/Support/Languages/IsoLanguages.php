<?php

declare(strict_types=1);

namespace App\Support\Languages;

use Locale;

/**
 * Languages (ISO 639-1) for the "Lingue conosciute" section and for the language of publications.
 * The names come from intl in the interface language.
 */
class IsoLanguages
{
    /** @var list<string> */
    public const array CODES = [
        'ar', 'bg', 'ca', 'cs', 'da', 'de', 'el', 'en', 'es', 'et', 'eu', 'fa', 'fi', 'fr', 'ga', 'gl',
        'he', 'hi', 'hr', 'hu', 'id', 'is', 'it', 'ja', 'ko', 'la', 'lt', 'lv', 'mt', 'nl', 'no', 'pl',
        'pt', 'ro', 'ru', 'sk', 'sl', 'sq', 'sr', 'sv', 'sw', 'th', 'tr', 'uk', 'ur', 'vi', 'zh',
    ];

    /**
     * @return array<string, string> code => name, sorted by name
     */
    public function options(?string $inLocale = null): array
    {
        $inLocale ??= app()->getLocale();
        $options = [];

        foreach (self::CODES as $code) {
            $name = Locale::getDisplayLanguage($code, $inLocale);
            $options[$code] = ($name !== false ? mb_strtoupper(mb_substr($name, 0, 1)).mb_substr($name, 1) : $code);
        }

        asort($options, SORT_LOCALE_STRING);

        return $options;
    }
}
