<?php

declare(strict_types=1);

namespace App\Support\Languages;

use App\Models\User;
use Locale;

/**
 * Lingue dell'interfaccia admin: quelle con un file lang/{codice}/admin.php (oggi it ed en). Ogni amministratore
 * sceglie la sua nel profilo; senza scelta (e nella pagina di accesso) vale RESUME_ADMIN_LOCALE.
 */
final class AdminLocales
{
    /**
     * @return array<string, string> codice => nome nella sua lingua
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
        // getAttributes(): niente eccezione se il modello è stato caricato senza questa colonna
        $chosen = $user?->getAttributes()['locale'] ?? null;

        return is_string($chosen) && array_key_exists($chosen, $this->available()) ? $chosen : $this->default();
    }
}
