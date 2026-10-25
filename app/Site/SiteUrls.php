<?php

declare(strict_types=1);

namespace App\Site;

use App\Models\Experience;
use App\Models\Project;
use App\Support\Languages\LanguagePacks;
use App\Support\Languages\Locales;

/**
 * Indirizzi del sito pubblico. I percorsi sono relativi alla radice del sito ("/it-IT/progetti/slug");
 * url() li rende assoluti (in produzione sempre sul dominio di APP_URL).
 */
final class SiteUrls
{
    public function __construct(
        private readonly Locales $locales,
        private readonly LanguagePacks $packs,
    ) {}

    /**
     * Segmento tradotto di una lingua (es. "projects" in it-IT → "progetti").
     */
    public function segment(string $locale, string $key): string
    {
        $language = $this->locales->find($locale);

        if ($language !== null) {
            return $language->segment($key);
        }

        return $this->packs->segments($locale)[$key] ?? $this->packs->segments('en-GB')[$key] ?? $key;
    }

    /**
     * Chiave del segmento (projects, experience, blog, cv) a cui corrisponde una parola dell'URL.
     * Si accettano anche i segmenti delle altre lingue attive: il controller poi reindirizza all'URL corretto.
     */
    public function segmentKey(string $locale, string $segment): ?string
    {
        $segment = strtolower($segment);

        foreach ([$locale, ...$this->locales->active()] as $code) {
            foreach (LanguagePacks::SEGMENTS as $key) {
                if ($this->segment($code, $key) === $segment) {
                    return $key;
                }
            }
        }

        return null;
    }

    public function homePath(string $locale): string
    {
        return '/'.$locale;
    }

    public function sectionPath(string $locale, string $anchor): string
    {
        return '/'.$locale.'#'.$anchor;
    }

    public function cvDownloadPath(string $locale): string
    {
        return '/'.$locale.'/'.$this->segment($locale, 'cv');
    }

    public function projectPath(Project $project, string $locale): ?string
    {
        return $this->detailPath('projects', $project->t('slug', $locale, false), $locale);
    }

    public function experiencePath(Experience $experience, string $locale): ?string
    {
        return $this->detailPath('experience', $experience->t('slug', $locale, false), $locale);
    }

    /**
     * Percorso della pagina dedicata di un contenuto, se il tipo ne ha una.
     */
    public function pathFor(object $model, string $locale): ?string
    {
        return match (true) {
            $model instanceof Project => $this->projectPath($model, $locale),
            $model instanceof Experience => $this->experiencePath($model, $locale),
            default => null,
        };
    }

    /**
     * Versioni linguistiche di una pagina: codice lingua → URL assoluto (lingua di default per prima).
     *
     * @param  callable(string): ?string  $path
     * @return array<string, string>
     */
    public function alternates(callable $path): array
    {
        $alternates = [];

        foreach ($this->locales->active() as $code) {
            $localized = $path($code);

            if ($localized !== null) {
                $alternates[$code] = url($localized);
            }
        }

        return $alternates;
    }

    public function detailPath(string $key, ?string $slug, string $locale): ?string
    {
        return $slug !== null && $slug !== ''
            ? '/'.$locale.'/'.$this->segment($locale, $key).'/'.$slug
            : null;
    }
}
