<?php

declare(strict_types=1);

namespace App\Support\Languages;

use App\Models\User;
use Locale;

/**
 * Admin interface languages: those with a lang/{code}/admin.php file (currently it and en). Each administrator
 * picks their own in the profile; without a choice (and on the login page) RESUME_ADMIN_LOCALE applies.
 */
final class AdminLocales
{
    /**
     * @return array<string, string> code => name in its own language
     */
    public function available(): array
    {
        $locales = [];

        foreach (glob(lang_path('*/admin.php')) ?: [] as $file) {
            $code = basename(dirname($file));

            if (preg_match('/^[a-z]{2}$/', $code) === 1) {
                $locales[$code] = mb_convert_case((string) (Locale::getDisplayLanguage($code, $code) ?: $code), MB_CASE_TITLE);
            }
        }

        ksort($locales);

        return $locales;
    }

    public function default(): string
    {
        $configured = (string) config('resume.admin_locale', 'it');

        return array_key_exists($configured, $this->available()) ? $configured : 'en';
    }

    public function for(?User $user): string
    {
        // getAttributes(): no exception if the model was loaded without this column
        $chosen = $user?->getAttributes()['locale'] ?? null;

        return is_string($chosen) && array_key_exists($chosen, $this->available()) ? $chosen : $this->default();
    }
}
