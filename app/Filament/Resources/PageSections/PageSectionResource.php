<?php

namespace App\Filament\Resources\PageSections;

use App\Filament\Resources\PageSections\Pages\ManagePageSections;
use App\Filament\Support\HasAdminLabels;
use App\Filament\Support\Translatable;
use App\Models\PageSection;
use App\Support\Languages\Locales;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Layout della pagina CV: sezioni attive, ordine (drag & drop), titoli e voci di menu.
 * Le sezioni sono predefinite: non si creano né si eliminano.
 */
class PageSectionResource extends Resource
{
    use HasAdminLabels;

    protected static ?string $model = PageSection::class;

    protected static string $labelKey = 'page_sections';

    protected static string $navigationGroupKey = 'site';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                Toggle::make('is_enabled')->label(__('admin.fields.is_enabled')),
                Toggle::make('in_menu')->label(__('admin.fields.in_menu')),
            ]),
            Translatable::tabs(fn (string $locale): array => [
                TextInput::make("title.{$locale}")
                    ->label(__('admin.fields.section_title'))
                    ->placeholder(fn (?PageSection $record): ?string => $record ? __($record->key->siteStringKey(), locale: $locale) : null)
                    ->helperText(__('admin.help.section_title'))
                    ->maxLength(100),
            ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->paginated(false)
            ->columns([
                TextColumn::make('key')->label(__('admin.fields.section')),
                TextColumn::make('display_title')
                    ->label(__('admin.fields.section_title'))
                    ->state(fn (PageSection $record): string => $record->displayTitle(app(Locales::class)->default())),
                ToggleColumn::make('is_enabled')->label(__('admin.fields.is_enabled')),
                ToggleColumn::make('in_menu')->label(__('admin.fields.in_menu')),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePageSections::route('/'),
        ];
    }
}
