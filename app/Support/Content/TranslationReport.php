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
use App\Models\PageSection;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Publication;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Support\Languages\Locales;
use App\Support\Settings\Settings;
use App\Support\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Contenuti con traduzioni mancanti per ciascuna lingua attiva: un campo scritto in almeno una lingua e vuoto in
 * un'altra. Comprende i titoli personalizzati delle sezioni e i testi delle impostazioni (SEO, footer).
 */
class TranslationReport
{
    /** @var list<class-string<Model&Translatable>> */
    public const array MODELS = [
        Profile::class, Experience::class, Project::class, Education::class, Certification::class,
        Publication::class, Article::class, Skill::class, SkillCategory::class, Interest::class,
        Highlight::class, LanguageSkill::class, PageSection::class,
    ];

    public function __construct(
        private readonly Locales $locales,
        private readonly Settings $settings,
    ) {}

    /**
     * @return array<string, int> lingua => numero di contenuti da completare
     */
    public function missingByLocale(): array
    {
        $report = [];

        foreach ($this->locales->active() as $locale) {
            $report[$locale] = count($this->missing($locale));
        }

        return $report;
    }

    /**
     * Cosa manca in una lingua: per ogni contenuto incompleto i campi da tradurre.
     * Il record è null per i testi delle impostazioni.
     *
     * @return list<array{record: (Model&Translatable)|null, fields: list<string>}>
     */
    public function missing(string $locale): array
    {
        $items = [];

        foreach (self::MODELS as $model) {
            foreach ($model::query()->orderBy((new $model)->getKeyName())->cursor() as $record) {
                $fields = $record->missingTranslations($locale);

                if ($fields !== []) {
                    $items[] = ['record' => $record, 'fields' => $fields];
                }
            }
        }

        $settings = $this->settings->missingTranslations($locale);

        if ($settings !== []) {
            $items[] = ['record' => null, 'fields' => $settings];
        }

        return $items;
    }
}
