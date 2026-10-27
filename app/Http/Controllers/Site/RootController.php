<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Support\Languages\Locales;
use Illuminate\Http\RedirectResponse;

/**
 * "/" leads to the default language; "/it" or "/en" to the matching active language.
 */
class RootController extends Controller
{
    public function __invoke(Locales $locales): RedirectResponse
    {
        // Permanent, but cached by the browser for one day: if the admin changes the default language,
        // visitors are redirected to the new one within 24 hours.
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
