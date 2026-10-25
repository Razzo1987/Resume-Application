<?php

namespace App\Filament\Resources\Experiences;

use App\Enums\ExperienceKind;
use App\Filament\Resources\Experiences\Pages\CreateExperience;
use App\Filament\Resources\Experiences\Pages\EditExperience;
use App\Filament\Resources\Experiences\Pages\ListExperiences;
use App\Filament\Resources\Experiences\Schemas\ExperienceForm;
use App\Filament\Resources\Experiences\Tables\ExperiencesTable;
use App\Filament\Support\HasAdminLabels;
use App\Models\Experience;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Esperienze di lavoro. Il volontariato usa lo stesso modello con una risorsa dedicata.
 */
class ExperienceResource extends Resource
{
    use HasAdminLabels;

    protected static ?string $model = Experience::class;

    protected static string $labelKey = 'experiences';

    protected static string $navigationGroupKey = 'cv';

    protected static ?int $navigationSort = 20;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    public static function kind(): ExperienceKind
    {
        return ExperienceKind::Work;
    }

    /**
     * @return Builder<Experience>
     */
    public static function getEloquentQuery(): Builder
    {
        return Experience::query()->where('kind', static::kind());
    }

    public static function form(Schema $schema): Schema
    {
        return ExperienceForm::configure($schema, static::kind());
    }

    public static function table(Table $table): Table
    {
        return ExperiencesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExperiences::route('/'),
            'create' => CreateExperience::route('/create'),
            'edit' => EditExperience::route('/{record}/edit'),
        ];
    }
}
