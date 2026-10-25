<?php

declare(strict_types=1);

namespace App\Support\Content;

use App\Models\Article;
use App\Models\Certification;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Highlight;
use App\Models\Interest;
use App\Models\LanguageSkill;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Publication;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Support\Languages\Locales;
use App\Support\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Conta i contenuti con traduzioni mancanti per ciascuna lingua attiva.
 */
class TranslationReport
{
    /** @var list<class-string<Model&Translatable>> */
    public const array MODELS = [
        Profile::class, Experience::class, Project::class, Education::class, Certification::class,
        Publication::class, Article::class, Skill::class, SkillCategory::class, Interest::class,
        Highlight::class, LanguageSkill::class,
    ];

    public function __construct(private readonly Locales $locales) {}

    /**
     * @return array<string, int> lingua => numero di contenuti da completare
     */
    public function missingByLocale(): array
    {
        $report = array_fill_keys($this->locales->active(), 0);

        foreach (self::MODELS as $model) {
            foreach ($model::query()->cursor() as $record) {
                foreach (array_keys($report) as $locale) {
                    if ($record->missingTranslations($locale) !== []) {
                        $report[$locale]++;
                    }
                }
            }
        }

        return $report;
    }
}
