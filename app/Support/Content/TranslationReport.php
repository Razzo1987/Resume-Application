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
 * Content with missing translations for each active language: a field written in at least one language and empty in
 * another. Includes the custom section titles and the settings texts (SEO, footer).
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
     * @return array<string, int> language => number of items to complete
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
     * What is missing in a language: for each incomplete item, the fields to translate.
     * The record is null for the settings texts.
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
