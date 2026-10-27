<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\ArticleStatus;
use App\Enums\ExperienceKind;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Experiences\ExperienceResource;
use App\Filament\Resources\PageSections\PageSectionResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Article;
use App\Models\Experience;
use App\Models\PageSection;
use App\Models\Project;
use App\Site\CvData;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dashboard, gruppo Contenuti: esperienze, progetti con pagina dedicata, articoli, sezioni del CV nascoste perché vuote.
 */
class ContentOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected function getHeading(): ?string
    {
        return __('admin.dashboard.content');
    }

    protected function getStats(): array
    {
        $work = Experience::query()->where('kind', ExperienceKind::Work)->count();
        $volunteering = Experience::query()->where('kind', ExperienceKind::Volunteering)->count();
        $projects = Project::query()->get();
        $withPage = $projects->filter(fn (Project $project): bool => $project->hasDetailPage())->count();
        $published = Article::query()->published()->count();
        $scheduled = Article::query()->where('status', ArticleStatus::Published)->where('published_at', '>', now())->count();
        $drafts = Article::query()->where('status', ArticleStatus::Draft)->count();
        $enabled = PageSection::query()->enabled()->count();
        $shown = rescue(fn (): int => app(CvData::class)->sections()->count(), $enabled, report: false);
        $empty = max(0, $enabled - $shown);

        return [
            Stat::make(__('admin.dashboard.experiences'), (string) $work)
                ->description(__('admin.dashboard.volunteering', ['count' => $volunteering]))
                ->url(ExperienceResource::getUrl()),
            Stat::make(__('admin.dashboard.projects'), (string) $projects->count())
                ->description(__('admin.dashboard.projects_with_page', ['count' => $withPage]))
                ->url(ProjectResource::getUrl()),
            Stat::make(__('admin.dashboard.articles'), (string) $published)
                ->description(__('admin.dashboard.articles_detail', ['drafts' => $drafts, 'scheduled' => $scheduled]))
                ->url(ArticleResource::getUrl()),
            Stat::make(__('admin.dashboard.sections'), $shown.'/'.$enabled)
                ->description($empty > 0 ? __('admin.dashboard.sections_empty', ['count' => $empty]) : __('admin.dashboard.sections_ok'))
                ->color($empty > 0 ? 'warning' : 'gray')
                ->url(PageSectionResource::getUrl()),
        ];
    }
}
