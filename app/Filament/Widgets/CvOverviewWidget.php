<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\CvDocuments\CvDocumentResource;
use App\Models\CvDocument;
use App\Models\Language;
use App\Support\Languages\Locales;
use App\Support\Settings\Settings;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dashboard, CV group: the downloadable PDF of each active language, with its date, and what happens if it is missing.
 */
class CvOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected function getHeading(): ?string
    {
        return __('admin.dashboard.cv');
    }

    protected function getStats(): array
    {
        $documents = CvDocument::query()->current()->get()->keyBy('language_id');
        $fallback = app(Settings::class)->bool('cv.fallback_to_default');
        $stats = [];

        foreach (app(Locales::class)->activeLanguages() as $language) {
            /** @var Language $language */
            $document = $documents->get($language->id);

            $stats[] = Stat::make(__('admin.dashboard.cv_language', ['language' => $language->name]), $document !== null ? '✓' : '-')
                ->description($document !== null
                    ? __('admin.dashboard.cv_updated', ['date' => $document->created_at?->translatedFormat('j M Y') ?? '-'])
                    : ($fallback && ! $language->is_default ? __('admin.dashboard.cv_fallback') : __('admin.dashboard.cv_missing')))
                ->color($document !== null ? ($document->created_at?->lt(now()->subMonths(6)) ? 'warning' : 'success') : 'warning')
                ->url(CvDocumentResource::getUrl());
        }

        return $stats;
    }
}
