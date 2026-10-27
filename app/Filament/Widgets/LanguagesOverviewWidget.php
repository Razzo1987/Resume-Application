<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Pages\Translations;
use App\Filament\Resources\Languages\LanguageResource;
use App\Models\Language;
use App\Support\Content\TranslationReport;
use App\Support\Languages\Locales;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dashboard, Languages group: site languages and, for each one, the texts still to translate.
 */
class LanguagesOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected function getHeading(): ?string
    {
        return __('admin.dashboard.languages');
    }

    protected function getStats(): array
    {
        $locales = app(Locales::class);
        $active = $locales->activeLanguages();

        $stats = [
            Stat::make(__('admin.dashboard.site_languages'), (string) $active->count())
                ->description($active->map(fn (Language $language): string => $language->name.($language->is_default ? ' ★' : ''))->implode(' · '))
                ->url(LanguageResource::getUrl()),
        ];

        foreach (app(TranslationReport::class)->missingByLocale() as $locale => $missing) {
            $stats[] = Stat::make(__('admin.widgets.translations', ['language' => $locale]), $missing === 0 ? '✓' : (string) $missing)
                ->description($missing === 0 ? __('admin.widgets.translations_complete') : __('admin.widgets.translations_missing'))
                ->descriptionIcon($missing === 0 ? null : 'heroicon-m-arrow-right')
                ->color($missing === 0 ? 'success' : 'warning')
                // List of what is missing, with the link to edit it
                ->url(Translations::getUrl(['locale' => $locale]));
        }

        return $stats;
    }
}
