<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Experience;
use App\Models\Project;
use App\Site\Blog;
use App\Site\LastUpdated;
use App\Site\SiteUrls;
use App\Support\Languages\Locales;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * sitemap.xml: CV and dedicated pages in all active languages, with the alternate versions (hreflang).
 */
class SitemapController extends Controller
{
    public function __invoke(SiteUrls $urls, Locales $locales, LastUpdated $lastUpdated, Blog $blog): Response
    {
        $entries = [];
        $default = $locales->default();

        // CV page
        $entries[] = [
            'alternates' => $urls->alternates(fn (string $code): string => $urls->homePath($code)),
            'lastmod' => $lastUpdated->date(),
        ];

        $projects = Project::query()->visible()->chronological()->get()->filter(fn (Project $project): bool => $project->hasDetailPage());

        foreach ($projects as $project) {
            $entries[] = [
                'alternates' => $urls->alternates(fn (string $code): ?string => $urls->projectPath($project, $code)),
                'lastmod' => $project->updated_at,
            ];
        }

        $experiences = Experience::query()->visible()->chronological()
            ->withCount(['projects as visible_projects_count' => fn ($query) => $query->where('is_visible', true)])
            ->get()
            ->filter(fn (Experience $experience): bool => $experience->hasDetailPage());

        foreach ($experiences as $experience) {
            $entries[] = [
                'alternates' => $urls->alternates(fn (string $code): ?string => $urls->experiencePath($experience, $code)),
                'lastmod' => $experience->updated_at,
            ];
        }

        // Blog: list in the languages that have articles, then each article in the languages it is written in
        $blogAlternates = $urls->alternates(fn (string $code): ?string => $blog->hasArticles($code) ? $urls->blogPath($code) : null);

        if ($blogAlternates !== []) {
            $entries[] = ['alternates' => $blogAlternates, 'lastmod' => Article::query()->published()->max('published_at') !== null ? Carbon::parse((string) Article::query()->published()->max('published_at')) : null];
        }

        foreach (Article::query()->published()->orderByDesc('published_at')->get() as $article) {
            $alternates = $urls->alternates(fn (string $code): ?string => $urls->articlePath($article, $code));

            if ($alternates !== []) {
                $entries[] = ['alternates' => $alternates, 'lastmod' => $article->updated_at];
            }
        }

        $xml = view('sitemap', ['entries' => $entries, 'default' => $default])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
