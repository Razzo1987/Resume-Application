<?php

declare(strict_types=1);

namespace App\Site;

use App\Models\Experience;
use App\Models\Language;
use App\Models\Project;
use App\Support\Languages\LanguagePacks;
use Illuminate\Database\Eloquent\Model;

/**
 * Quando cambia un indirizzo pubblico, quello vecchio continua a funzionare con un 301:
 * - slug di un progetto o di un'esperienza modificato in admin;
 * - segmento URL di una lingua modificato (vale per tutti gli indirizzi sotto di esso).
 */
final class UrlChangeRedirects
{
    public function __construct(
        private readonly Redirects $redirects,
        private readonly SiteUrls $urls,
        private readonly LanguagePacks $packs,
    ) {}

    public function slugsChanged(Model $model): void
    {
        $key = match (true) {
            $model instanceof Project => 'projects',
            $model instanceof Experience => 'experience',
            default => null,
        };

        if ($key === null || ! $model->wasChanged('slug')) {
            return;
        }

        $before = self::decode($model->getOriginal('slug'));
        $after = self::decode($model->getAttribute('slug'));

        foreach ($before as $locale => $old) {
            $new = $after[$locale] ?? null;

            if (is_string($old) && $old !== '' && is_string($new) && $new !== '' && $old !== $new) {
                $this->redirects->remember(
                    (string) $this->urls->detailPath($key, $old, (string) $locale),
                    (string) $this->urls->detailPath($key, $new, (string) $locale),
                );
            }
        }
    }

    public function segmentsChanged(Language $language): void
    {
        if (! $language->wasChanged('url_segments')) {
            return;
        }

        $before = self::decode($language->getOriginal('url_segments'));
        $pack = $this->packs->segments($language->code) + $this->packs->segments('en-GB');

        foreach (LanguagePacks::SEGMENTS as $key) {
            $old = is_string($before[$key] ?? null) && $before[$key] !== '' ? $before[$key] : ($pack[$key] ?? $key);
            $new = $language->segment($key);

            if ($old !== $new) {
                $this->redirects->remember('/'.$language->code.'/'.$old, '/'.$language->code.'/'.$new);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? $value : [];
    }
}
