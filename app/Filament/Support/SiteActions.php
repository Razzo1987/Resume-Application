<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\Article;
use App\Models\Experience;
use App\Models\Project;
use App\Site\SiteUrls;
use App\Support\Languages\Locales;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Azioni dell'admin che portano al sito pubblico.
 */
class SiteActions
{
    /**
     * "Apri nel sito": la pagina dedicata nella lingua di default, se esiste (pubblicata, con dettaglio...).
     */
    public static function viewOnSite(): Action
    {
        return Action::make('viewOnSite')
            ->label(__('admin.actions.view_on_site'))
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->color('gray')
            ->url(fn (Model $record): ?string => self::url($record))
            ->openUrlInNewTab()
            ->visible(fn (Model $record): bool => self::url($record) !== null);
    }

    public static function url(Model $record): ?string
    {
        $locale = app(Locales::class)->default();
        $urls = app(SiteUrls::class);

        $path = match (true) {
            $record instanceof Project => $record->is_visible && $record->hasDetailPage() ? $urls->projectPath($record, $locale) : null,
            $record instanceof Experience => $record->is_visible && $record->hasDetailPage() ? $urls->experiencePath($record, $locale) : null,
            $record instanceof Article => $record->isPublished() ? self::firstArticlePath($record, $urls) : null,
            default => null,
        };

        return $path !== null ? url($path) : null;
    }

    /**
     * Un articolo può non essere scritto nella lingua di default: si apre nella prima lingua in cui esiste.
     */
    private static function firstArticlePath(Article $article, SiteUrls $urls): ?string
    {
        foreach (app(Locales::class)->active() as $code) {
            if (($path = $urls->articlePath($article, $code)) !== null) {
                return $path;
            }
        }

        return null;
    }
}
