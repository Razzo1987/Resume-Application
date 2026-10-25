<?php

namespace App\Filament\Resources\Volunteering\Pages;

use App\Filament\Resources\Volunteering\VolunteeringResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVolunteering extends ListRecords
{
    protected static string $resource = VolunteeringResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
