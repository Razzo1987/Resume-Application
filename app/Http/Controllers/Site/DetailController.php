<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Experience;
use App\Models\Project;
use App\Site\CvData;
use App\Site\Pages;
use App\Site\SiteUrls;
use App\Support\Languages\Locales;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Dedicated pages: "/{language}/{progetti}/{slug}" and "/{language}/{esperienze}/{slug}".
 * If the URL is not the canonical one (letter case, segment or slug of another language), it redirects.
 */
class DetailController extends Controller
{
    public function __invoke(Request $request, string $locale, string $segment, string $slug, SiteUrls $urls, Locales $locales, Pages $pages, CvData $cv): View|RedirectResponse
    {
        $key = $urls->segmentKey($locale, $segment);
        $slug = strtolower($slug);

        $record = match ($key) {
            'projects' => $this->find(Project::query()->visible()->with(['client', 'experience.organization', 'skills.category']), $slug, $locale, $locales),
            'experience' => $this->find(Experience::query()->visible()->with(['organization', 'skills.category', 'projects' => fn ($query) => $query->visible()->chronological()->with('client')]), $slug, $locale, $locales),
            'blog' => $this->find(Article::query()->published(), $slug, $locale, $locales),
            default => null,
        };

        $exists = match (true) {
            $record instanceof Project, $record instanceof Experience => $record->hasDetailPage(),
            $record instanceof Article => $record->isAvailableIn($locale),
            default => false,
        };

        abort_unless($exists, 404);

        $canonical = (string) $urls->pathFor($record, $locale);

        if ($canonical !== $request->getPathInfo()) {
            return redirect()->to(url($canonical), 301);
        }

        return match (true) {
            $record instanceof Project => view('theme::project', ['page' => $pages->project($record, $locale), 'project' => $record, 'cv' => $cv]),
            $record instanceof Experience => view('theme::experience', ['page' => $pages->experience($record, $locale), 'experience' => $record, 'cv' => $cv]),
            default => view('theme::article', ['page' => $pages->article($record, $locale), 'article' => $record, 'cv' => $cv]),
        };
    }

    /**
     * Looks up the slug first in the page language, then in the other active languages.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return TModel|null
     */
    private function find(Builder $query, string $slug, string $locale, Locales $locales): ?Model
    {
        foreach (array_unique([$locale, ...$locales->active()]) as $code) {
            $record = (clone $query)->where("slug->{$code}", $slug)->first();

            if ($record !== null) {
                return $record;
            }
        }

        return null;
    }
}
