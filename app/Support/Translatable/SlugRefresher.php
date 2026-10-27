<?php

declare(strict_types=1);

namespace App\Support\Translatable;

use App\Models\Article;
use App\Models\Experience;
use App\Models\Project;
use App\Support\Languages\Locales;

/**
 * Creates the missing slugs (e.g. after a new language is activated), so every content item
 * with its own page immediately has its URL in the new language too.
 */
final class SlugRefresher
{
    /** @var list<class-string<Project|Experience|Article>> */
    public const array MODELS = [Project::class, Experience::class, Article::class];

    public function __construct(private readonly Locales $locales) {}

    public function refresh(): int
    {
        // Fresh list of active languages (the language may have just been saved)
        $this->locales->flush();
        $updated = 0;

        foreach (self::MODELS as $model) {
            $model::query()->each(function (Project|Experience|Article $record) use (&$updated): void {
                $record->fillMissingSlugs();

                if ($record->isDirty('slug')) {
                    // Without events: these are not URL changes (no redirects) and the cache has already been cleared
                    $record->saveQuietly();
                    $updated++;
                }
            });
        }

        return $updated;
    }
}
