<?php

namespace App\Filament\Pages;

use App\Filament\Support\Fields;
use App\Filament\Support\Translatable;
use App\Models\Profile;
use App\Support\Languages\Locales;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Personal details: name, photo, contacts, headlines, bio.
 *
 * @property-read Schema $form
 */
class ManageProfile extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'profile-data';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.cv');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.pages.profile.title');
    }

    public function getTitle(): string
    {
        return __('admin.pages.profile.title');
    }

    public function mount(): void
    {
        $this->form->fill(Profile::current()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.sections.person'))
                    ->schema([
                        TextInput::make('full_name')->label(__('admin.fields.full_name'))->required()->maxLength(255),
                        Grid::make(2)->schema([
                            Fields::image('photo', 'profile', 800)->imageEditor()->imageCropAspectRatio('1:1'),
                            Grid::make(1)->schema([
                                DatePicker::make('birth_date')->label(__('admin.fields.birth_date'))->native(false)->helperText(__('admin.help.birth_date')),
                                DatePicker::make('career_start_date')->label(__('admin.fields.career_start_date'))->native(false)->helperText(__('admin.help.career_start_date')),
                            ]),
                        ]),
                    ])
                    ->columnSpanFull(),
                Section::make(__('admin.sections.contacts'))
                    ->description(__('admin.help.contacts'))
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('email')->label(__('admin.fields.email'))->email()->maxLength(255),
                            TextInput::make('phone')->label(__('admin.fields.phone'))->tel()->maxLength(40),
                            TextInput::make('country_code')
                                ->label(__('admin.fields.country_code'))
                                ->helperText(__('admin.help.country_code'))
                                ->length(2)
                                ->regex('/^[A-Za-z]{2}$/')
                                ->dehydrateStateUsing(fn (?string $state): ?string => $state !== null ? strtoupper($state) : null),
                        ]),
                    ])
                    ->columnSpanFull(),
                Section::make(__('admin.sections.presentation'))
                    ->description(fn (): ?string => $this->missingSummary())
                    ->schema([
                        Translatable::tabs(fn (string $locale): array => [
                            TextInput::make("location.{$locale}")->label(__('admin.fields.location'))->helperText(__('admin.help.location'))->maxLength(255),
                            Textarea::make("headlines.{$locale}")->label(__('admin.fields.headlines'))->helperText(__('admin.help.headlines'))->rows(4),
                            RichEditor::make("bio.{$locale}")
                                ->label(__('admin.fields.bio'))
                                ->toolbarButtons([['bold', 'italic', 'link'], ['h3', 'bulletList', 'orderedList'], ['undo', 'redo']]),
                            TextInput::make("photo_alt.{$locale}")->label(__('admin.fields.image_alt'))->maxLength(255),
                        ], missing: fn (string $locale): array => Profile::current()->missingTranslations($locale)),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    /**
     * Texts written in a language but still empty in another one ("English: Presentazione, Località").
     */
    private function missingSummary(): ?string
    {
        $profile = Profile::current();
        $parts = [];

        foreach (app(Locales::class)->activeLanguages() as $language) {
            $fields = $profile->missingTranslations($language->code);

            if ($fields !== []) {
                $parts[] = $language->name.': '.implode(', ', array_map(Translatable::fieldLabel(...), $fields));
            }
        }

        return $parts !== [] ? __('admin.translations.missing_in', ['list' => implode(' · ', $parts)]) : null;
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

    public function save(): void
    {
        Profile::current()->update($this->form->getState());

        Notification::make()->success()->title(__('admin.messages.saved'))->send();
    }
}
