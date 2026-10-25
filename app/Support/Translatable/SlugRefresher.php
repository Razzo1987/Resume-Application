<?php

declare(strict_types=1);

namespace App\Support\Translatable;

use App\Models\Article;
use App\Models\Experience;
use App\Models\Project;
use App\Support\Languages\Locales;

/**
 * Crea gli slug mancanti (es. dopo l'attivazione di una nuova lingua), così ogni contenuto
 * con una pagina dedicata ha subito il suo indirizzo anche nella nuova lingua.
 */
final class SlugRefresher
{
    /** @var list<class-string<Project|Experience|Article>> */
    public const array MODELS = [Project::class, Experience::class, Article::class];

    public function __construct(private readonly Locales $locales) {}

    public function refresh(): int
    {
        // Elenco delle lingue attive aggiornato (la lingua potrebbe essere stata appena salvata)
        $this->locales->flush();
        $updated = 0;

        foreach (self::MODELS as $model) {
            $model::query()->each(function (Project|Experience|Article $record) use (&$updated): void {
                $record->fillMissingSlugs();

                if ($record->isDirty('slug')) {
                    // Senza eventi: non sono cambi di indirizzo (niente redirect) e la cache è già stata svuotata
                    $record->saveQuietly();
                    $updated++;
                }
            });
        }

        return $updated;
    }
}
