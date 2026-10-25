<?php

namespace App\Filament\Resources\Highlights;

use App\Enums\HighlightDisplay;
use App\Enums\HighlightSource;
use App\Filament\Resources\Highlights\Pages\ManageHighlights;
use App\Filament\Support\Columns;
use App\Filament\Support\Fields;
use App\Filament\Support\HasAdminLabels;
use App\Filament\Support\Translatable;
use App\Models\Highlight;
use App\Support\Languages\Locales;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class HighlightResource extends Resource
{
    use HasAdminLabels;

    protected static ?string $model = Highlight::class;

    protected static string $labelKey = 'highlights';

    protected static string $navigationGroupKey = 'cv';

    protected static ?int $navigationSort = 15;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    public static function form(Schema $schema): Schema
    {
        $isManual = fn (Get $get): bool => ($get('source') instanceof HighlightSource ? $get('source') : HighlightSource::tryFrom((string) $get('source'))) === HighlightSource::Manual;

        return $schema->components([
            Select::make('source')
                ->label(__('admin.fields.highlight_source'))
                ->options(HighlightSource::class)
                ->default(HighlightSource::Manual->value)
                ->required()
                ->live(),
            Grid::make(3)->schema([
                TextInput::make('value')
                    ->label(__('admin.fields.value'))
                    ->numeric()
                    ->required($isManual)
                    ->visible($isManual),
                Radio::make('display')
                    ->label(__('admin.fields.highlight_display'))
                    ->options(HighlightDisplay::class)
                    ->default(HighlightDisplay::Exact->value)
                    ->inline()
                    ->required(),
                TextInput::make('unit')->label(__('admin.fields.unit'))->helperText(__('admin.help.unit'))->maxLength(10),
            ]),
            Translatable::tabs(fn (string $locale, bool $isDefault): array => [
                TextInput::make("label.{$locale}")
                    ->label(__('admin.fields.highlight_label'))
                    ->helperText(__('admin.help.highlight_label'))
                    ->required(fn (Get $get): bool => $isDefault && $isManual($get))
                    ->maxLength(120),
            ]),
            Fields::icon(),
            Fields::visible(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('preview')
                    ->label(__('admin.fields.preview'))
                    ->state(fn (Highlight $record): string => ($record->formattedValue(app(Locales::class)->default()) ?? '—'))
                    ->weight('bold'),
                TextColumn::make('label_preview')
                    ->label(__('admin.fields.highlight_label'))
                    ->state(fn (Highlight $record): ?string => $record->displayLabel(app(Locales::class)->default())),
                TextColumn::make('source')->label(__('admin.fields.highlight_source'))->badge()->color('gray'),
                Columns::missingTranslations(),
                ToggleColumn::make('is_visible')->label(__('admin.fields.is_visible')),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageHighlights::route('/'),
        ];
    }
}
