<?php

namespace App\Filament\Resources\Skills;

use App\Filament\Resources\Skills\Pages\ManageSkills;
use App\Filament\Support\Columns;
use App\Filament\Support\HasAdminLabels;
use App\Filament\Support\Translatable;
use App\Models\Skill;
use App\Models\SkillCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SkillResource extends Resource
{
    use HasAdminLabels;

    protected static ?string $model = Skill::class;

    protected static string $labelKey = 'skills';

    protected static string $navigationGroupKey = 'cv';

    protected static ?int $navigationSort = 110;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('skill_category_id')
                ->label(__('admin.fields.skill_category'))
                ->relationship('category', 'id')
                ->getOptionLabelFromRecordUsing(fn (SkillCategory $record): string => (string) $record->t('name'))
                ->preload()
                ->required(),
            Translatable::tabs(fn (string $locale, bool $isDefault): array => [
                TextInput::make("name.{$locale}")->label(__('admin.fields.name'))->required($isDefault)->maxLength(100),
            ]),
            Toggle::make('is_featured')->label(__('admin.fields.is_featured'))->helperText(__('admin.help.skill_featured')),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('category')->withCount(['experiences', 'projects']))
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Columns::translated('name'),
                TextColumn::make('category')
                    ->label(__('admin.fields.skill_category'))
                    ->state(fn (Skill $record): ?string => $record->category->t('name'))
                    ->badge(),
                TextColumn::make('experiences_count')->label(Str::ucfirst(__('admin.resources.experiences.plural'))),
                TextColumn::make('projects_count')->label(Str::ucfirst(__('admin.resources.projects.plural'))),
                ToggleColumn::make('is_featured')->label(__('admin.fields.is_featured')),
                Columns::missingTranslations(),
            ])
            ->filters([
                SelectFilter::make('skill_category_id')
                    ->label(__('admin.fields.skill_category'))
                    ->options(fn (): array => SkillCategory::query()->ordered()->get()->mapWithKeys(fn (SkillCategory $c): array => [$c->id => (string) $c->t('name')])->all()),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSkills::route('/'),
        ];
    }
}
