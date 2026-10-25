<?php

namespace App\Filament\Resources\Publications\Schemas;

use App\Enums\PublicationType;
use App\Filament\Support\Fields;
use App\Filament\Support\Translatable;
use App\Support\Languages\IsoLanguages;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PublicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.sections.general'))
                ->schema([
                    Grid::make(3)->schema([
                        Select::make('type')->label(__('admin.fields.publication_type'))->options(PublicationType::class)->required()->default(PublicationType::Article->value),
                        DatePicker::make('published_on')->label(__('admin.fields.published_on'))->native(false),
                        Select::make('language_code')->label(__('admin.fields.publication_language'))->options(fn (): array => app(IsoLanguages::class)->options())->searchable(),
                    ]),
                    TextInput::make('coauthors')->label(__('admin.fields.coauthors'))->maxLength(255),
                    Grid::make(2)->schema([
                        TextInput::make('url')->label(__('admin.fields.url'))->url()->maxLength(500),
                        Fields::publicPdf('file', 'publications'),
                    ]),
                    Grid::make(2)->schema([
                        Toggle::make('is_featured')->label(__('admin.fields.is_featured')),
                        Fields::visible(),
                    ]),
                ])
                ->columnSpanFull(),

            Section::make(__('admin.sections.content'))
                ->schema([
                    Translatable::tabs(fn (string $locale, bool $isDefault): array => [
                        TextInput::make("title.{$locale}")->label(__('admin.fields.title'))->required($isDefault)->maxLength(255),
                        TextInput::make("venue.{$locale}")->label(__('admin.fields.venue'))->helperText(__('admin.help.venue'))->maxLength(255),
                        Fields::richSummary("description.{$locale}")
                            ->label(__('admin.fields.description')),
                    ]),
                ])
                ->columnSpanFull(),
        ]);
    }
}
