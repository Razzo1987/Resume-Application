<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Support\Languages\Locales;
use Illuminate\Http\RedirectResponse;

/**
 * "/" porta alla lingua di default; "/it" o "/en" alla lingua attiva corrispondente.
 */
class RootController extends Controller
{
    public function __invoke(Locales $locales): RedirectResponse
    {
        // Permanente, ma memorizzato dal browser per un giorno: se l'admin cambia la lingua di default
        // i visitatori vengono reindirizzati alla nuova entro 24 ore.
        return redirect()->to(url('/'.$locales->default()), 301)
            ->header('Cache-Control', 'public, max-age=86400');
    }

    public function language(string $language, Locales $locales): RedirectResponse
    {
        $language = strtolower($language);

        $match = $locales->activeLanguages()->first(
            fn (Language $candidate): bool => strtolower(strtok($candidate->code, '-') ?: '') === $language,
        );

        abort_unless($match instanceof Language, 404);

        return redirect()->to(url('/'.$match->code), 301);
    }
}
