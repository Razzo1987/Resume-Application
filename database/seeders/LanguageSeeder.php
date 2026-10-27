<?php

namespace Database\Seeders;

use App\Models\Language;
use App\Support\Languages\LanguagePacks;
use App\Support\Languages\Locales;
use Illuminate\Database\Seeder;

/**
 * Default language (APP_LOCALE) and fallback language (APP_FALLBACK_LOCALE), if they do not exist yet.
 */
class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $default = (string) config('app.locale', 'it-IT');
        $fallback = (string) config('app.fallback_locale', 'en-GB');

        foreach (array_values(array_unique([$default, $fallback])) as $position => $code) {
            Language::query()->firstOrCreate(['code' => $code], [
                'name' => app(LanguagePacks::class)->nativeName($code),
                'is_default' => $code === $default && ! Language::query()->where('is_default', true)->exists(),
                'is_active' => true,
                'sort_order' => ($position + 1) * 10,
            ]);
        }

        app(Locales::class)->flush();
    }
}
