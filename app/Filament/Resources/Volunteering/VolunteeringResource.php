<?php

namespace App\Filament\Resources\Volunteering;

use App\Enums\ExperienceKind;
use App\Filament\Resources\Experiences\ExperienceResource;
use App\Filament\Resources\Volunteering\Pages\CreateVolunteering;
use App\Filament\Resources\Volunteering\Pages\EditVolunteering;
use App\Filament\Resources\Volunteering\Pages\ListVolunteering;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * Volontariato: stesso modello e stessi campi delle esperienze di lavoro (kind = volunteering).
 */
class VolunteeringResource extends ExperienceResource
{
    protected static string $labelKey = 'volunteering';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'volunteering';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    public static function kind(): ExperienceKind
    {
        return ExperienceKind::Volunteering;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVolunteering::route('/'),
            'create' => CreateVolunteering::route('/create'),
            'edit' => EditVolunteering::route('/{record}/edit'),
        ];
    }
}
