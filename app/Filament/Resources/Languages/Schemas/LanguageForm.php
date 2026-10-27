<?php

namespace App\Filament\Resources\Languages\Schemas;

use App\Models\Language;
use App\Support\Languages\LanguagePacks;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class LanguageForm
{
    public static function configure(Schema $schema): Schema
    {
        $packs = app(LanguagePacks::class);

        return $schema->components([
            Section::make(__('admin.sections.general'))
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('code')
                            ->label(__('admin.fields.language_code'))
                            ->helperText(__('admin.help.language_code', ['packs' => implode(', ', $packs->available())]))
                            ->datalist($packs->available())
                            ->required()
                            ->regex('/^[a-z]{2,3}-[A-Z]{2}$/')
                            ->unique(ignoreRecord: true)
                            ->disabledOn('edit')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Set $set, Get $get) use ($packs): void {
                                if ($state !== null && blank($get('name'))) {
                                    $set('name', $packs->nativeName($state));
                                }
                            }),
                        TextInput::make('name')
                            ->label(__('admin.fields.language_name'))
                            ->helperText(__('admin.help.language_name'))
                            ->required()
                            ->maxLength(100),
                    ]),
                    Grid::make(2)->schema([
                        Toggle::make('is_active')
                            ->label(__('admin.fields.is_active'))
                            ->default(true)
                            ->disabled(fn (?Language $record): bool => (bool) $record?->is_default),
                        Toggle::make('is_default')
                            ->label(__('admin.fields.is_default'))
                            // Explanation + warning: browsers cache the redirect of "/" for one day (RootController)
                            ->belowContent([
                                Text::make(__('admin.help.is_default')),
                                Text::make(__('admin.help.default_language_cache'))->color('warning'),
                            ])
                            ->disabled(fn (?Language $record): bool => (bool) $record?->is_default),
                    ]),
                ])
                ->columnSpanFull(),

            Section::make(__('admin.sections.url_segments'))
                ->description(__('admin.help.url_segments'))
                ->schema(array_map(
                    fn (string $key): TextInput => TextInput::make("url_segments.{$key}")
                        ->label(__("admin.fields.segment_{$key}"))
                        ->placeholder(fn (Get $get): string => $packs->segments((string) $get('code'))[$key] ?? $packs->segments('en-GB')[$key] ?? $key)
                        ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                        ->maxLength(50),
                    LanguagePacks::SEGMENTS,
                ))
                ->columns(4)
                ->columnSpanFull(),

            Section::make(__('admin.sections.string_overrides'))
                ->description(__('admin.help.string_overrides'))
                ->schema([
                    KeyValue::make('string_overrides')
                        ->hiddenLabel()
                        ->keyLabel(__('admin.fields.string_key'))
                        ->valueLabel(__('admin.fields.string_value'))
                        ->keyPlaceholder('actions.download_cv'),
                ])
                ->collapsed()
                ->columnSpanFull(),
        ]);
    }
}
