<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\ExperienceKind;
use App\Filament\Resources\Experiences\ExperienceResource;
use App\Filament\Resources\Volunteering\VolunteeringResource;
use App\Models\Article;
use App\Models\Experience;
use App\Models\Language;
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

    /**
     * Sposta un'esperienza tra lavoro e volontariato (le due sezioni hanno elenchi separati in admin).
     */
    public static function moveExperience(): Action
    {
        return Action::make('moveExperience')
            ->label(fn (Experience $record): string => $record->kind === ExperienceKind::Work
                ? __('admin.actions.move_to_volunteering')
                : __('admin.actions.move_to_work'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('gray')
            ->requiresConfirmation()
            ->action(function (Experience $record, Action $action): void {
                $record->kind = $record->kind === ExperienceKind::Work ? ExperienceKind::Volunteering : ExperienceKind::Work;
                $record->save();

                $resource = $record->kind === ExperienceKind::Work ? ExperienceResource::class : VolunteeringResource::class;
                $action->redirect($resource::getUrl('edit', ['record' => $record]));
            });
    }

    /**
     * Home del sito per il pulsante "Vai al sito" della barra in alto: nella lingua dell'admin se è attiva sul sito
     * ("it" → it-IT), altrimenti nella lingua di default.
     */
    public static function homeUrl(): string
    {
        $locales = app(Locales::class);
        $admin = strtolower((string) config('resume.admin_locale', 'it'));

        $match = $locales->activeLanguages()
            ->map(fn (Language $language): string => $language->code)
            ->first(fn (string $code): bool => strcasecmp($code, $admin) === 0 || str_starts_with(strtolower($code), $admin.'-'));

        return url(app(SiteUrls::class)->homePath($match ?? $locales->default()));
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
