<?php

namespace App\Filament\Pages;

use App\Enums\ExperienceKind;
use App\Filament\Resources\PageSections\PageSectionResource;
use App\Filament\Resources\Volunteering\VolunteeringResource;
use App\Filament\Support\Translatable as TranslatableFields;
use App\Models\Experience;
use App\Models\Highlight;
use App\Models\LanguageSkill;
use App\Models\PageSection;
use App\Models\Profile;
use App\Support\Content\TranslationReport;
use App\Support\Languages\Locales;
use App\Support\Translatable\Translatable;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

/**
 * Traduzioni: per ogni lingua attiva, i contenuti con campi da tradurre e il link per modificarli.
 * Si apre anche dai riquadri "Traduzioni" della dashboard.
 */
class Translations extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'translations';

    protected string $view = 'filament.pages.translations';

    #[Url]
    public ?string $locale = null;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.content');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.pages.translations.title');
    }

    public function getTitle(): string
    {
        return __('admin.pages.translations.title');
    }

    public function mount(): void
    {
        $locales = app(Locales::class)->active();

        if (! in_array($this->locale, $locales, true)) {
            // Prima lingua con qualcosa da tradurre, altrimenti la principale
            $counts = app(TranslationReport::class)->missingByLocale();
            $this->locale = array_key_first(array_filter($counts)) ?? app(Locales::class)->default();
        }
    }

    public function selectLocale(string $locale): void
    {
        if (in_array($locale, app(Locales::class)->active(), true)) {
            $this->locale = $locale;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $report = app(TranslationReport::class);
        $locales = app(Locales::class);
        $default = $locales->default();
        $locale = (string) $this->locale;

        $groups = [];

        foreach ($report->missing($locale) as $item) {
            $entry = $item['record'] === null ? $this->describeSettings($item['fields']) : $this->describe($item['record'], $item['fields'], $default);
            $groups[$entry['type']][] = $entry;
        }

        return [
            'locales' => collect($report->missingByLocale())->map(fn (int $count, string $code): array => [
                'code' => $code,
                'name' => $locales->activeLanguages()->firstWhere('code', $code)->name ?? $code,
                'count' => $count,
            ])->values()->all(),
            'current' => $locale,
            'groups' => $groups,
        ];
    }

    /**
     * @param  list<string>  $fields
     * @return array{type: string, title: string, fields: list<string>, url: ?string}
     */
    private function describe(Model&Translatable $record, array $fields, string $default): array
    {
        $resource = match (true) {
            $record instanceof Profile => null,
            $record instanceof PageSection => PageSectionResource::class,
            $record instanceof Experience && $record->kind === ExperienceKind::Volunteering => VolunteeringResource::class,
            default => Filament::getModelResource($record),
        };

        /** @var class-string<resource>|null $resource */
        return [
            'type' => $resource === null ? __('admin.pages.profile.title') : Str::ucfirst($resource::getPluralModelLabel()),
            'title' => $this->title($record, $default),
            'fields' => array_map(fn (string $field): string => $this->fieldLabel($field), $fields),
            'url' => match (true) {
                $resource === null => ManageProfile::getUrl(),
                $resource::hasPage('edit') => $resource::getUrl('edit', ['record' => $record]),
                default => $resource::getUrl('index'),
            },
        ];
    }

    /**
     * @param  list<string>  $keys
     * @return array{type: string, title: string, fields: list<string>, url: ?string}
     */
    private function describeSettings(array $keys): array
    {
        return [
            'type' => __('admin.pages.settings.title'),
            'title' => __('admin.translations.settings_texts'),
            'fields' => array_map(fn (string $key): string => $this->fieldLabel(match ($key) {
                'seo.title' => 'seo_title',
                'seo.description' => 'seo_description',
                'footer.gdpr_text' => 'gdpr_text',
                default => $key,
            }), $keys),
            'url' => ManageSettings::getUrl(),
        ];
    }

    private function title(Model&Translatable $record, string $default): string
    {
        $title = match (true) {
            $record instanceof Profile => $record->full_name,
            $record instanceof PageSection => $record->displayTitle($default),
            $record instanceof LanguageSkill => $record->displayName(),
            $record instanceof Highlight => $record->t('label', $default) ?? $record->formattedValue($default),
            default => null,
        };

        foreach (['title', 'role', 'degree', 'name', 'label'] as $field) {
            if (filled($title)) {
                break;
            }
            if (in_array($field, $record->getTranslatableAttributes(), true)) {
                $title = strip_tags((string) $record->t($field, $default));
            }
        }

        if ($record instanceof Experience && $record->organization !== null) {
            $title .= ' - '.$record->organization->name;
        }

        return filled($title) ? (string) $title : class_basename($record).' #'.$record->getKey();
    }

    private function fieldLabel(string $field): string
    {
        return TranslatableFields::fieldLabel($field);
    }
}
