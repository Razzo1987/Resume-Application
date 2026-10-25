<?php

namespace App\Filament\Resources\SkillCategories;

use App\Filament\Resources\SkillCategories\Pages\ManageSkillCategories;
use App\Filament\Support\Columns;
use App\Filament\Support\HasAdminLabels;
use App\Filament\Support\Translatable;
use App\Models\SkillCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class SkillCategoryResource extends Resource
{
    use HasAdminLabels;

    protected static ?string $model = SkillCategory::class;

    protected static string $labelKey = 'skill_categories';

    protected static string $navigationGroupKey = 'cv';

    protected static ?int $navigationSort = 120;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')
                ->label(__('admin.fields.key'))
                ->helperText(__('admin.help.category_key'))
                ->required()
                ->alphaDash()
                ->maxLength(30)
                ->unique(ignoreRecord: true),
            Translatable::tabs(fn (string $locale, bool $isDefault): array => [
                TextInput::make("name.{$locale}")->label(__('admin.fields.name'))->required($isDefault)->maxLength(100),
            ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Columns::translated('name'),
                TextColumn::make('key')->label(__('admin.fields.key'))->badge()->color('gray'),
                TextColumn::make('skills_count')->label(Str::ucfirst(__('admin.resources.skills.plural')))->counts('skills'),
                Columns::missingTranslations(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSkillCategories::route('/'),
        ];
    }
}
