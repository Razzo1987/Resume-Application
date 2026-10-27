<?php

declare(strict_types=1);

namespace App\Site;

use App\Models\Article;
use App\Models\Experience;
use App\Models\Project;
use App\Support\Languages\LanguagePacks;
use App\Support\Languages\Locales;

/**
 * Addresses of the public site. Paths are relative to the site root ("/it-IT/progetti/slug");
 * url() makes them absolute (in production always on the APP_URL domain).
 */
final class SiteUrls
{
    public function __construct(
        private readonly Locales $locales,
        private readonly LanguagePacks $packs,
    ) {}

    /**
     * Translated segment of a language (e.g. "projects" in it-IT → "progetti").
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
     * Key of the segment (projects, experience, blog, cv, privacy) that a word of the URL corresponds to.
     * Segments of the other active languages are accepted too: the controller then redirects to the correct URL.
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

    public function blogPath(string $locale): string
    {
        return '/'.$locale.'/'.$this->segment($locale, 'blog');
    }

    public function privacyPath(string $locale): string
    {
        return '/'.$locale.'/'.$this->segment($locale, 'privacy');
    }

    public function feedPath(string $locale): string
    {
        return '/'.$locale.'/feed.xml';
    }

    public function articlePath(Article $article, string $locale): ?string
    {
        if (! $article->isAvailableIn($locale)) {
            return null;
        }

        return $this->detailPath('blog', $article->t('slug', $locale, false), $locale);
    }

    public function experiencePath(Experience $experience, string $locale): ?string
    {
        return $this->detailPath('experience', $experience->t('slug', $locale, false), $locale);
    }

    /**
     * Path of the dedicated page of a content item, if its type has one.
     */
    public function pathFor(object $model, string $locale): ?string
    {
        return match (true) {
            $model instanceof Project => $this->projectPath($model, $locale),
            $model instanceof Experience => $this->experiencePath($model, $locale),
            $model instanceof Article => $this->articlePath($model, $locale),
            default => null,
        };
    }

    /**
     * Language versions of a page: language code → absolute URL (default language first).
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
