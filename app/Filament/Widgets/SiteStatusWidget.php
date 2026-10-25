<?php

namespace App\Filament\Widgets;

use App\Models\CvDocument;
use App\Models\Experience;
use App\Models\Project;
use App\Support\Content\TranslationReport;
use App\Support\Languages\Locales;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Stato del sito: versione, contenuti, CV correnti e traduzioni da completare.
 */
class SiteStatusWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $locales = app(Locales::class);
        $languages = $locales->languages()->where('is_active', true);
        $withCv = CvDocument::query()->current()->whereIn('language_id', $languages->pluck('id'))->count();
        $version = trim((string) @file_get_contents(base_path('VERSION'))) ?: 'dev';

        $stats = [
            Stat::make(__('admin.widgets.version'), $version)
                ->description('Laravel '.app()->version().' · PHP '.PHP_VERSION),
            Stat::make(__('admin.widgets.content'), (string) (Experience::query()->count() + Project::query()->count()))
                ->description(__('admin.widgets.content_description', [
                    'experiences' => Experience::query()->count(),
                    'projects' => Project::query()->count(),
                ])),
            Stat::make(__('admin.widgets.cv_documents'), "{$withCv}/{$languages->count()}")
                ->description(__('admin.widgets.cv_documents_description'))
                ->color($withCv < $languages->count() ? 'warning' : 'success'),
        ];

        foreach (app(TranslationReport::class)->missingByLocale() as $locale => $missing) {
            $stats[] = Stat::make(__('admin.widgets.translations', ['language' => $locale]), $missing === 0 ? '✓' : (string) $missing)
                ->description($missing === 0 ? __('admin.widgets.translations_complete') : __('admin.widgets.translations_missing'))
                ->color($missing === 0 ? 'success' : 'warning');
        }

        return $stats;
    }
}
