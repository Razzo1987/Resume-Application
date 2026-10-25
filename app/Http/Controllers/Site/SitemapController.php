<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Experience;
use App\Models\Project;
use App\Site\LastUpdated;
use App\Site\SiteUrls;
use App\Support\Languages\Locales;
use Illuminate\Http\Response;

/**
 * sitemap.xml: CV e pagine dedicate in tutte le lingue attive, con le versioni alternative (hreflang).
 */
class SitemapController extends Controller
{
    public function __invoke(SiteUrls $urls, Locales $locales, LastUpdated $lastUpdated): Response
    {
        $entries = [];
        $default = $locales->default();

        // Pagina CV
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

        $xml = view('sitemap', ['entries' => $entries, 'default' => $default])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
