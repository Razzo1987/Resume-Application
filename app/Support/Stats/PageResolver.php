<?php

declare(strict_types=1);

namespace App\Support\Stats;

use App\Models\Article;
use App\Models\Experience;
use App\Models\Project;
use App\Site\SiteUrls;
use App\Support\Languages\Locales;
use Illuminate\Database\Eloquent\Model;

/**
 * Pagina delle statistiche a partire dall'indirizzo: "cv", "blog", "privacy", "cv-download", "project:12",
 * "experience:3", "article:5". Le pagine possono arrivare dalla cache HTML, senza passare dai controller: per
 * questo si riconoscono dall'indirizzo (canonico, perché si contano solo le risposte 200).
 */
class PageResolver
{
    private const array MODELS = ['projects' => ['project', Project::class], 'experience' => ['experience', Experience::class], 'blog' => ['article', Article::class]];

    public function __construct(
        private readonly SiteUrls $urls,
        private readonly Locales $locales,
    ) {}

    /**
     * @return array{page: string, locale: string}|null
     */
    public function resolve(string $path): ?array
    {
        $parts = array_values(array_filter(explode('/', trim($path, '/')), fn (string $part): bool => $part !== ''));

        if ($parts === [] || count($parts) > 3 || preg_match('/^[A-Za-z]{2,3}-[A-Za-z]{2}$/', $parts[0]) !== 1) {
            return null;
        }

        $locale = $parts[0];

        if (count($parts) === 1) {
            return ['page' => 'cv', 'locale' => $locale];
        }

        $key = $this->urls->segmentKey($locale, $parts[1]);

        if (count($parts) === 2) {
            $page = match ($key) {
                'blog' => 'blog',
                'privacy' => 'privacy',
                'cv' => 'cv-download',
                default => null,
            };

            return $page !== null ? ['page' => $page, 'locale' => $locale] : null;
        }

        if ($key === null || ! isset(self::MODELS[$key])) {
            return null;
        }

        [$prefix, $class] = self::MODELS[$key];
        $id = $class::query()->whereTranslation('slug', $locale, $parts[2])->value('id');

        return $id !== null ? ['page' => $prefix.':'.$id, 'locale' => $locale] : null;
    }

    /**
     * Nome leggibile di una pagina, nella lingua principale del sito.
     */
    public function label(string $page): string
    {
        $locale = $this->locales->default();

        if (str_contains($page, ':')) {
            [$prefix, $id] = explode(':', $page, 2);
            $class = collect(self::MODELS)->first(fn (array $model): bool => $model[0] === $prefix)[1] ?? null;
            /** @var Model|null $model */
            $model = $class !== null ? $class::query()->when($class === Experience::class, fn ($query) => $query->with('organization'))->find((int) $id) : null;
            $title = match (true) {
                $model instanceof Project, $model instanceof Article => $model->t('title', $locale),
                $model instanceof Experience => $model->t('role', $locale).($model->organization !== null ? ' - '.$model->organization->name : ''),
                default => null,
            };

            return __('admin.stats.pages.'.$prefix).': '.($title ?? '#'.$id);
        }

        return (string) __('admin.stats.pages.'.$page);
    }

    /**
     * Pagine con almeno una visita, per il filtro: chiave => nome.
     *
     * @param  list<string>  $pages
     * @return array<string, string>
     */
    public function labels(array $pages): array
    {
        $labels = [];

        foreach ($pages as $page) {
            $labels[$page] = $this->label($page);
        }

        asort($labels, SORT_NATURAL | SORT_FLAG_CASE);

        return $labels;
    }
}
