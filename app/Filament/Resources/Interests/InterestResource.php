<?php

namespace App\Filament\Resources\Interests;

use App\Filament\Resources\Interests\Pages\ManageInterests;
use App\Filament\Support\Columns;
use App\Filament\Support\Fields;
use App\Filament\Support\HasAdminLabels;
use App\Filament\Support\Translatable;
use App\Models\Interest;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class InterestResource extends Resource
{
    use HasAdminLabels;

    protected static ?string $model = Interest::class;

    protected static string $labelKey = 'interests';

    protected static string $navigationGroupKey = 'cv';

    protected static ?int $navigationSort = 100;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFaceSmile;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Translatable::tabs(fn (string $locale, bool $isDefault): array => [
                TextInput::make("name.{$locale}")->label(__('admin.fields.name'))->required($isDefault)->maxLength(100),
                TextInput::make("description.{$locale}")->label(__('admin.fields.short_description'))->maxLength(300),
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
                Columns::translated('name'),
                TextColumn::make('icon')->label(__('admin.fields.icon'))->color('gray'),
                ToggleColumn::make('is_visible')->label(__('admin.fields.is_visible')),
                Columns::missingTranslations(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageInterests::route('/'),
        ];
    }
}
