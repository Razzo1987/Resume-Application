<?php

namespace App\Filament\Pages;

use App\Filament\Support\Fields;
use App\Filament\Support\Translatable;
use App\Site\PrivacyPolicy;
use App\Site\SiteUrls;
use App\Support\Languages\Locales;
use App\Support\Settings\Settings;
use App\Themes\ThemeManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;

/**
 * Impostazioni del sito: tema, CV, SEO, footer, stampa, privacy dei contatti e dati strutturati.
 *
 * @property-read Schema $form
 */
class ManageSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.site');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.pages.settings.title');
    }

    public function getTitle(): string
    {
        return __('admin.pages.settings.title');
    }

    public function mount(Settings $settings): void
    {
        // Le chiavi puntate (es. "cv.filename_pattern") diventano campi annidati del form
        $this->form->fill(Arr::undot($settings->all()));
    }

    public function form(Schema $schema): Schema
    {
        $themes = app(ThemeManager::class);

        return $schema
            ->components([
                Section::make(__('admin.sections.appearance'))
                    ->schema([
                        // Il tema si sceglie solo nella pagina Temi (versioni, anteprima delle informazioni, Attiva)
                        TextEntry::make('active_theme')
                            ->label(__('admin.fields.theme'))
                            ->state(fn (): string => $themes->active()->title)
                            ->url(fn (): string => ManageThemes::getUrl())
                            ->color('primary')
                            ->icon(Heroicon::OutlinedSwatch)
                            ->hint(__('admin.help.theme_on_themes_page')),
                        Toggle::make('print.enabled')->label(__('admin.fields.print_enabled'))->helperText(__('admin.help.print_enabled')),
                        Toggle::make('footer.show_last_updated')->label(__('admin.fields.show_last_updated'))->helperText(__('admin.help.show_last_updated')),
                    ])
                    ->columnSpanFull(),

                Section::make(__('admin.sections.cv_download'))
                    ->schema([
                        TextInput::make('cv.filename_pattern')
                            ->label(__('admin.fields.cv_filename_pattern'))
                            ->helperText(__('admin.help.cv_filename_pattern'))
                            ->required()
                            ->maxLength(120),
                        Toggle::make('cv.fallback_to_default')->label(__('admin.fields.cv_fallback'))->helperText(__('admin.help.cv_fallback')),
                    ])
                    ->columnSpanFull(),

                Section::make(__('admin.sections.privacy'))
                    ->description(__('admin.help.privacy'))
                    ->schema([
                        Grid::make(3)->schema([
                            Toggle::make('privacy.show_email')->label(__('admin.fields.show_email')),
                            Toggle::make('privacy.show_phone')->label(__('admin.fields.show_phone')),
                            Toggle::make('privacy.show_birthdate')->label(__('admin.fields.show_birthdate')),
                        ]),
                    ])
                    ->columnSpanFull(),

                Section::make(__('admin.sections.privacy_policy'))
                    ->description(__('admin.help.privacy_policy'))
                    ->schema([
                        Toggle::make('privacy.page')
                            ->label(__('admin.fields.privacy_page'))
                            ->helperText(fn (): string => app(PrivacyPolicy::class)->statsEnabled() ? __('admin.help.privacy_page_forced') : __('admin.help.privacy_page'))
                            ->disabled(fn (): bool => app(PrivacyPolicy::class)->statsEnabled())
                            ->hintAction(
                                Action::make('viewPrivacy')
                                    ->label(__('admin.actions.view_page'))
                                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                                    ->url(fn (): string => url(app(SiteUrls::class)->privacyPath(app(Locales::class)->default())), shouldOpenInNewTab: true)
                                    ->visible(fn (): bool => app(PrivacyPolicy::class)->isEnabled()),
                            ),
                        Translatable::tabs(fn (string $locale): array => [
                            RichEditor::make("privacy.text.{$locale}")
                                ->label(__('admin.fields.privacy_text'))
                                ->helperText(__('admin.help.privacy_text'))
                                ->toolbarButtons([['bold', 'italic', 'link'], ['h2', 'h3'], ['bulletList', 'orderedList'], ['undo', 'redo']])
                                ->hintAction(
                                    Action::make("privacyTemplate_{$locale}")
                                        ->label(__('admin.actions.use_template'))
                                        ->icon(Heroicon::OutlinedDocumentText)
                                        ->requiresConfirmation()
                                        ->modalDescription(__('admin.help.use_template'))
                                        ->action(fn (Set $set) => $set("privacy.text.{$locale}", app(PrivacyPolicy::class)->template($locale))),
                                ),
                        ], 'privacy-texts'),
                    ])
                    ->columnSpanFull(),

                Section::make(__('admin.sections.structured_data'))
                    ->description(__('admin.help.structured_data'))
                    ->schema([
                        Grid::make(3)->schema([
                            Toggle::make('jsonld.include_email')->label(__('admin.fields.jsonld_email')),
                            Toggle::make('jsonld.include_phone')->label(__('admin.fields.jsonld_phone')),
                            Toggle::make('jsonld.include_birthdate')->label(__('admin.fields.jsonld_birthdate')),
                        ]),
                    ])
                    ->columnSpanFull(),

                Section::make(__('admin.sections.seo'))
                    ->schema([
                        Fields::image('seo.og_image', 'seo', 1200, 'jpeg')
                            ->label(__('admin.fields.og_image'))
                            ->helperText(__('admin.help.og_image')),
                        Fields::image('site.icon', 'site', 512, 'png')
                            ->label(__('admin.fields.site_icon'))
                            ->helperText(__('admin.help.site_icon'))
                            ->imageEditor()
                            ->imageCropAspectRatio('1:1'),
                        Translatable::tabs(fn (string $locale): array => [
                            TextInput::make("seo.title.{$locale}")->label(__('admin.fields.seo_title'))->helperText(__('admin.help.default_seo_title'))->maxLength(70),
                            Textarea::make("seo.description.{$locale}")->label(__('admin.fields.seo_description'))->maxLength(170)->rows(2),
                            Textarea::make("footer.gdpr_text.{$locale}")->label(__('admin.fields.gdpr_text'))->helperText(__('admin.help.gdpr_text'))->rows(3),
                        ], 'settings-texts'),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label(__('admin.actions.save'))->submit('save')->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(Settings $settings): void
    {
        $state = $this->form->getState();
        $values = [];

        // Solo le chiavi note presenti nel form ("seo.title" → array {lingua: testo})
        foreach (array_keys(Settings::DEFAULTS) as $key) {
            if (! Arr::has($state, $key)) {
                continue;
            }

            $value = data_get($state, $key);

            $values[$key] = in_array($key, Settings::TRANSLATABLE, true) && is_array($value)
                ? array_filter($value, fn (mixed $text): bool => is_string($text) && trim($text) !== '')
                : $value;
        }

        $settings->setMany($values);

        Notification::make()->success()->title(__('admin.messages.saved'))->send();
    }
}
